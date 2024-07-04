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
def market_index(date, mkidx, file, conn, cur):

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
		sql = f"   REPLACE INTO infostock_market_index (report_date, index_cd, index_nm, index_value, index_diff, index_close_rate,comment, create_dtime) "\
			f"		SELECT '{date}', MAX(cd) cd, '{idxnm}', '{idxval}', '{idxdf}', '{idxrt}', '{date}', NOW() "\
			f"		  FROM (SELECT cd "\
			f"				  FROM comm_cd A WHERE l_cd = 'A0000' and nm = '{idxnm}' "\
			f"				 UNION ALL "\
			f"				SELECT '' cd ) Z"
		cur.execute(sql)
		# print(sql)

	cur.execute('commit;')

# 인포스탁 당일테마 데이터 가져오기
##---------------------------------------------------------------------- 
def daily_theme(date, dailyTheme, file, conn, cur):

	theme_idx = 0
	theme_str = []
	tbl = dailyTheme.find("table", class_="tbl")
	for p,tbld in enumerate(tbl.find_all("tr")):
		if(p>2): # title은 건너띄기
			dd = tbld.find_all("td")
			if len(dd) == 2 :
				aa = dd[0].find("p") 
				if aa is not None:
					tm_g_nm = dd[0].text
					isu = dd[1].text
					isu = special_char(isu)

					theme_idx = p
					theme_str.append(tm_g_nm)
			
			if len(dd) == 1 :
				if theme_idx > 0:
					det = dd[0].text
					det = special_char(det)
					det = det.replace('.▷', '.\\n▷').replace('. ▷', '.\\n▷')
					theme_idx = 0

					# Daily Theme 정보 테이블에 등록하기
					sql = f"REPLACE INTO rawdata_infostock(str1, str2, str3, str7, str8) VALUES ('Daily_Theme_Group', '{date}', '{tm_g_nm}', '{isu}', '{det}')"
					file.write(sql)
					file.write('\n')
					cur.execute(sql)

	for i, tbll in enumerate(dailyTheme.find_all("table", class_="tbll")):
		# print(theme_str[i])
		tm_g_nm = theme_str[i]
		print('########## '+ date + ' / ' + tm_g_nm +' ########')

		for j,tms in enumerate(tbll.find_all("tr")):
			if(j>0): # title은 건너띄기
				sd = tms.find_all("td")
				# print(sd)
				if len(sd) == 4:
					tnm = sd[0].text.strip()
					# print(tnm)
					scd = sd[2].find("a").attrs['href'].split('code=')[1]
					# print(scd)
					snm = sd[2].text.split('(')[0].strip()
					# print(snm)
					i += 1
				else :
					scd = sd[0].find("a").attrs['href'].split('code=')[1]
					# print(scd)
					snm = sd[0].text.split('(')[0].strip()
					# print(snm)
		
				# Daily Theme - 종목 정보 테이블에 등록하기
				sql = f"REPLACE INTO rawdata_infostock(str1, str2, str3, str4, str5, str6) VALUES ('Daily_Theme_Stock', '{date}', '{tm_g_nm}', '{tnm}', '{scd}', '{snm}')"
				file.write(sql)
				file.write('\n')
				cur.execute(sql)

	# 인포스탁 테마 기준 테마코드 업데이트
	sql = f" UPDATE rawdata_infostock A "\
		 f"   INNER JOIN theme B "\
		 f" 	 ON B.theme_nm = A.str4 "\
		 f" 	SET A.theme_cd = B.theme_cd "\
		 f"   WHERE A.str1 = 'Daily_Theme_Stock' "\
		 f" 	AND A.str2 = '{date}' "\
		 f" 	AND A.theme_cd is null "

	file.write(sql)
	file.write('\n')
	cur.execute(sql)

	cur.execute('commit;')


