"""파이썬 증권데이터 분석"""
import pandas as pd
from bs4 import BeautifulSoup
from urllib.request import urlopen
from datetime import datetime
from threading import Timer
import requests
import pymysql
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

	def update_org_link(self):
		"""시그널이브닝 중 네이버뉴스 파일을 읽어와서 원본주소 찾기"""
		# self.codes = dict()
		sql = f"select link from signal_evening_cafe where link like 'https://n.news%' or link like 'https://news.naver.com/%' group by link"
		df = pd.read_sql(sql, self.conn)
		# print(df)
		for idx in range(len(df)):
			blink = df['link'].values[idx]
			link = blink.decode('utf-8')

			# 네이버 뉴스경로인 경우는 기사 원문 찾아오기
			nv = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
			nvsoup = BeautifulSoup(nv.text, "html.parser")
			org = nvsoup.find("a", class_="media_end_head_origin_link")
			orglink = org.attrs['href']

			sql = f"UPDATE signal_evening_cafe SET update_link = '{orglink}' WHERE link = '{link}'"
			print(sql)
			self.curs.execute(sql)
			self.conn.commit()

	def exe_info_stock(self) :
			self.update_org_link()

if __name__ == '__main__':
	dbu = DBUpdater()
	dbu.exe_info_stock()
