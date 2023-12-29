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
		pathExl = 'E:/Project/202410/data/PythonDBUpload/STOCK_COMMENT_20230225.xlsx'
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
		
		rdxls.code = rdxls.code.map('{:06d}'.format)
		return rdxls

	def update_info(self):
		rdxls = self.read_xlsx()

		file=open("signal_evening.sql", "w", encoding="utf-8")

		for idx in range(len(rdxls)):
			name	= rdxls.name.values[idx]
			comment	= rdxls.comment.values[idx].replace("'", "\\'").replace('"','\\"')
			remark	= rdxls.remark.values[idx].replace("'", "\\'").replace('"','\\"')
			
			if pd.isna(rdxls.code.values[idx]) :
				code	= ""
			else :
				code	= rdxls.code.values[idx]

			sql = f'''REPLACE INTO stock_comment_bak
		              ( code
					  ,	name
					  , comment
					  , remark
					  , create_date
					  , last_date
					  )
		              VALUES
		              ( '{code}'
					  ,	'{name}'
					  , '{comment}'
					  , '{remark}'
					  ,  now()
					  ,  now());'''
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