# 인포스탁 당일 코스피,코스닥 종목 가져오기
##---------------------------------------------------------------------- 
def daily_market(date, daily_market, file, conn, cur):

	# 특징주 구하기
	for market_idx, tbl in enumerate(daily_market.find_all("table", class_="tbl")):
		if   market_idx == 0 : mk = 'Daily_Kospi' 
		elif market_idx == 1 : mk = 'Daily_Kosdaq'
		elif market_idx == 2 : mk = 'Daily_Stock'
		elif market_idx == 3 : mk = 'Daily_Down'
		else : break
		print('########## '+ date + ' / ' + mk +' ########')

		if market_idx < 2 : # 코스피,코스닥 특징주인 경우
			for i,sd in enumerate(tbl.find_all("tr")):
				if i > 1:
					if len(sd) == 2:
						# print(sd)
						sd = sd.find_all("td")
						# print(sd)
						if '(' in sd[0].text: # 종목이 지정된 경우 
							stock = sd[0].text.split('(')
							stock_nm = stock[0]
							# print(stock_nm)
				
							stock_cd = stock[1].split(')')[0]
							# print(stock_cd)
						else: # 종목 그룹으로 표시된 경우
							stock_nm = sd[0].text
							stock_cd = ''

						comment = sd[1].text
						comment = special_char(comment)
						# print(comment)

					else :
						# 종목 그룹으로 표시된 경우 종목명 찾아서 INSERT 해주기
						td = sd.find("td")
						tdstr = special_char(td.text)
						tdstr = tdstr.replace('.▷', '.\\n▷').replace('. ▷', '.\\n▷')
						# print(tdstr)
						if stock_cd != '':
							sql = f"REPLACE INTO rawdata_infostock(str1, str2, str5, str6, str7, str8) VALUES ('{mk}', '{date}', '{stock_cd}', '{stock_nm}', '{comment}', '{tdstr}')"
							file.write(sql)
							file.write('\n')
							cur.execute(sql)
						else:
							sts = tdstr.split('[종목]: ')[1].split(',')
							# print(sts)
							for st in sts:
								stock_nm = st.strip()
								sql = f"REPLACE INTO rawdata_infostock(str1, str2, str5, str6, str7, str8) VALUES ('{mk}', '{date}', '{stock_cd}', '{stock_nm}', '{comment}', '{tdstr}')"
								file.write(sql)
								file.write('\n')
								cur.execute(sql)

		elif market_idx < 4 : # 당일 상한가 및 급등주 / 하한가 및 급락주
			for i,sd in enumerate(tbl.find_all("tr")):
				if i > 0: # 타이틀 제외하기
					# print(sd)
					sd = sd.find_all("td")
					stock = sd[0].text.split('(')
					# print(sd[0].text)
					stock_nm = stock[0]
					# print(stock_nm)
					stock_cd = stock[1].split(')')[0]
					# print(stock_cd)

					comment = sd[2].text
					comment = special_char(comment)
					# print(comment)

					sql = f"REPLACE INTO rawdata_infostock(str1, str2, str5, str6, str7) VALUES ('{mk}', '{date}', '{stock_cd}', '{stock_nm}', '{comment}')"
					file.write(sql)
					file.write('\n')
					cur.execute(sql)

		# 당일 특징 종목 중 테마 정보 업데이트
		sql = f" UPDATE rawdata_infostock X "\
			f" 	 INNER JOIN (SELECT A.str1, A.str2, A.str5, A.str6, A.substr2, B.str3 "\
	 		f" 				  FROM (SELECT *, RTRIM((SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(str7, '테마', 1), ' ', -2), '/',1))) substr2 "\
			f" 						  FROM rawdata_infostock "\
			f" 						 WHERE str1 = 'Daily_Stock' AND str2 = '{date}' AND str7 like '%테마%') A "\
			f" 						LEFT OUTER JOIN (SELECT * FROM rawdata_infostock WHERE str1 = 'Daily_Theme_Group' AND str2 = '{date}') B "\
			f" 					ON B.str3 like concat('%',A.substr2,'%') ) Y "\
			f" 		ON  Y.str5 = X.str5 "\
			f" 		SET X.str3 = Y.str3 "\
			f" 	  WHERE X.str1 = 'Daily_Stock' AND X.str2 = '{date}' AND X.str7 like '%테마%' "
		file.write(sql)
		file.write('\n')
		cur.execute(sql)

		# 종목코드 없는 경우 업데이트 하기
		sql = f" UPDATE rawdata_infostock A "\
			f"		SET str5 "\
			f"		= (SELECT code "\
			f"			FROM stock B "\
			f"			WHERE B.name = A.str6 "\
			f"			AND   B.last_yn = 'Y') "\
			f"  WHERE (A.str5 = '' or A.str5 is null)"\
			f"	AND str1 in ('Daily_Kospi', 'Daily_Kosdaq') "\
			f"	AND str2 = '{date}' "
		file.write(sql)
		file.write('\n')
		cur.execute(sql)

	cur.execute('commit;')


