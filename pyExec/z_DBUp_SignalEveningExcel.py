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


	def read_xlsx(self):
		"""카페 시그널이브닝 엑셀 파일을 읽어와서 데이터프레임으로 반환"""
		pathExl = 'E:/Project/202410/data/_Mochaten/siricafe_evening_db.xlsx'
		sheetList = []

		# openpyxl를 이용하여 시트명 가져오기
		wb = openpyxl.load_workbook(pathExl)
		for i in wb.sheetnames:
			sheetList.append(i)

		# pandas를 이용하여 각 시트별 데이터 가져오기
		rdxls = pd.DataFrame()
		xlsx = pd.ExcelFile(pathExl)
		for j in sheetList:
			df = pd.read_excel(xlsx, j)
			# print('%s Sheet의 데이타 입니다.' %j)
			# print(df)
			# print('*' * 50)
			rdxls = rdxls.append(df)

		rdxls = rdxls.rename(columns={'제목':'title', '링크':'link', '관련주':'stock','테마':'theme','내용':'content','일자':'evening_date'})
		return rdxls

	def update_info(self):
		rdxls = self.read_xlsx()

		file=open("signal_evening.sql", "w", encoding="utf-8")

		for idx in range(len(rdxls)):
			title	= rdxls.title.values[idx].replace("'", "\\'").replace('"','\\"')
			link	= rdxls.link.values[idx].replace("'", "\\'").replace('"','\\"')
			stock	= rdxls.stock.values[idx].replace("'", "\\'").replace('"','\\"')
			theme	= rdxls.theme.values[idx].replace("'", "\\'").replace('"','\\"')
			evening_date	= rdxls.evening_date.values[idx]
			
			if pd.isna(rdxls.content.values[idx]) :
				content	= ""
			else :
				content	= rdxls.content.values[idx].replace("'", "\\'").replace('"','\\"')

			sql = f'''REPLACE INTO cafe_signal_evening
		              ( title
					  ,	link
					  , stock
					  , theme
					  , content
					  , evening_date
					  )
		              VALUES
		              ( '{title}'
					  ,	'{link}'
					  , '{stock}'
					  , '{theme}'
					  , '{content}'
					  , '{evening_date}');'''
			# print(sql)
			file.write(sql)
			self.curs.execute(sql)
		self.conn.commit()

		file.close()

	def exe_info(self) :
			self.update_info()

if __name__ == '__main__':
	dbu = DBUpdater()
	dbu.exe_info()