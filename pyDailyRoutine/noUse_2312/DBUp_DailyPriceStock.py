import pandas as pd
from bs4 import BeautifulSoup
import pymysql
import calendar
from datetime import datetime
from threading import Timer
import requests
from io import StringIO

class DBUpdater:
    def __init__(self):
        """생성자: MariaDB 연결 및 종목코드 딕셔너리 생성"""
        self.conn = pymysql.connect(
            host='siriens.mycafe24.com', 
            user='siriens', 
            password='hosting1004!', 
            db='siriens', 
            charset='utf8'
        )

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
            html = BeautifulSoup(requests.get(url, headers={
                'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
            }).text, "lxml")
            
            pgrr = html.find("td", class_="pgRR")
            if pgrr is not None:
                s = str(pgrr.a["href"]).split('=')
                lastpage = int(s[-1])
            else:
                lastpage = 1

            df = pd.DataFrame()
            pages = min(lastpage, pages_to_fetch)
            for page in range(1, pages + 1):
                pg_url = f'{url}&page={page}'
                html_content = requests.get(pg_url, headers={
                    'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
                }).text
                df = pd.concat([df, pd.read_html(StringIO(html_content))[0]])
                tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
                print(f'[{tmnow}] {code} : {page:04d}/{pages:04d} pages are downloading...', end="\r")

            df = df.rename(columns={
                '날짜': 'date', '종가': 'close', '전일비': 'diff',
                '시가': 'open', '고가': 'high', '저가': 'low', '거래량': 'volume'
            })
            df['date'] = df['date'].str.replace('.', '-')
            df = df.dropna()
            df[['close', 'diff', 'open', 'high', 'low', 'volume']] = df[['close', 'diff', 'open', 'high', 'low', 'volume']].astype(int)
            df = df[['date', 'open', 'high', 'low', 'close', 'diff', 'volume']]
        except Exception as e:
            print('Exception occured :', str(e))
            return None
        return df

    def replace_into_db(self, df, num, code):
        """네이버에서 읽어온 주식 시세를 DB에 반영"""
        with self.conn.cursor() as curs:
            sql = f"DELETE FROM daily_price WHERE code = '{code}'"
            curs.execute(sql)

            for r in df.itertuples():
                sql = f"INSERT INTO daily_price(code,date,open,high,low,close,diff,volume) VALUES ('{code}', '{r.date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.diff}, {r.volume})"
                curs.execute(sql)
            self.conn.commit()
            print(f'[{datetime.now().strftime("%Y-%m-%d %H:%M")}] #{num+1:04d}  ({code}) : {len(df)} rows > REPLACE INTO daily_price [OK]')

            sql1 = f"""
            UPDATE daily_price A
            INNER JOIN (
                SELECT X.date pre_date, X.close pre_close, STR_TO_DATE((SELECT MIN(date) FROM calendar Y WHERE Y.date > X.date), '%Y%m%d') date
                FROM daily_price X
                WHERE X.code = '{code}'
            ) B
            ON B.date = A.date
            SET A.pre_close = B.pre_close,
                A.open_rate = ROUND((A.open-B.pre_close)/B.pre_close*100, 2),
                A.high_rate = ROUND((A.high-B.pre_close)/B.pre_close*100, 2),
                A.low_rate = ROUND((A.low-B.pre_close)/B.pre_close*100, 2),
                A.close_rate = ROUND((A.close-B.pre_close)/B.pre_close*100, 2)
            WHERE A.code = '{code}'
            """
            curs.execute(sql1)
            self.conn.commit()

            sql2 = f"""
            UPDATE daily_price A
            INNER JOIN (
                SELECT X.date, X.code, X.close_rate, Y.close, 
                       CASE WHEN X.close_rate < 0 THEN Y.close + Y.diff ELSE Y.close - Y.diff END pre_close
                FROM (SELECT date, code, close_rate FROM market_ohlcv WHERE code = '{code}') X, 
                     (SELECT date, code, close_rate, close, diff FROM daily_price WHERE code = '{code}') Y
                WHERE Y.date = X.date AND Y.code = X.code AND Y.close_rate != X.close_rate
            ) B
            ON B.date = A.date AND B.code = A.code
            SET A.pre_close = B.pre_close,
                A.open_rate = ROUND((A.open-B.pre_close)/B.pre_close*100, 2),
                A.high_rate = ROUND((A.high-B.pre_close)/B.pre_close*100, 2),
                A.low_rate = ROUND((A.low-B.pre_close)/B.pre_close*100, 2),
                A.close_rate = ROUND((A.close-B.pre_close)/B.pre_close*100, 2),
                A.reset_market_ohlcv = 'Y'
            """
            curs.execute(sql2)
            self.conn.commit()

    def update_daily_price(self, code, pages_to_fetch):
        """KRX 상장법인의 주식 시세를 네이버로부터 읽어서 DB에 업데이트"""
        df = self.read_naver(code, pages_to_fetch)
        if df is not None:
            self.replace_into_db(df, 1, code)

    def execute_daily(self, code):
        self.update_daily_price(code, 100)

        tmnow = datetime.now()
        lastday = calendar.monthrange(tmnow.year, tmnow.month)[1]
        if tmnow.month == 12 and tmnow.day == lastday:
            tmnext = tmnow.replace(year=tmnow.year + 1, month=1, day=1, hour=17, minute=0, second=0)
        elif tmnow.day == lastday:
            tmnext = tmnow.replace(month=tmnow.month + 1, day=1, hour=17, minute=0, second=0)
        else:
            tmnext = tmnow.replace(day=tmnow.day + 1, hour=17, minute=0, second=0)
        tmdiff = tmnext - tmnow
        secs = tmdiff.seconds
        t = Timer(secs, self.execute_daily, [code])
        t.start()
        print("처리완료!! ")

if __name__ == '__main__':
    dbu = DBUpdater()
    dbu.execute_daily('336370')