# 인포스탁 당일특징주 가져오기
##---------------------------------------------------------------------- 
def daily_stock(link, file, conn, cur):

	href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
	soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")

	# 특징주 일자 구하기
	date = soup.find("div", class_="dateCon").text.strip()
	date = date.replace('. ','')[0:8]
	# # print(date)

	# 특징주 구하기
	d_stock = soup.find("table", class_="tbl")
	for sd in d_stock.find_all("tr"):
		if ('종목' not in sd.text.replace(' ','')) and (sd.text.replace(' ','').strip() != '') :
			# print(sd)
			sd = sd.find_all("td")
			stock = sd[0].text.split('(')
			# print(sd[0].text)
			stock_nm = stock[0]
			# print(stock_nm)
			stock_cd = stock[1].split(')')[0]
			# print(stock_cd)

			comment = sd[2].text
			comment = special_char(comment)
			# print(comment)

			sql = f"REPLACE INTO rawdata_infostock(str1, str2, str5, str6, str7) VALUES ('Daily_Stock', '{date}', '{stock_cd}', '{stock_nm}', '{comment}')"
			file.write(sql)
			file.write('\n')
			cur.execute(sql)

	cur.execute('commit;')
	

# 인포스탁 이브닝 레터 데이터 가져오기
##---------------------------------------------------------------------- 
def call(link, file, conn, cur):

	href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
	soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")

	# 이브닝레터 일자 구하기
	date = soup.find("div", class_="dateBox").text.strip()
	date = date.replace('. ','')[0:8]

	# 지표 크롤링
	hf = soup.find("div", class_="newsTable")
	market_index(date, hf, file, conn, cur)

	# 각 섹션별 데이터 순서 구하기
	for sIdx, sc in enumerate(soup.find_all("a", class_="menu-anchor")):
		if '특징 테마 및 테마별 등락률' in sc.text :
			tmIdx = sIdx
		elif '특징 종목 및 상한가' in sc.text :
			scIdx = sIdx

	# 각 섹션별 데이터 처리 호출
	for eveLtIdx, tm in enumerate(soup.find_all("div", class_="selectConBox")):
		print('######## ' + str(eveLtIdx)  + ' ####')
		# 당일 테마
		if eveLtIdx == tmIdx :
			daily_theme(date, tm, file, conn, cur)

		# 당일 코스피/코스닥 종목 + 당일 특징주
		if eveLtIdx == scIdx :
			daily_market(date, tm, file, conn, cur)

# 메인 function
def main():
	conn = pymysql.connect(host='siriens.mycafe24.com', user='siriens', password='hosting1004!', db='siriens', charset='utf8')
	cur = conn.cursor()
	print('처리중....................')
	file=open("infostock.txt", "w", encoding="utf-8")

	# sql = "SELECT date date FROM calendar a WHERE not exists (select * from rawdata_infostock b where b.str2 = a.date) and a.date between '20230710' and '20230725'"
	sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
	# sql = "SELECT date date FROM calendar a WHERE a.date between '20231025' and '20231025'"

	df = pd.read_sql(sql, conn)

	for idx in range(len(df)):
		date = df['date'].values[idx].decode('utf-8')
		print('********* Infostock Evening Letter ' + date + ' ********************************************************************************************************')
	
		# 인포스탁 크롤링
		# link = 'http://localhost/scrap/infostock/bak_dailytheme/DailyTheme_' +  date + '.html'
		link = 'http://localhost/scrap/infostock/EveningLetter_' +  date + '.html'

		# file_path = 'E:/Project/202410/www/scrap/infostock/bak_dailytheme/DailyTheme_' + date + '.html'
		file_path = 'E:/Project/202410/www/scrap/infostock/EveningLetter_' + date + '.html'
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
