import pandas as pd
from bs4 import BeautifulSoup
import pymysql
import calendar
import json
import requests
from datetime import datetime
from threading import Timer
import time
from pykrx import stock

class DBUpdater:
    def __init__(self):
        """생성자: MariaDB 연결 및 종목코드 딕셔너리 생성"""
        self.conn = pymysql.connect(
            host='siriens.mycafe24.com', user='siriens', password='hosting1004!',
            db='siriens', charset='utf8'
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

    def get_ticker(self):
        kospi_tickers = stock.get_market_ticker_list(None)
        kosdaq_tickers = stock.get_market_ticker_list(None, "KOSDAQ")
        tickers = kospi_tickers + kosdaq_tickers
        return tickers

    def get_market(self, from_date, to_date, code):
        df = stock.get_market_ohlcv(from_date, to_date, code)
        df = df.rename(columns={'날짜': 'date', '시가': 'open', '고가': 'high', '저가': 'low',
                                '종가': 'close', '거래량': 'volume', '거래대금': 'amount', '등락률': 'close_rate'})
        return df

    def replace_into_ohlcv(self, df, code, idx):
        with self.conn.cursor() as curs:
            for r in df.itertuples():
                date = str(r.Index).replace('-', '')[0:8]
                sql = f"""
                INSERT IGNORE INTO market_ohlcv(code, date, open, high, low, close, volume, amount, close_rate)
                VALUES ('{code}', '{date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.volume}, {r.amount}, {r.close_rate})
                """
                print(f'({idx}) {sql}')
                curs.execute(sql)
            self.conn.commit()

    def update_market_ohlcv(self, codes, from_date, to_date):
        for idx in range(len(codes)):
            df = self.get_market(from_date, to_date, codes[idx])
            if df is None:
                continue
            self.replace_into_ohlcv(df, codes[idx], idx)
            time.sleep(idx % 2 + 1)
            if idx % 10 == 0:
                time.sleep(1)

    def pykrxMarket_execute(self, from_date, to_date):
        codes = self.get_ticker()
        print(len(codes))
        self.update_market_ohlcv(codes, from_date, to_date)

def datetime2string(dt, freq='d'):
    if freq.upper() == 'Y':
        return dt.strftime("%Y")
    elif freq.upper() == 'M':
        return dt.strftime("%Y%m")
    else:
        return dt.strftime("%Y%m%d")

def get_nearest_business_day_in_a_week(date: str = None, prev: bool = True) -> str:
    """인접한 영업일을 조회한다."""
    from pykrx.website.krx.market import get_index_ohlcv_by_date
    
    if date is None:
        curr = datetime.datetime.now()
    else:
        curr = datetime.datetime.strptime(date, "%Y%m%d")

    if prev:
        prev = curr - datetime.timedelta(days=7)
        curr_str = curr.strftime("%Y%m%d")
        prev_str = prev.strftime("%Y%m%d")
        df = get_index_ohlcv_by_date(prev_str, curr_str, "1001")
        print(f"Requesting data for date range: {prev_str} to {curr_str}")
        print(f"Returned DataFrame:\n{df}")
        if df.empty:
            print(f"Empty DataFrame returned for date range: {prev_str} to {curr_str}")
            return None
        return df.index[-1].strftime("%Y%m%d")
    else:
        next = curr + datetime.timedelta(days=7)
        next_str = next.strftime("%Y%m%d")
        curr_str = curr.strftime("%Y%m%d")
        df = get_index_ohlcv_by_date(curr_str, next_str, "1001")
        print(f"Requesting data for date range: {curr_str} to {next_str}")
        print(f"Returned DataFrame:\n{df}")
        if df.empty:
            print(f"Empty DataFrame returned for date range: {curr_str} to {next_str}")
            return None
        return df.index[0].strftime("%Y%m%d")

# 예외 처리 및 다른 날짜 범위 시도
try:
    dbu = DBUpdater()
    dbu.pykrxMarket_execute('20240705', '20240705')
except Exception as e:
    print(f"Error occurred: {e}")
