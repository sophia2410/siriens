import openpyxl
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
		"""과거 모차십 엑셀 파일을 읽어와서 데이터프레임으로 반환"""
		pathExl = 'E:/Project/202410/data/_Mochaten/old_mochaten_list_20221231.xlsx'
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
		print(rdxls)
		return rdxls

	def update_info(self):
		rdxls = self.read_xlsx()

		file=open("mochaten_list.sql", "w", encoding="utf-8")
		
		for idx in range(len(rdxls)):
			date= rdxls.date.values[idx]
			fg	= rdxls.fg.values[idx]

			stock= [rdxls.stock1.values[idx]]
			stock.append(rdxls.stock2.values[idx])
			stock.append(rdxls.stock3.values[idx])
			stock.append(rdxls.stock4.values[idx])
			stock.append(rdxls.stock5.values[idx])
			stock.append(rdxls.stock6.values[idx])
			stock.append(rdxls.stock7.values[idx])
			stock.append(rdxls.stock8.values[idx])
			stock.append(rdxls.stock9.values[idx])
			stock.append(rdxls.stock10.values[idx])
			stock.append(rdxls.stock11.values[idx])
			stock.append(rdxls.stock12.values[idx])
			stock.append(rdxls.stock13.values[idx])
			stock.append(rdxls.stock14.values[idx])
			stock.append(rdxls.stock15.values[idx])
			stock.append(rdxls.stock16.values[idx])
			stock.append(rdxls.stock17.values[idx])
			stock.append(rdxls.stock18.values[idx])
			stock.append(rdxls.stock19.values[idx])
			stock.append(rdxls.stock20.values[idx])
			stock.append(rdxls.stock21.values[idx])
			stock.append(rdxls.stock22.values[idx])

			for i in range(len(stock)):	#for page in range:
				print(stock[i])
				if pd.isna(stock[i]) :
					break
				else :
					sql = f'''REPLACE INTO temp_old_mochaten_list
							( date
							, fg_str
							, name
							)
							VALUES
							( '{date}'
							, '{fg}'
							, '{stock[i]}');'''
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