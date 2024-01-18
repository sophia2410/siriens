import pandas as pd
from bs4 import BeautifulSoup
from urllib.request import urlopen
from datetime import datetime
from threading import Timer
import sys
sys.path.append("E:/Project/202410/www/boot/common/db")
from DBConnect import DBConnect as db

class DBUpdater :
	def __init__(self) :
		"""생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
		db.MariaDBConnct(self)
		self.curs = self.conn.cursor()

	def __del__(self) :
		"""소멸자:MariaDB 연결 해제"""
		db.MariaDBClose(self)

	def read_xlsx(self):
		"""모차십 종목 엑셀 파일을 읽어와서 데이터프레임으로 반환"""
		# 모차십일자 글로벌 변수 선언. 쿼리문 적용
		global mochaten_date
		global trade_date

		sql = "SELECT min(date) date FROM calendar a WHERE date > (select DATE_FORMAT(now(), '%Y%m%d'))"
		df = pd.read_sql(sql, self.conn)
		mochaten_date = df['date'][0].decode('utf-8')
		
		sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
		df = pd.read_sql(sql, self.conn)
		trade_date = df['date'][0].decode('utf-8')
		
		# mochaten_date = '20240116'
		# trade_date = '20240115'

		pathExl = 'E:/Project/202410/data/_Mochaten/' +  mochaten_date + '.xlsx'
		rdxls = pd.read_excel(pathExl, engine = 'openpyxl')
		rdxls = rdxls.rename(columns={'차트구분':'cha_fg', '종목코드':'code', '종목명':'name', '시가총액':'market_cap', '등락률':'close_rate', '거래량':'volume', '거래대금':'tot_trade_amt', '외국인순매수금액':'f_trade_amt', '기관순매수금액':'o_trade_amt', '프로그램순매수금액':'p_trade_amt', '영업이익률(Y)':'op_ratio', '부채비율(Y)':'lb_ratio', '유통비율':'dt_ratio'})
		rdxls.code = rdxls.code.map('{:06d}'.format)
		return rdxls

	def update_info(self):
		rdxls = self.read_xlsx()
		# rdxls.fillna("0") # nan 값으로 인식되지 않아 채워지지 않음
		today = datetime.today().strftime('%Y-%m-%d')

		sql = f'''DELETE FROM mochaten WHERE mochaten_date = '{mochaten_date}' '''
		self.curs.execute(sql)

		for idx in range(len(rdxls)):
			cha_fg			= rdxls.cha_fg.values[idx]
			code			= rdxls.code.values[idx]
			name			= rdxls.name.values[idx]
			market_cap		= rdxls.market_cap.values[idx]
			close_rate		= rdxls.close_rate.values[idx]
			volume			= rdxls.volume.values[idx]
			tot_trade_amt	= rdxls.tot_trade_amt.values[idx]

			# 거래대금이 NaN 경우 처리
			if pd.isna(rdxls.f_trade_amt.values[idx]) :
				f_trade_amt = 0
			else :
				f_trade_amt		= int(rdxls.f_trade_amt.values[idx])

			if pd.isna(rdxls.o_trade_amt.values[idx]) :
				o_trade_amt = 0
			else :
				o_trade_amt		= int(rdxls.o_trade_amt.values[idx])

			if pd.isna(rdxls.p_trade_amt.values[idx]) :
				p_trade_amt = 0
			else :
				p_trade_amt		= int(rdxls.p_trade_amt.values[idx])

			# o_trade_amt		= int(rdxls.o_trade_amt.values[idx])
			# p_trade_amt		= int(rdxls.p_trade_amt.values[idx])
			op_ratio		= rdxls.op_ratio.values[idx]
			lb_ratio		= rdxls.lb_ratio.values[idx]
			dt_ratio		= rdxls.dt_ratio.values[idx]

			sql = f'''REPLACE INTO mochaten
		              ( mochaten_date
					  ,	cha_fg
					  , code
					  , name
					  , market_cap
					  , close_rate
					  , volume
					  , tot_trade_amt
					  , f_trade_amt
					  , o_trade_amt
					  , p_trade_amt
					  , op_ratio
					  , lb_ratio
					  , dt_ratio
					  , trade_date
					  , create_dtime
					  )
					  VALUES
					  ( '{mochaten_date}'
					  ,	'{cha_fg}'
					  , '{code}'
					  , '{name}'
					  , '{market_cap}'
					  , '{close_rate}'
					  , '{volume}'
					  , '{tot_trade_amt}'
					  , '{f_trade_amt}'
					  , '{o_trade_amt}'
					  , '{p_trade_amt}'
					  , '{op_ratio}'
					  , '{lb_ratio}'
					  , '{dt_ratio}'
					  , '{trade_date}'
					  , now())'''

			self.curs.execute(sql)
		self.conn.commit()

	def exe_info(self) :
		self.update_info()

if __name__ == '__main__':
	dbu = DBUpdater()
	dbu.exe_info()
