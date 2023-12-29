
import pandas as pd
from bs4 import BeautifulSoup
import pymysql, calendar, json
import requests
from datetime import datetime
from threading import Timer
import time
import pandas as pd
import numpy as np
from pykrx import stock

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


	# pykrxMarket --------------------------------------------------------------------------------------------------------------------------------------------------
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

	def replace_into_ohlcv(self, df, code, idx):
		with self.conn.cursor() as curs:
			for r in df.itertuples():
				# print(type(r))
				date = str(r.Index).replace('-','')[0:8]
				sql = f"INSERT IGNORE INTO market_ohlcv(code,date,open,high,low,close,volume,amount,close_rate)  "\
					f"VALUES ('{code}', '{date}', {r.open}, {r.high}, {r.low}, {r.close}, {r.volume}, {r.amount}, {r.close_rate} )"
					# f"{r.volume}, round({r.amount}/100000000,0))"
					
				print('('+str(idx) + ') ' + sql)
				curs.execute(sql)
			self.conn.commit()

	def update_market_ohlcv(self, codes, from_date, to_date):
		for idx in range(len(codes)):
			df = self.get_market(from_date, to_date, codes[idx])
			if df is None:
				continue
			self.replace_into_ohlcv(df, codes[idx], idx)

			time.sleep(idx%2+1)

			if idx%10 == 0:
				time.sleep(1)

		with self.conn.cursor() as curs:
			# 거래대금 반영
			sql1=  f"UPDATE daily_price A"\
					f" INNER JOIN market_ohlcv B "\
					f"	 ON B.code = A.code "\
					f"   AND B.date = A.date "\
					f"   SET A.amount  = B.amount "\
					f" WHERE A.date BETWEEN {from_date} AND {to_date}"
			curs.execute(sql1)
			self.conn.commit()

			# daily_price VS market_ohlcv 종가등락률 다른 경우 처리 / 속도 오래 걸리는 것 같아 처리방안 고려 중
			sql2=  f"UPDATE daily_price A"\
					f"INNER JOIN (SELECT X.date, X.code, X.close_rate, Y.close, CASE WHEN X.close_rate < 0 THEN Y.close + Y.diff ELSE Y.close - Y.diff END pre_close "\
					f" 				FROM (SELECT date, code, close_rate FROM market_ohlcv WHERE date BETWEEN {from_date} AND {to_date} ) X,"\
					f" 					 (SELECT date, code, close_rate, close, diff FROM daily_price WHERE date BETWEEN {from_date} AND {to_date} ) Y "\
					f"	 			WHERE Y.date = X.date AND Y.code = X.code AND Y.close_rate != X.close_rate ) B "\
					f"	 ON B.date = A.date "\
					f"	AND B.code = A.code "\
					f"	SET A.pre_close  = B.pre_close "\
					f"	  , A.open_rate  = round((A.open-B.pre_close)/B.pre_close*100,2) "\
					f"	  , A.high_rate  = round((A.high-B.pre_close)/B.pre_close*100,2) "\
					f"	  , A.low_rate   = round((A.low-B.pre_close)/B.pre_close*100,2) "\
					f"	  , A.close_rate = round((A.close-B.pre_close)/B.pre_close*100,2) "

			# curs.execute(sql2)
			# self.conn.commit()

	def pykrxMarket_execute(self, from_date, to_date):
		codes = self.get_ticker()
		# print(codes)
		print(len(codes))

		self.update_market_ohlcv(codes, from_date, to_date)

	# naverPage --------------------------------------------------------------------------------------------------------------------------------------------------
	def read_krx_code(self):
		"""KRX로부터 상장기업 목록 파일을 읽어와서 데이터프레임으로 반환"""
		url = 'http://kind.krx.co.kr/corpgeneral/corpList.do?method='\
			'download&searchType=13'
		krx = pd.read_html(url, header=0)[0]
		krx = krx[['종목코드', '회사명']]
		krx = krx.rename(columns={'종목코드': 'code', '회사명': 'company'})
		krx.code = krx.code.map('{:06d}'.format)
		return krx

	def update_comp_info(self):
		today = datetime.today().strftime('%Y-%m-%d')

		"""종목코드를 company_info 테이블에 업데이트 한 후 딕셔너리에 저장"""
		sql = f"SELECT * FROM company_info WHERE last_update = '{today}' "

		df = pd.read_sql(sql, self.conn)
		for idx in range(len(df)):
			self.codes[df['code'].values[idx]] = df['company'].values[idx]

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
					print(f"[{tmnow}] #{idx+1:04d} REPLACE INTO company_info VALUES ({code}, {company}, {today})")
				self.conn.commit()
				print('') 

	def read_naver(self, code, company, pages_to_fetch):
		"""네이버에서 주식 시세를 읽어서 데이터프레임으로 반환"""
		try:
			# url = f"http://finance.naver.com/item/sise_day.nhn?code={code}" #URL 막힘 2023.09.01 #URL 변경 및 Header에 referer 추가
			url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"
			print(url)
			html = BeautifulSoup(requests.get(url,
				headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text, "lxml")
			
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
						headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text)[0])
					tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
					print('[{}] {} ({}) pages are downloading...'.
						format(tmnow, company, code), end="\r")
			else :
				s = str(pgrr.a["href"]).split('=')
				lastpage = s[-1]
				df = pd.DataFrame()
				pages = min(int(lastpage), pages_to_fetch)
				for page in range(1, pages + 1):
					pg_url = '{}&page={}'.format(url, page)
					df = df.append(pd.read_html(requests.get(pg_url,
						headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text)[0])
					tmnow = datetime.now().strftime('%Y-%m-%d %H:%M')
					print('[{}] {} ({}) : {:04d}/{:04d} pages are downloading...'.
						format(tmnow, company, code, page, pages), end="\r")

			df = df.rename(columns={'날짜':'date','종가':'close','전일비':'diff'
				,'시가':'open','고가':'high','저가':'low','거래량':'volume'})
			df['date'] = df['date'].replace('.', '-')
			print(df)
			df = df.dropna()
			df[['close', 'diff', 'open', 'high', 'low', 'volume']] = df[['close',
				'diff', 'open', 'high', 'low', 'volume']].astype(int)
			df = df[['date', 'open', 'high', 'low', 'close', 'diff', 'volume']]
		except Exception as e:
			print('Exception occured :', str(e))
			return None
		return df

	def replace_into_db(self, df, num, code, company):
		"""네이버에서 읽어온 주식 시세를 DB에 REPLACE"""
		with self.conn.cursor() as curs:
			for r in df.itertuples():
				date = str(r.date).replace('.','')
				sql = f"INSERT IGNORE INTO daily_price(code,date,open,high,low,close,diff,volume)  VALUES ('{code}', "\
					f"'{date}', {r.open}, {r.high}, {r.low}, {r.close}, "\
					f"{r.diff}, {r.volume})"
				curs.execute(sql)
				print(sql)
			self.conn.commit()
			print('[{}] #{:04d} {} ({}) : {} rows > REPLACE INTO daily_price [OK]'.format(datetime.now().strftime('%Y-%m-%d %H:%M'), num+1, company, code, len(df)))
			

	def update_daily_price(self, pages_to_fetch):
		"""KRX 상장법인의 주식 시세를 네이버로부터 읽어서 DB에 업데이트"""
		for idx, code in enumerate(self.codes):
			df = self.read_naver(code, self.codes[code], pages_to_fetch)
			print(df)
			if df is None:
				continue
			self.replace_into_db(df, idx, code, self.codes[code])

	def update_pre_price(self):
		sql = "SELECT date proc_date FROM calendar A WHERE proc_yn = 'N' AND date <= (select DATE_FORMAT(now(), '%Y%m%d')) AND EXISTS (SELECT * FROM daily_price B WHERE B.date = A.date) order by date"
		print(sql)
		df = pd.read_sql(sql, self.conn)
		with self.conn.cursor() as curs:
			for idx in range(len(df)):

				proc_date = df['proc_date'].values[idx].decode('utf-8')
				# print(type(proc_date))
				print(proc_date)

				sql0= f"insert into log_daily_price values (now(), {proc_date})"
				curs.execute(sql0)

				# 전일종가를 찾아 등락률 구하기
				# sql1=  f"UPDATE daily_price A"\
				# 	   f" INNER JOIN (SELECT Z.code, Z.close "\
				# 	   f"				  FROM (SELECT code, close FROM daily_price WHERE date = (SELECT MAX(date) pre_date FROM calendar WHERE date < {proc_date})) Z ) B "\
				# 	   f"			ON B.code = A.code "\
				# 	   f"   SET pre_close  = B.close "\
				# 	   f"	  , open_rate  = round((A.open-B.close)/B.close*100,2) "\
				# 	   f"	  , high_rate  = round((A.high-B.close)/B.close*100,2) "\
				# 	   f"	  , low_rate   = round((A.low-B.close)/B.close*100,2) "\
				# 	   f"	  , close_rate = round((A.close-B.close)/B.close*100,2) "\
				# 	   f" WHERE A.date = {proc_date} "

				# 전일종가가 없을 경우 (신규주), 당일 시가를 반영하는 로직 추가
				sql1=  f"UPDATE daily_price A"\
					   f" INNER JOIN (SELECT X.code, CASE WHEN Y.close is null THEN X.open ELSE Y.close END pre_close  "\
					   f"				FROM daily_price X "\
					   f"				LEFT OUTER JOIN daily_price Y"\
					   f"				  ON Y.code = X.code "\
					   f"				 AND Y.date = (SELECT MAX(date) FROM calendar WHERE date < {proc_date}) "\
					   f"			   WHERE X.date = {proc_date}) B "\
					   f"	 ON B.code = A.code "\
					   f"   SET A.pre_close  = B.pre_close "\
					   f"	  , A.open_rate  = round((A.open-B.pre_close)/B.pre_close*100,2) "\
					   f"	  , A.high_rate  = round((A.high-B.pre_close)/B.pre_close*100,2) "\
					   f"	  , A.low_rate   = round((A.low-B.pre_close)/B.pre_close*100,2) "\
					   f"	  , A.close_rate = round((A.close-B.pre_close)/B.pre_close*100,2) "\
					   f" WHERE A.date = {proc_date} "
				curs.execute(sql1)

				sql2 = f"UPDATE calendar  SET proc_yn  = 'Y'  WHERE date = {proc_date}"
				curs.execute(sql2)

				self.conn.commit()
			self.pykrxMarket_execute(df)
	
	def execute_daily(self):
		"""실행 즉시 및 매일 오후 다섯시에 daily_price 테이블 업데이트"""
		self.update_comp_info()

		# try:
		# 	with open('config.json', 'r') as in_file:
		# 		config = json.load(in_file)
		# 		pages_to_fetch = config['pages_to_fetch']
		# except FileNotFoundError:
		# 	with open('config.json', 'w') as out_file:
		# 		pages_to_fetch = 100
		# 		config = {'pages_to_fetch': 1}
		# 		json.dump(config, out_file)

		# self.update_daily_price(pages_to_fetch)

		self.update_daily_price(1)

		self.update_pre_price()

		tmnow = datetime.now()
		lastday = calendar.monthrange(tmnow.year, tmnow.month)[1]
		if tmnow.month == 12 and tmnow.day == lastday:
			tmnext = tmnow.replace(year=tmnow.year+1, month=1, day=1,
				hour=17, minute=0, second=0)
		elif tmnow.day == lastday:
			tmnext = tmnow.replace(month=tmnow.month+1, day=1, hour=17,
				minute=0, second=0)
		else:
			tmnext = tmnow.replace(day=tmnow.day+1, hour=17, minute=0,
				second=0)
		tmdiff = tmnext - tmnow
		secs = tmdiff.seconds
		t = Timer(secs, self.execute_daily)
		print("Waiting for next update ({}) ... ".format(tmnext.strftime
			('%Y-%m-%d %H:%M')))
		# t.start()

if __name__ == '__main__':
	dbu = DBUpdater()
	# dbu.execute_daily()
	dbu.pykrxMarket_execute('20231220','20231221')