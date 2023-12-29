import pandas as pd
import numpy as np
from pykrx import stock
import time
import sys
sys.path.append("E:/Project/202410/www/source/boot/common/db")
from DBConnect import DBConnect as db
# pylint: disable-all
# flake8: noqa

class DBUpdater :
	def __init__(self) :
		"""생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
		db.MariaDBConnct(self)
		self.curs = self.conn.cursor()

	def __del__(self) :
		"""소멸자:MariaDB 연결 해제"""
		db.MariaDBClose(self)

	def get_ticker(self):
		kospi_tickers = stock.get_market_ticker_list(None)
		kosdaq_tickers = stock.get_market_ticker_list(None, "KOSDAQ")
		tickers = kospi_tickers + kosdaq_tickers

		return tickers

	def get_market(self, from_date, to_date, code):
		df = stock.get_market_ohlcv(from_date, to_date, code)
		# print(df.columns)
		# print(df.index)
		df = df.rename(columns={'날짜':'date', '시가':'open', '고가':'high','저가':'low','종가':'close','거래량':'volume','거래대금':'amount','등락률':'close_rate'})
		return df

	def replace_into_db(self, df, code, idx):
		with self.conn.cursor() as curs:
			for r in df.itertuples():
				# print(type(r))
				date = str(r.Index).replace('-','')[0:8]
				sql = f"INSERT IGNORE INTO market_ohlcv(code,date,open,high,low,close,volume,amount,close_rate) "\
					f" VALUES ('{code}', '{date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.volume}, {r.amount}, {r.close_rate} )"
					# f"{r.volume}, round({r.amount}/100000000,0))"

				print('('+str(idx) + ') ' + sql)
				curs.execute(sql)
			self.conn.commit()

	def update_market_ohlcv(self, codes, from_date, to_date):
		# with self.conn.cursor() as curs:
			# sql = f"DELETE FROM market_ohlcv WHERE DATE between '{from_date}' AND '{to_date}'"
			# curs.execute(sql)

		for idx in range(len(codes)):
			df = self.get_market(from_date, to_date, codes[idx].decode('utf-8'))
			# print(df);
			if df is None:
				continue
			self.replace_into_db(df, codes[idx].decode('utf-8'), idx)

			time.sleep(idx%2+1)

			if idx%10 == 0:
				time.sleep(1)

	def execute_daily(self):
		# codes = self.get_ticker()
		# print(len(codes))

		from_date = '20230522'
		to_date   = '20230525'

		with self.conn.cursor() as curs:
			# sql = f"SELECT code FROM market_ohlcv A WHERE A.DATE = '20221229' AND NOT EXISTS (SELECT * FROM market_ohlcv B WHERE B.DATE = '{from_date}' AND B.code = A.code)"
			sql = f"SELECT code FROM (select code from (select code from market_ohlcv WHERE DATE = '20220602' union all select code from market_ohlcv WHERE DATE = '20221229' ) z group by code) A "\
				  f" where NOT EXISTS (SELECT * FROM market_ohlcv B WHERE B.DATE = '{from_date}' AND B.code = A.code) "
			
			curs.execute(sql)
			df = pd.read_sql(sql, self.conn)
			print(df)
			print(df.columns)

			codes = df['code'].to_list() # Dataframe 형식의 데이터를 List 형식으로 변경
			# print(codes)

		self.update_market_ohlcv(codes, from_date, to_date)

if __name__ == '__main__':
	dbu = DBUpdater()
	dbu.execute_daily()