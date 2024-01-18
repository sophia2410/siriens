#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
# -*- coding: utf-8 -*-

import requests
import pandas as pd
from bs4 import BeautifulSoup
from datetime import datetime
import pymysql
import sys
sys.path.append("E:/Project/202410/www/boot/common/python")
from crawling_news import crawling_news

import requests
requests.packages.urllib3.disable_warnings()

def special_char(str):
	pt = str.replace('"','\\"').replace("'", "\\'")
	pt = pt.replace('∼','~')
	pt = pt.replace('＆','&')

	# pt = re.sub('[a-zA-Z]' , '', str)
	# pt = re.sub('[\{\}\[\]\/?.,;:|\)*~`!^\-_+<>@\#$%&\\\=\(\'\"]', '', pt)
	return pt

# 시그널리포트 가져오기
##---------------------------------------------------------------------- 

def main():
	print('처리중....................')
	conn = pymysql.connect(host='yunseul0907.cafe24.com', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')
	cur = conn.cursor()
	
	sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
	sql = "SELECT date FROM calendar a WHERE date = '20240115'"
	
	df = pd.read_sql(sql, conn)
	date = df['date'].values[0].decode('utf-8')

	link = 'http://localhost/scrap/' +  date + '.html'
	print(link)

	href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
	# 한글 깨져서 테스트
	# soup = BeautifulSoup(href.text.replace('<!-- SE-TEXT { -->',''), "html.parser")
	soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")
	print(soup)
	title = soup.find("title")
	ptt = title.text
	for pdt in ptt.split(' ') :
		if pdt[0:3] == '202' :
			dt = pdt.replace('.','').replace('/','')[0:8]
			print(dt)

	# 제목 ", ' 에 \ 붙여주기 && 특수문자 제거
	ec_ptt = special_char(ptt)

	# 본문 읽어와서 DB 등록하기
	# 사용변수 일부 초기화
	hf = '';
	file=open("signal.txt", "w", encoding="utf-8")
	for content in soup.find_all("p", class_="se-text-paragraph se-text-paragraph-align-"):

		#그룹, 종목 정보 가져오기
		if len(content.find_all("b")) > 0 :
			hd = content.find("b")    # for st in content.select("b"):
			# print(hd.text)

			# 시그널리포트 대분류인 경우
			if hd.text[0:1] == '<' :
				if '일정' not in hd.text :
					db_grp = hd.text.replace('<','').replace('>','').replace(' ','').replace('＆','&')

			# 종목정보인 경우
			if hd.text[0:1] == '●' :
				sts = '';
				stt = hd.text.split('●')
				for st in stt[1].split('/') :
					# print(st)
					for std in st.split(',') :
						std.split('(')
						# print(std.split('(')[0].split()[0])
						if sts != '' :
							sts += ','
						sts += std.split('(')[0].split()[0]
				db_sts = sts
				db_sts_v2 = hd.text[1:]

			print(hd.text)
			file.write(hd.text)
			file.write('\n')

		#뉴스 링크 가져오기
		if len(content.find_all("a")) > 0 :
			
			lk = content.select("a.se-link") # for lk in content.select("a.se-link"):
			print(lk)
			hf = lk[0].attrs['href']
			hf = hf.replace('://m.','://')
			ntt = lk[0].text
			print(hf)
			# print(ntt)

			# 뉴스일자 기본 dt 값 넣어주기
			db_dt = dt

			# 제목 ", ' 에 \ 붙여주기 && 특수문자 제거
			ec_ntt = special_char(ntt)
			# 제목에 () 일자 있는 경우 제거하기
			if ec_ntt[0:1] == '(' and ec_ntt[9:10] == ')':
				db_dt = '20'
				db_dt += ec_ntt[1:9].replace('.','').replace('/','')
				ec_ntt = ec_ntt[10:]
			
			# 여러 종목 묶여있는 경우 뉴스별로 제목 검색해서 종목과 연결시켜주기
			if len(db_sts.split(',')) > 1 :
				for ct in db_sts.split(',') :
					if ct in ec_ntt :
						db_st = ct
						lk_yn = 'Y'
						break
					else :
						db_st = ''
						lk_yn = 'N'
			else :
				db_st = db_sts
				lk_yn = 'Y'

			# 네이버 뉴스경로인 경우는 기사 원문 찾아오기
			if 'news.naver.com' in hf :
				nv = requests.get(hf, headers={'User-agent': 'Mozilla/5.0'})
				nvsoup = BeautifulSoup(nv.text, "html.parser")
				orhf = nvsoup.find("a", class_="media_end_head_origin_link")
				if orhf is not None:
					hf = orhf.attrs['href']

			# 계속 오류내는 링크 정보 변경해주기 23.10.16 추가.
			if 'gametoc.hankyung.com' in hf :
				hf = hf.replace('gametoc.hankyung.com','www.gametoc.co.kr')

			sql = f"REPLACE INTO rawdata_siri_report(page_date, page_fg, page_title, signal_grp, grouping, stocks, stock, link, title, date, del_yn, save_yn, today_pick,  create_date) "\
				  f"VALUES ('{dt}', 'E', '{ec_ptt}', '{db_grp}', '{db_grp}', '{db_sts_v2}', '{db_st}', '{hf}', '{ec_ntt}', '{db_dt}', 'N', 'N','{lk_yn}', now())"
			file.write(sql)
			file.write('\n')
			cur.execute(sql)
			cur.execute('commit;')
			
			file.write(hf)
			file.write('\n')
			file.write(ntt)
			file.write('\n')

			# 크롤링을 통해 뉴스 일자, 시간, 기자 정보 구하기
			cnd = crawling_news(hf, 'type3')
			if(cnd[0] != 'NoData') :

				cr_title = special_char(cnd[0])
				sql = f"UPDATE rawdata_siri_report"\
					f"   SET crawling_title = '{cr_title}'"\
					f"     , crawling_name  = '{cnd[1]}'"\
					f"     , crawling_date  = '{cnd[2]}'"\
					f"     , crawling_time  = '{cnd[3]}'"\
					f" WHERE page_date = '{dt}'"\
					f"   AND link      = '{hf}'"\
					f"   AND page_fg   = 'E'"

				file.write(sql)
				file.write('\n')
				cur.execute(sql)
				cur.execute('commit;')

			# 이브닝 제목은 한번만 등록되도록 처리
			ec_ptt = ''

		#뉴스 내용 가져오기
		if len(content.find_all("b")) == 0 and len(content.find_all("a")) == 0 :
			if content.text.replace(' ','').strip() != '' :
				ctt = special_char(content.text)
				# print(ctt)

				sql = f"UPDATE rawdata_siri_report SET content = '{ctt}' WHERE page_date = '{dt}' AND link = '{hf}'"
				file.write(sql)
				file.write('\n')
				cur.execute(sql)
				cur.execute('commit;')

				# file.write(ctt)
				# file.write('\n')
				ctt = ''
				hf  = ''
	
	# 크롤링한 데이터 반영 안정화 되면 소스 수정. 테스트 중에는 아래 로직 잠시 주석처리. 2023.07.30
					# # 이브닝 등록 완료 후 기등록 데이터와 비교, 보완처리
					# # signals에 등록된 뉴스의 경우 데이터 불러오기
					# sql = f"UPDATE rawdata_siri_report A"\
					#         f" INNER JOIN signals B"\
					#         f"    ON B.link      = A.link"\
					#         f"   SET A.date      = B.date"\
					#         f"     , A.time      = B.time"\
					#         f"     , A.title     = (CASE WHEN A.title = '' THEN B.title ELSE A.title END)"\
					#         f"     , A.publisher = B.publisher"\
					#         f"     , A.writer    = B.writer"\
					#         f"     , A.code      = B.code"\
					#         f"     , A.stock     = B.name"\
					#         f"     , A.content   = (CASE WHEN A.content IS NULL THEN B.content WHEN A.content = '' THEN B.content ELSE A.content END)"\
					#         f"     , A.exists_yn = 'Y'"\
					#         f"     , A.confirm_fg= B.confirm_fg"\
					#         f"     , A.signal_id = B.signal_id"\
					#         f" WHERE page_date = '{dt}'"\
					#         f"   AND page_fg   = 'E'"

					# file.write(sql)
					# file.write('\n')
					# cur.execute(sql)
					# cur.execute('commit;')
	# 크롤링한 데이터 반영 안정화 되면 소스 수정. 테스트 중에는 아래 로직 잠시 주석처리. 2023.07.30

	sql = f"UPDATE rawdata_siri_report A"\
			f" INNER JOIN ( SELECT Y.cd, Y.nm, Y.nm_sub1, X.link "\
			f"                FROM (SELECT SUBSTR(link_1, 1, INSTR(link_1,'/')-1) AS link_2, link "\
			f"                        FROM (SELECT link, replace(replace(link,'http://', ''),'https://', '') AS link_1 "\
			f"                                FROM rawdata_siri_report "\
			f"                               WHERE page_date = '{dt}'"\
			f"                                 AND page_fg = 'E' "\
			f"                             ) A "\
			f"                      ) X "\
			f"               INNER JOIN (SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'PB000' ) Y "\
			f"                  ON Y.nm_sub1 =X.link_2 ) B"\
			f"    ON B.link    = A.link "\
			f"   SET A.publisher = B.nm"\
			f" WHERE page_date = '{dt}'"\
			f"   AND page_fg   = 'E'"\
			f"   AND exists_yn = 'N'"

	file.write(sql)
	file.write('\n')
	cur.execute(sql)
	cur.execute('commit;')

	file.close()
	conn.close()
	print('End!!!')

##----------------------------------------------------------------------

if __name__ == "__main__":
	# 시그널이브닝 크롤링 테스트
	# call('http://localhost/scrap/bak_signalevening/20230728.html')
	# call('http://localhost/scrap/20231013.html')
	# call('http://localhost/scrap/' +  datetime.today().strftime('%Y%m%d') + '.html')
	main()