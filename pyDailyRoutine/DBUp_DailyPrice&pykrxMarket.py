import pandas as pd
import subprocess
from io import StringIO
from bs4 import BeautifulSoup
import pymysql, calendar, json
import requests
from datetime import datetime
from threading import Timer
import time
import numpy as np
from pykrx import stock
import logging
import traceback

# 로깅 설정
logging.basicConfig(filename='E:/Project/202410/www/pyDailyRoutine/dbupdater_log.txt', level=logging.DEBUG,
                    format='%(asctime)s - %(levelname)s - %(message)s', filemode='w')

# 로그 파일 핸들러를 직접 추가하여 즉시 플러시
for handler in logging.getLogger().handlers:
    handler.flush()

class DBUpdater:
    def __init__(self):
        """생성자: MariaDB 연결 및 종목코드 딕셔너리 생성"""
        try:
            self.conn = pymysql.connect(host='siriens.mycafe24.com', user='siriens', password='hosting1004!', db='siriens', charset='utf8')
            self.codes = dict()
            logging.info("DB 연결 성공")
        except Exception as e:
            logging.error(f"DB 연결 실패: {str(e)}")
            logging.error(traceback.format_exc())

    def __del__(self):
        """소멸자: MariaDB 연결 해제"""
        try:
            self.conn.close()
            logging.info("DB 연결 해제 성공")
        except Exception as e:
            logging.error(f"DB 연결 해제 실패: {str(e)}")
            logging.error(traceback.format_exc())

    # pykrxMarket --------------------------------------------------------------------------------------------------------------------------------------------------
    def get_ticker(self, date):
        try:
            kospi_tickers = stock.get_market_ticker_list(None)
            kosdaq_tickers = stock.get_market_ticker_list(None, "KOSDAQ")
            tickers = kospi_tickers + kosdaq_tickers
            logging.info("티커 목록 가져오기 성공")

            # 이미 데이터가 존재하는 코드를 조회
            with self.conn.cursor() as curs:
                sql = f"SELECT code FROM daily_pykrx WHERE date = '{date}'"
                curs.execute(sql)
                result = curs.fetchall()
                
                # 이미 반영된 코드 리스트를 문자열로 변환
                existing_codes = [row[0].decode('utf-8') if isinstance(row[0], bytes) else row[0] for row in result]

            # 이미 반영된 코드를 제외한 리스트를 반환
            tickers_to_process = [ticker for ticker in tickers if ticker not in existing_codes]

            return tickers_to_process
        except Exception as e:
            logging.error(f"티커 목록 가져오기 실패: {str(e)}")
            logging.error(traceback.format_exc())
            return []

    def get_market(self, from_date, to_date, code):
        try:
            df = stock.get_market_ohlcv(from_date, to_date, code)
            df = df.rename(columns={'날짜':'date', '시가':'open', '고가':'high','저가':'low','종가':'close','거래량':'volume','거래대금':'amount','등락률':'close_rate'})
            logging.info(f"{code} 종목 시장 데이터 가져오기 성공")
            return df
        except Exception as e:
            logging.error(f"{code} 종목 시장 데이터 가져오기 실패: {str(e)}")
            logging.error(traceback.format_exc())
            return None

    def replace_into_ohlcv(self, df, code, idx):
        try:
            with self.conn.cursor() as curs:
                for r in df.itertuples():
                    date = r.Index.strftime('%Y-%m-%d')  # Convert to DATE format
                    sql = f"INSERT IGNORE INTO daily_pykrx(code,date,open,high,low,close,volume,amount,close_rate)  "\
                        f"VALUES ('{code}', '{date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.volume}, {r.amount}, {r.close_rate} )"
                    logging.debug(f'({str(idx)}) {sql}')
                    curs.execute(sql)
                self.conn.commit()
                logging.info(f"{code} 종목 OHLCV 데이터 저장 성공")
        except Exception as e:
            logging.error(f"{code} 종목 OHLCV 데이터 저장 실패: {str(e)}")
            logging.error(traceback.format_exc())

    def update_daily_price_from_pykrx(self, codes, from_date, to_date):
        for idx in range(len(codes)):
            try:
                df = self.get_market(from_date, to_date, codes[idx])
                if df is None:
                    continue
                self.replace_into_ohlcv(df, codes[idx], idx)
                time.sleep(idx % 2 + 1)

                if idx % 10 == 0:
                    time.sleep(1)

            except Exception as e:
                logging.error(f"{codes[idx]} 종목 데이터 처리 중 오류 발생: {str(e)}")
                logging.error(traceback.format_exc())

        # 거래대금 반영 및 기타 쿼리 실행
        try:
            with self.conn.cursor() as curs:
                sql1 = f"UPDATE daily_price A INNER JOIN daily_pykrx B ON B.code = A.code AND B.date = A.date SET A.amount = B.amount WHERE A.date BETWEEN '{from_date}' AND '{to_date}'"
                logging.debug(f'{sql1}')
                curs.execute(sql1)
                sql2 = f"UPDATE calendar SET proc_yn = 'Y' WHERE date BETWEEN '{from_date}' AND '{to_date}'"
                logging.debug(f'{sql2}')
                curs.execute(sql2)
                self.conn.commit()
                logging.info("거래대금 반영 및 proc_yn 업데이트 성공")
        except Exception as e:
            logging.error(f"거래대금 반영 실패: {str(e)}")
            logging.error(traceback.format_exc())

    # naverPage --------------------------------------------------------------------------------------------------------------------------------------------------
    def read_krx_code(self):
        """KRX로부터 상장기업 목록 파일을 읽어와서 데이터프레임으로 반환"""
        try:
            url = 'http://kind.krx.co.kr/corpgeneral/corpList.do?method=download&searchType=13'
            krx = pd.read_html(requests.get(url,
                        headers={'referer': 'http://kind.krx.co.kr/',
                                  'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text)[0]
            krx = krx[['종목코드', '회사명']]
            krx = krx.rename(columns={'종목코드': 'code', '회사명': 'company'})
            krx.code = krx.code.map('{:06d}'.format)
            logging.info("KRX 종목코드 읽기 성공")
            return krx
        except Exception as e:
            logging.error(f"KRX 종목코드 읽기 실패: {str(e)}")
            logging.error(traceback.format_exc())
            return None

    def update_comp_info(self):
        """종목코드를 company_info 테이블에 업데이트 한 후 딕셔너리에 저장"""
        today = datetime.today().strftime('%Y-%m-%d')

        try:
            sql = f"SELECT * FROM company_info WHERE last_update = '{today}' "
            df = pd.read_sql(sql, self.conn)
            for idx in range(len(df)):
                self.codes[df['code'].values[idx].decode('utf-8')] = df['company'].values[idx].decode('utf-8')

            with self.conn.cursor() as curs:
                sql = "SELECT max(last_update) FROM company_info"
                curs.execute(sql)
                rs = curs.fetchone()
                if rs[0] == None or rs[0].strftime('%Y-%m-%d') < today:
                    krx = self.read_krx_code()
                    for idx in range(len(krx)):
                        code = krx.code.values[idx]
                        company = krx.company.values[idx]
                        sql = f"REPLACE INTO company_info (code, company, last_update) VALUES ('{code}', '{company}', '{today}')"
                        curs.execute(sql)
                        self.codes[code] = company
                        tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
                        # logging.info(f"[{tmnow}] #{idx+1:04d} REPLACE INTO company_info VALUES ({code}, {company}, {today})")
                    self.conn.commit()
                    logging.info("종목코드 업데이트 성공")
        except Exception as e:
            logging.error(f"종목코드 업데이트 실패: {str(e)}")
            logging.error(traceback.format_exc())

    def read_naver(self, code, company, pages_to_fetch):
        """네이버에서 주식 시세를 읽어서 데이터프레임으로 반환"""
        try:
            url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"
            logging.info(f"네이버에서 {company} ({code}) 주식 시세 가져오기 시도 중...")
            df = pd.DataFrame()

            response = requests.get(url, headers={
                'referer': 'https://finance.naver.com/',
                'User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
            })
            html_content = response.text
            df = pd.concat([df, pd.read_html(StringIO(html_content))[0]])
            df = df.rename(columns={'날짜':'date','종가':'close','전일비':'diff','시가':'open','고가':'high','저가':'low','거래량':'volume'})
            df['diff'] = df['diff'].replace('[^0-9]', '', regex=True).fillna(0).astype(int)
            df['date'] = df['date'].replace('.', '-')
            df.dropna(inplace=True)
            df[['close', 'open', 'high', 'low', 'volume']] = df[['close', 'open', 'high', 'low', 'volume']].astype(int)
            df = df[['date', 'open', 'high', 'low', 'close', 'diff', 'volume']]
            logging.info(f"{company} ({code}) 주식 시세 가져오기 성공")
            return df
        except Exception as e:
            logging.error(f"{company} ({code}) 주식 시세 가져오기 실패: {str(e)}")
            logging.error(traceback.format_exc())
            return None

    def replace_into_db(self, df, num, code, company):
        """네이버에서 읽어온 주식 시세를 DB에 REPLACE"""
        try:
            with self.conn.cursor() as curs:
                for r in df.itertuples():
                    date = str(r.date).replace('.','')
                    sql = f"INSERT IGNORE INTO daily_price(code,date,open,high,low,close,diff,volume)  VALUES ('{code}', "\
                        f"'{date}', {r.open}, {r.high}, {r.low}, {r.close}, "\
                        f"{r.diff}, {r.volume})"
                    curs.execute(sql)
                self.conn.commit()
                logging.info(f"[{datetime.now().strftime('%Y-%m-%d %H:%M')}] #{num+1:04d} {company} ({code}) : {len(df)} rows REPLACE INTO daily_price [OK]")
        except Exception as e:
            logging.error(f"{company} ({code}) 데이터베이스 REPLACE 실패: {str(e)}")
            logging.error(traceback.format_exc())

    def update_daily_price(self, pages_to_fetch):
        """KRX 상장법인의 주식 시세를 네이버로부터 읽어서 DB에 업데이트"""
        for idx, code in enumerate(self.codes):
            df = self.read_naver(code, self.codes[code], pages_to_fetch)
            if df is None:
                continue
            self.replace_into_db(df, idx, code, self.codes[code])

    def update_pre_price(self):
        """일별 주식 데이터 업데이트"""
        try:
            sql = f"SELECT date as proc_date FROM calendar A WHERE proc_yn = 'N' AND date <= (SELECT CURDATE()) AND EXISTS (SELECT * FROM daily_price B WHERE B.date = A.date) ORDER BY date"
            logging.info(f"{sql}")
            df = pd.read_sql(sql, self.conn)
            with self.conn.cursor() as curs:
                for idx in range(len(df)):
                    proc_date = df['proc_date'].iloc[0]
                    proc_date = str(proc_date)
                    logging.info(f"처리 날짜: {proc_date}")

                    sql0= f"insert into log_daily_price values (now(), '{proc_date}')"
                    logging.debug(f'{sql0}')
                    curs.execute(sql0)

                    sql1 = f"UPDATE daily_price A INNER JOIN (SELECT X.code, CASE WHEN Y.close IS NULL THEN X.open ELSE Y.close END pre_close "\
                           f" FROM daily_price X LEFT OUTER JOIN daily_price Y "\
                           f" ON Y.code = X.code AND Y.date = (SELECT MAX(date) FROM calendar WHERE date < '{proc_date}') "\
                           f" WHERE X.date = '{proc_date}') B ON B.code = A.code "\
                           f" SET A.pre_close = B.pre_close, A.open_rate = ROUND((A.open-B.pre_close)/B.pre_close*100, 2), "\
                           f" A.high_rate = ROUND((A.high-B.pre_close)/B.pre_close*100, 2), "\
                           f" A.low_rate = ROUND((A.low-B.pre_close)/B.pre_close*100, 2), "\
                           f" A.close_rate = ROUND((A.close-B.pre_close)/B.pre_close*100, 2) "\
                           f" WHERE A.date = '{proc_date}'"
                    logging.debug(f'{sql1}')
                    curs.execute(sql1)

                    self.conn.commit()
                    logging.info(f"날짜 {proc_date}의 주식 데이터 업데이트 성공")
            self.pykrxMarket_execute(df)
        except Exception as e:
            logging.error(f"주식 데이터 업데이트 실패: {str(e)}")
            logging.error(traceback.format_exc())

    def pykrxMarket_execute(self, df):
        try:
            from_date = df['proc_date'].iloc[0]
            from_date = str(from_date)
            to_date = from_date

            codes = self.get_ticker(from_date)
            logging.info(f"PyKRX 마켓 데이터 업데이트 시작 - 총 {len(codes)} 개 종목")

            self.update_daily_price_from_pykrx(codes, from_date, to_date)
        except Exception as e:
            logging.error(f"PyKRX 마켓 데이터 실행 중 오류 발생: {str(e)}")
            logging.error(traceback.format_exc())

    def execute_daily(self):
        """실행 즉시 및 매일 오후 다섯시에 daily_price 테이블 업데이트"""
        try:
            self.update_comp_info()

            # try:
            #     with open('config.json', 'r') as in_file:
            #         config = json.load(in_file)
            #         pages_to_fetch = config['pages_to_fetch']
            # except FileNotFoundError:
            #     with open('config.json', 'w') as out_file:
            #         pages_to_fetch = 100
            #         config = {'pages_to_fetch': 1}
            #         json.dump(config, out_file)

            # self.update_daily_price(pages_to_fetch)
            self.update_daily_price(1)

            self.update_pre_price()
        except Exception as e:
            logging.error(f"일일 업데이트 실패: {str(e)}")
            logging.error(traceback.format_exc())

if __name__ == '__main__':
    
    # Step 1: Run DBUp_MarketIndex.py first
    try:
        subprocess.run(["C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe", "E:/Project/202410/www/PyDailyRoutine/DBUp_MarketIndex.py"])
        logging.info("DBUp_MarketIndex.py 실행 완료")
    except Exception as e:
        logging.error(f"DBUp_MarketIndex.py 실행 중 오류 발생: {str(e)}")
        logging.error(traceback.format_exc())

    # Step 2: Initialize DBUpdater and execute its daily routine
    try:
        dbu = DBUpdater()
        dbu.execute_daily()
    except Exception as e:
        logging.error(f"DBUpdater 실행 중 오류 발생: {str(e)}")
        logging.error(traceback.format_exc())

    # Step 3: After DBUpdater has completed, run Update_MarketIssueStocks.py
    try:
        logging.info("Running Update_MarketIssueStocks.py ...")
        subprocess.run(["C:/Users/elf96/AppData/Local/Programs/Python/Python39/python.exe", "E:/Project/202410/www/PyDailyRoutine/Update_MarketIssueStocks.py"])
        logging.info("Update_MarketIssueStocks.py 실행 완료")
    except Exception as e:
        logging.error(f"Update_MarketIssueStocks.py 실행 중 오류 발생: {str(e)}")
        logging.error(traceback.format_exc())