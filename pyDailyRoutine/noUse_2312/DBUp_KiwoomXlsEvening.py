import openpyxl
import pandas as pd
from bs4 import BeautifulSoup
from urllib.request import urlopen
from datetime import datetime
from threading import Timer
import sys
sys.path.append("E:/Project/202410/www/source/boot/common/db")
from DBConnect import DBConnect as db

class DBUpdater :
	def __init__(self) :
		"""생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
		db.MariaDBConnct(self)
		self.curs = self.conn.cursor()

	def __del__(self) :
		"""소멸자:MariaDB 연결 해제"""
		db.MariaDBClose(self)

	def replaceNaN(self,st):
		if pd.isna(st) :
			return ''
		else:
			return st

	def read_xlsx(self):
		sql =  "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
		df = pd.read_sql(sql, self.conn)
		date = df['date'][0].decode('utf-8')

		"""엑셀로 작성한 이브닝 파일 테이블 등록"""
		# pathExl = 'E:/Project/202410/data/_SophiaEvening/' +  date + '.xlsx'
		pathExl = 'E:/Project/202410/data/_SophiaEvening/한동훈.xlsx'
		sheetList = []

		# openpyxl를 이용하여 시트명 가져오기
		wb = openpyxl.load_workbook(pathExl)
		for i in wb.sheetnames:
			sheetList.append(i)

		# pandas를 이용하여 각 시트별 데이터 가져오기
		rdxls = pd.DataFrame()
		xlsx = pd.ExcelFile(pathExl)
		for j in sheetList:
			df = pd.read_excel(xlsx, j, dtype={'종목코드': str})
			print('%s Sheet의 데이타 입니다.' %j)
			print(df)
			print('*' * 50)
			rdxls = rdxls.append(df)

		rdxls = rdxls.rename(columns={'일자':'report_date', '종목코드':'code', '종목명':'name', '메모':'memo'})
		return rdxls

	def update_info(self):
		rdxls = self.read_xlsx()
		file=open("signal_evening.sql", "w", encoding="utf-8")

		for idx in range(len(rdxls)):
			report_date	= self.replaceNaN(rdxls.report_date.values[idx])
			code	= self.replaceNaN(rdxls.code.values[idx])
			name	= self.replaceNaN(rdxls.name.values[idx])
			memo	= self.replaceNaN(rdxls.memo.values[idx])

			if memo != '' :
				if memo[0] == '"':	memo	= memo[1:]
				if memo[0] == "'":	memo	= memo[1:]
				memo = memo.replace('_x000D_','')
				memo = memo.split('\n')[0]
				memo = memo.replace("'", "\\'").replace('"','\\"')
				print(memo)

			sql = f'''REPLACE INTO rawdata_evening
		              ( report_date
					  ,	code
					  ,	name
					  ,	memo
					  ,	keyword
					  ,	create_id
					  ,	create_dtime
					  )
					  VALUES
					  ( '{report_date}'
					  ,	'{code}'
					  ,	'{name}'
					  ,	'{memo}'
					  , SUBSTRING(REGEXP_SUBSTR('{memo}', '\\\[(.*?)\\\]'), 2, CHAR_LENGTH(REGEXP_SUBSTR('{memo}', '\\\[(.*?)\\\]')) - 2)
					  , 'python'
					  , now());'''
			print(sql)
			# file.write(sql)
			self.curs.execute(sql)
		self.conn.commit()
		file.close()

	def exe_info(self) :
			self.update_info()

if __name__ == '__main__':
	dbu = DBUpdater()
	dbu.exe_info()