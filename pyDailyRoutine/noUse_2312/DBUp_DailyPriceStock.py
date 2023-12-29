
import pandas as pd
from bs4 import BeautifulSoup
import pymysql, calendar, json
import requests
from datetime import datetime
from threading import Timer

class DBUpdater:
    def __init__(self):
        """생성자: MariaDB 연결 및 종목코드 딕셔너리 생성"""
        self.conn = pymysql.connect(host='yunseul0907.cafe24.com', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')

        with self.conn.cursor() as curs:
            sql = """
            CREATE TABLE IF NOT EXISTS company_info (
                code VARCHAR(20),
                company VARCHAR(40),
                last_update DATE,
                PRIMARY KEY (code))
            """
            curs.execute(sql)
            sql = """
            CREATE TABLE IF NOT EXISTS daily_price (
                code VARCHAR(20),
                date DATE,
                open BIGINT(20),
                high BIGINT(20),
                low BIGINT(20),
                close BIGINT(20),
                diff BIGINT(20),
                volume BIGINT(20),
                PRIMARY KEY (code, date))
            """
            curs.execute(sql)
        self.conn.commit()
        self.codes = dict()

    def __del__(self):
        """소멸자: MariaDB 연결 해제"""
        self.conn.close()

    def read_naver(self, code, pages_to_fetch):
        """네이버에서 주식 시세를 읽어서 데이터프레임으로 반환"""
        try:
            url = f"http://finance.naver.com/item/sise_day.nhn?code={code}"
            html = BeautifulSoup(requests.get(url,
                headers={'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text, "lxml")
            # 1page 인 경우 class가 pgRR 이 아니라 on 라서 읽어오지 못하는 경우 발생. 로직 변경. 20230315
            pgrr = html.find("td", class_="pgRR")
            if pgrr is None:
                pgrr2 = html.find("td", class_="on")
                if pgrr2 is None:
                    return None
                else:
                    s = str(pgrr2.a["href"]).split('=')
                    df = pd.DataFrame()
                    df = df.append(pd.read_html(requests.get(url,
                        headers={'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text)[0])
                    tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
                    print('[{}] {} pages are downloading...'.
                        format(tmnow, code), end="\r")
            else :
                s = str(pgrr.a["href"]).split('=')
                lastpage = s[-1]
                df = pd.DataFrame()
                pages = min(int(lastpage), pages_to_fetch)
                for page in range(1, pages + 1):
                    pg_url = '{}&page={}'.format(url, page)
                    df = df.append(pd.read_html(requests.get(pg_url,
                        headers={'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text)[0])
                    tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
                    print('[{}] {} : {:04d}/{:04d} pages are downloading...'.
                        format(tmnow, code, page, pages), end="\r")

            df = df.rename(columns={'날짜':'date','종가':'close','전일비':'diff'
                ,'시가':'open','고가':'high','저가':'low','거래량':'volume'})
            df['date'] = df['date'].replace('.', '-')
            df = df.dropna()
            df[['close', 'diff', 'open', 'high', 'low', 'volume']] = df[['close',
                'diff', 'open', 'high', 'low', 'volume']].astype(int)
            df = df[['date', 'open', 'high', 'low', 'close', 'diff', 'volume']]
        except Exception as e:
            print('Exception occured :', str(e))
            return None
        return df

    def replace_into_db(self, df, num, code):

        """네이버에서 읽어온 주식 시세를 DB에 반영"""
        with self.conn.cursor() as curs:
            """네이버 데이터 반영 전 기존 데이터 삭제 처리"""
            sql = f"DELETE FROM daily_price WHERE code = '{code}' "
            curs.execute(sql)

            """일자별 주식 시세 반영"""
            for r in df.itertuples():
                sql = f"INSERT INTO daily_price(code,date,open,high,low,close,diff,volume)  "\
                    f"VALUES ('{code}', '{r.date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.diff}, {r.volume})"
                # print(sql)
                curs.execute(sql)
            self.conn.commit()
            print('[{}] #{:04d}  ({}) : {} rows > REPLACE INTO daily_'\
                'price [OK]'.format(datetime.now().strftime('%Y-%m-%d %H:%M'), num+1, code, len(df)))

            """전일 종가 반영 및 등락률 계산"""
            sql1=  f"UPDATE daily_price A "\
                    f" INNER JOIN (SELECT X.date pre_date, X.close pre_close, STR_TO_DATE((select min(date) from calendar Y where Y.date > X.date), '%Y%m%d') date "\
                    f"				FROM daily_price X "\
                    f"			   WHERE X.code = '{code}') B "\
                    f"	 ON B.date = A.date "\
                    f"  SET A.pre_close  = B.pre_close "\
                    f"	  , A.open_rate  = round((A.open-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.high_rate  = round((A.high-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.low_rate   = round((A.low-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.close_rate = round((A.close-B.pre_close)/B.pre_close*100,2) "\
                    f" WHERE A.code = '{code}' "
            # print(sql1)
            curs.execute(sql1)
            self.conn.commit()

            # daily_price VS market_ohlcv 종가등락률 다른 경우 처리
            sql2=  f"UPDATE daily_price A "\
                    f"INNER JOIN (SELECT X.date, X.code, X.close_rate, Y.close, CASE WHEN X.close_rate < 0 THEN Y.close + Y.diff ELSE Y.close - Y.diff END pre_close "\
                    f" 				FROM (SELECT date, code, close_rate FROM market_ohlcv WHERE code = '{code}') X, "\
                    f" 					 (SELECT date, code, close_rate, close, diff FROM daily_price WHERE code = '{code}') Y "\
                    f"	 			WHERE Y.date = X.date AND Y.code = X.code AND Y.close_rate != X.close_rate ) B "\
                    f"	 ON B.date = A.date "\
                    f"	AND B.code = A.code "\
                    f"	SET A.pre_close  = B.pre_close "\
                    f"	  , A.open_rate  = round((A.open-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.high_rate  = round((A.high-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.low_rate   = round((A.low-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.close_rate = round((A.close-B.pre_close)/B.pre_close*100,2) "\
                    f"	  , A.reset_market_ohlcv = 'Y'"

            # print(sql2)
            curs.execute(sql2)
            self.conn.commit()
            
    def update_daily_price(self, code, pages_to_fetch):
        """KRX 상장법인의 주식 시세를 네이버로부터 읽어서 DB에 업데이트"""
        df = self.read_naver(code, pages_to_fetch)
        self.replace_into_db(df, 1, code)

    def execute_daily(self, code):
        self.update_daily_price(code, 100)

        tmnow = datetime.now()
        lastday = calendar.monthrange(tmnow.year, tmnow.month)[1]
        if tmnow.month == 12 and tmnow.day == lastday:
            tmnext = tmnow.replace(year=tmnow.year+1, month=1, day=1, hour=17, minute=0, second=0)
        elif tmnow.day == lastday:
            tmnext = tmnow.replace(month=tmnow.month+1, day=1, hour=17, minute=0, second=0)
        else:
            tmnext = tmnow.replace(day=tmnow.day+1, hour=17, minute=0, second=0)
        tmdiff = tmnext - tmnow
        secs = tmdiff.seconds
        t = Timer(secs, self.execute_daily)
        print("처리완료!! ")

if __name__ == '__main__':
    # 특정 종목 시세 읽어오기. ( 반영 이 후 등락률 추가 반영 로직 추가 필요 2023.03.19)
    dbu = DBUpdater()
    dbu.execute_daily('204630')


# pd.concat(list_of_pd.DataFrame)이 df.append(df)보다 빠릅니다.
# import pandas as pd 
# import numpy as np 
# import time

# RG = np.random.RandomState(seed=0)

# Row_N = 10
# csv_N = 1000

# def read_csv_as_dict_lst():
#     """
#     - csv를 dictionary_lst로 읽었다고 생각함.
#     - row에 대한 정보를 {column_name: value}로 표현하여 모든 리스트에 넣어서 리턴. 
#     """
#     def random_string(str_len=20):
#         return "".join(map(chr, RG.randint(ord('a'), ord('z'), str_len)))
#     col_a = RG.randint(0, 100, Row_N)
#     col_b = RG.random(Row_N)
#     col_c = [random_string() for _ in range(0, Row_N)]
#     return [{"col_A": a, "col_B": b, "col_C": c} for a, b, c in zip(col_a, col_b, col_c)] 

# csv_lst = [read_csv_as_dict_lst() for _ in range(0, csv_N)]
# ##########################################################
# # df_A.append(df_B)
# # df_A에 df_B를 추가한 새로운 dataframe을 리턴함.
# start_time = time.time()
# df_append = pd.DataFrame(csv_lst[0])
# for each_csv in csv_lst[1:]:
#     df_append = df_append.append(pd.DataFrame(each_csv))
# print(f"== df.append execution time: {time.time() - start_time:.5f}")
# ##########################################################
# # pd.concat: 
# # list of pd.DataFrame을 모두 합쳐서 하나의 DF로 리턴하는 함수
# start_time = time.time() 
# df_concat = pd.concat([pd.DataFrame(each_csv) for each_csv in csv_lst])
# print(f"== pd.concat execution time: {time.time() - start_time:.5f}")
# assert len(df_concat) == len(df_append)