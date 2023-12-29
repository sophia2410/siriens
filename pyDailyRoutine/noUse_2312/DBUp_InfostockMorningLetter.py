#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
#-*- coding: utf-8 -*-

import pandas as pd
import requests
from bs4 import BeautifulSoup
import pymysql
import os

# 특수문자 처리
def special_char(str):
	pt = str.replace('"','\\"').replace("'", "\\'")
	pt = pt.replace('∼','~')
	pt = pt.replace('＆','&')
	pt = pt.replace(' ','')

	# pt = re.sub('[a-zA-Z]' , '', str)
	# pt = re.sub('[\{\}\[\]\/?.,;:|\)*~`!^\-_+<>@\#$%&\\\=\(\'\"]', '', pt)
	return pt

# 인포스탁 지표 정보 가져오기
##---------------------------------------------------------------------- 
def daily_market(date, trade_date, mkidx, file, conn, cur):

	# 지표 구하기
	for idx, trd in enumerate(mkidx.find_all("tr")):
		if idx == 0:
			continue
		# print(trd)
		tdd = trd.find_all("td")
		if(len(tdd)) == 5 :
			idxnm  = tdd[1].text
			idxval = tdd[2].text
			idxdf  = tdd[3].text
			idxrt  = tdd[4].text
		elif(len(tdd)) == 4 :
			idxnm  = tdd[0].text
			idxval = tdd[1].text
			idxdf  = tdd[2].text
			idxrt  = tdd[3].text
		elif(len(tdd)) == 3 :
			idxnm  = tdd[1].text
			idxval = tdd[2].text
			idxdf  = ''
			idxrt  = ''
		else :
			idxnm  = tdd[0].text
			idxval = tdd[1].text
			idxdf  = ''
			idxrt  = ''

		# 해당 지수명이 없어도 일단 테이블 등록은 될 수 있도록 union 처리
		sql = f"   REPLACE INTO sirianz_market_index (report_date, index_cd, index_nm, index_value, index_diff, index_close_rate,comment, create_dtime) "\
			f"		SELECT '{trade_date}', MAX(cd) cd, '{idxnm}', '{idxval}', '{idxdf}', '{idxrt}', '{date}', NOW() "\
			f"		  FROM (SELECT cd "\
			f"				  FROM comm_cd A WHERE l_cd = 'A0000' and nm = '{idxnm}' "\
			f"				 UNION ALL "\
			f"				SELECT '' cd ) Z"
		cur.execute(sql)
		# print(sql)

	cur.execute('commit;')




# 인포스탁 모닝레터 데이터 가져오기
##---------------------------------------------------------------------- 
def call(link, file, conn, cur):

	href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
	soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")

	# 모닝레터 일자 구하기
	date = soup.find("div", class_="dateBox").text.strip()
	date = date.replace('. ','')[0:8]

	sql = f"SELECT max(date) date FROM calendar a WHERE date < '{date}'"
	print(sql)
	df = pd.read_sql(sql, conn)
	trade_date = df['date'][0].decode('utf-8')

	# 지표 크롤링
	hf = soup.find("div", class_="newsTable")
	daily_market(date, trade_date, hf, file, conn, cur)

# 메인 function
def main():
	conn = pymysql.connect(host='yunseul0907.cafe24.com', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')
	cur = conn.cursor()
	print('처리중....................')
	file=open("infostock.txt", "w", encoding="utf-8")
	
	# sql = "SELECT date date FROM calendar a WHERE a.date between '20221001' and '20230807'"
	# sql = "SELECT date date FROM calendar a WHERE not exists (select * from rawdata_infostock b where b.str2 = a.date) and a.date between '20230808' and '20230817'"
	sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
	# sql = "SELECT date date FROM calendar a WHERE a.date between '20231025' and '20231025'"

	df = pd.read_sql(sql, conn)

	for idx in range(len(df)):
		date = df['date'].values[idx].decode('utf-8')
		print('********* Infostock Morning Letter ' + date + ' ********************************************************************************************************')
	
		# 인포스탁 크롤링
		# link = 'http://localhost/scrap/infostock/bak_MorningLetter/MorningLetter_' +  date + '.html'
		link = 'http://localhost/scrap/infostock/MorningLetter_' +  date + '.html'

		# file_path = 'E:/Project/202410/www/scrap/infostock/bak_MorningLetter/MorningLetter_' + date + '.html'
		file_path = 'E:/Project/202410/www/scrap/infostock/MorningLetter_' + date + '.html'
		if os.path.isfile(file_path):
			call(link, file, conn, cur)
		else :
			print('Not Found!!!')

	file.close()
	conn.close()
	print('End!!!')
##----------------------------------------------------------------------

if __name__ == "__main__":
	# 인포스탁 테마이력 크롤링
	main()
