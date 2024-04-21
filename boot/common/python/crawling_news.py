import requests
import certifi
from bs4 import BeautifulSoup

#연합뉴스
##----------------------------------------------------------------------
def crawling_yna(soup):
	publisher = "연합뉴스"

	title = soup.find("title")
	title = title.text.replace(' | 연합뉴스','')

	name = soup.find("strong", class_="tit-name")
	if name is not None :
		name = name.text.split()[0]
	else : 
		name = ''

	date = soup.find("p", class_="update-time")
	date = date.text.replace('\n','').replace('-','').replace('송고시간','')
	time = date[9:14]
	date = date[0:8]

	# 광고 문자 제거, 기사 앞 공백 제거. 본문 앞 텍스트 제거 위해 '기자'로 구분 이후 내역만 출력
	content = soup.find("article", class_="story-news")
	content = content.text.replace('광고','').replace('\n ', '\n').strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#머니S
##----------------------------------------------------------------------
def crawling_moneys(soup):
	publisher = "머니S"
	
	#제목
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0]

	#기자 이름 - 페이지별로 규칙이 달라져 일단 패스
	# name = soup.find("strong", class_="name")
	name = ''

	#날짜 구하기
	date = soup.find("span", class_="num")

	if date is None:
		date = soup.find("div", class_="date")
		date = date.text.replace('\n','').split('|') 
		time = date[1].split()[0][:5]
		date = date[0].replace('.','').split()[0]
	else:
		time = date.text.split()[2]
		date = date.text.split()[1].replace('.','')

	#본문구하기
	content = soup.find("div", class_="content_area")
	if content is None:
		content = ''
	else:
		content = content.text.strip()
	
	rt = [publisher, title, name, date, time, content]
	return rt


#서울경제
##----------------------------------------------------------------------
def crawling_sedaily(soup):
	publisher = "서울경제"
	
	# 타이틀 공백 및 개행 처리
	title = soup.find("title")
	title = title.text.split('|')[0]

	art_info = soup.find_all("span", class_="url_txt")
	if art_info:
		# 기자이름, 뉴스일시 구해와서 나누기
		name = art_info[2].text.split()[0]
		
		# 날짜 공백으로 잘라서 구해오기
		date = art_info[0].text.split()
		time = date[1][0:5] 
		date = date[0].replace('-','').replace('입력','')
	else:
		name = ''
		date = ''
		time = ''


	# 본문구해오기
	content = soup.find("div", class_="article_view")
	if content:
		content = content.text.strip()
	else:
		content= ''

	rt = [publisher, title, name, date, time, content]
	return rt


#한국경제
##----------------------------------------------------------------------
def crawling_hankyung(soup):
	publisher = "한국경제"

	# 타이틀 공백 및 개행 처리
	title = soup.find("h1", class_="headline")
	if title is None:
		title = soup.find("h1", class_="article-tit")
	title = title.text.strip()

	# 기자 이름 본문에 있어 패스
	name = ''

	# 날짜 공백 처리
	date = soup.find("div", class_="datetime")
	if date is None:
		time = ''
		date = ''
	else:
		date = date.text.replace('\n','').replace('.','').replace('입력','').strip()
		time = date[9:14]
		date = date[0:8]

	# 본문 구하기
	content = soup.find("div", class_="article-body")
	if content is not None:
		content = content.text.strip()
	else:
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt

#매일경제
##----------------------------------------------------------------------
def crawling_mk(soup):
	publisher = "매일경제"

	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()

	# 기자 구하기
	name = soup.find("dt", class_="name")	
	if name is not None :
		name = name.text.strip().split()
		name = name[0]
	else : 
		name = ''
	
	# 날짜 구하기
	date = soup.find("div", class_="time_area")
	if date is not None :
		date = date.text.strip().split()
		time = date[3][0:5]
		date = date[2].replace('-','')
	else : 
		time = ''
		date = ''
	
	# 본문 구하기
	content = soup.find("div", class_="news_cnt_detail_wrap")
	if content is not None :
		content = content.text.strip()
	else :
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt

#아시아경제
##----------------------------------------------------------------------
def crawling_asiae(soup):
	publisher = "아시아경제"

	# 타이틀 공백 처리
	title = soup.select("div.scont_tit > p")
	title = title[0].text.strip()

	# 기자 이름 구하기
	name = soup.find("div", class_="e_article")
	name = name.text.split()[0]

	# 아시아경제 일자 출력로직 변경 확인. 수정 2023.07.29
	# # 날짜에서 '기사입력'으로 문자열 잘라내여 입력일시만 뽑아오기
	# date = soup.find("p", class_="user_data")
	# date_splits  = date.text.replace('.','').split('기사입력')
	
	# 날짜에서 '입력'으로 문자열 잘라내여 입력일시만 뽑아오기
	date = soup.find("div", class_="date_box")
	date_splits  = date.text.replace('.','').split('입력')
	time = date_splits[1].strip()[9:14]
	date = date_splits[1].strip()[0:8]

	# 본문 구하기 - 본문 중간중간 들어간 <li> 항목 제거 하고 싶으나 잘 안됨.
	# <p> 안에 쓰인 본문만 구해오기로..
	cont_article = soup.find("div", class_="cont_article")
	content = []
	for p in cont_article.find_all("p"):
		content.append(p.text)
	content = content[:]
	
	rt = [publisher, title, name, date, time, content]
	return rt

#이데일리
##----------------------------------------------------------------------
def crawling_edaily(soup):
	publisher = "이데일리"

	title = soup.find("title")
	title = title.text.replace('\n','')

	name = soup.find("p", class_="reporter_name")
	name = name.text.replace('\n','').split(' ')
	name = name[0]

	date = soup.find("div", class_="dates")
	date = date.text.split('수정')
	date = date[0].replace('\n','').replace('-','').replace('등록 ','')

	if '오전 10' in date:
		date = date.replace('오전 ','')
	elif '오전 11' in date:
		date = date.replace('오전 ','')
	elif '오전 12' in date:
		date = date.replace('오전 12','00')
	elif '오전 ' in date:
		date = date.replace('오전 ','0')
	elif '오후 1' in date:
		date = date.replace('오후 1','13')
	elif '오후 2' in date:
		date = date.replace('오후 2','14')
	elif '오후 3' in date:
		date = date.replace('오후 3','15')
	elif '오후 4' in date:
		date = date.replace('오후 4','16')
	elif '오후 5' in date:
		date = date.replace('오후 5','17')
	elif '오후 6' in date:
		date = date.replace('오후 6','18')
	elif '오후 7' in date:
		date = date.replace('오후 7','19')
	elif '오후 8' in date:
		date = date.replace('오후 8','20')
	elif '오후 9' in date:
		date = date.replace('오후 9','21')
	elif '오후 10' in date:
		date = date.replace('오후 10','22')
	elif '오후 11' in date:
		date = date.replace('오후 11','23')
	elif '오후 12' in date:
		date = date.replace('오후 12','12')

	time = date[9:14]
	date = date[0:8]

	content = soup.find("div", class_="news_body")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#이투데이
##----------------------------------------------------------------------
def crawling_etoday(soup):
	publisher = "이투데이"

	title = soup.find("title")
	title = title.text.replace('\n','').replace('- 이투데이','')

	name = soup.find("dd", class_="reporter_name")
	if name is not None :
		name = name.text.replace('\n','').replace('구독하기','').split(' ')
		name = name[0].strip()
	else :
		name = ''

	date = soup.find("div", class_="newsinfo")
	if date is not None :
		date = date.text.replace('\n','').replace('-','').replace('구독하기','').replace('입력','').strip()
		time = date[9:14]
		date = date[0:8]
	else :
		time = ''
		date = ''

	content = soup.find("div", class_="articleView")
	if content is not None :
		content = content.text
	else :
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt

#파이낸셜뉴스
##----------------------------------------------------------------------
def crawling_fnnews(soup):
	print(soup)
	publisher = "파이낸셜뉴스"

	title = soup.find("title")
	title = title.text.replace('- 파이낸셜뉴스','')

	name = soup.find(id='customByline')
	if name is not None :
		name = name.text.replace(" 기자","")[-3:]
	else : 
		name = ''

	date = soup.find("div", class_="byline")
	if date is not None :
		date = date.text.replace('파이낸셜뉴스','').replace('입력','').replace('.','').strip()
		time = date[9:14]
		date = date[0:8]
	else :
		time = ''
		date = ''

	content = soup.find(id='article_content')
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#프라임경제
##----------------------------------------------------------------------
def crawling_newsprime(soup):
	publisher = "프라임경제"

	title = soup.find("title")
	title = title.text

	name = soup.find("span", class_="name")
	name = name.text.replace(" 기자","")

	date = soup.find("div", class_="arvdate")
	date=date.text.replace('\n','').replace('.','')
	time = date[-11:-6]
	date = date[-20:-12]

	content = soup.find(id='news_body_area')
	# content_len = len(content.text) -1
	content = content.text.replace(' ','')
	
	rt = [publisher, title, name, date, time, content]
	return rt

#뉴스토마토
##----------------------------------------------------------------------
def crawling_newstomato(soup):
	publisher = "뉴스토마토"

	# 타이틀 공백 및 개행 처리
	title = soup.find("title")
	title = title.text.replace('\n','').strip()

	# 본문에 있는 기자 이름 구해오기
	content = soup.find("div", class_="rns_text")
	splits  = content.text.split('기자]')
	name = splits[0][-3:].strip()
	
	# 날짜 공백 처리
	date = soup.find("div", class_="rn_sdate")
	date = date.text.replace('-','').strip()
	time = date[9:14]
	date = date[0:8]

	# 본문에 있는 기자 이름 구하기 위해 가져온 본문 출력
	content = splits[1].replace(' ','').strip()

	rt = [publisher, title, name, date, time, content]
	return rt


#뉴스핌
##----------------------------------------------------------------------
def crawling_newspim(soup):
	publisher = "뉴스핌"

	# 타이틀 공백 및 개행 처리
	title = soup.find("title")
	title = title.text.replace('\n','').strip()

	# 본문에 있는 기자 이름 구해오기
	content = soup.find("div", id="news-contents")
	splits  = content.text.split(']')
	
	if len(splits) > 1 :
		splits  = splits[1].split(' ')
		name = splits[1]
	else : 
		name = ''


	# 날짜 공백 처리
	date = soup.find("span", id="send-time")
	date =date.text.replace('년','').replace('월','').replace('일','').strip()
	time =date[9:14]
	date =date[0:8]

	# 본문에 있는 기자 이름 구하기 위해 가져온 본문 출력
	content = soup.find("div", id="news-contents")
	content = content.text.replace(' ','').strip()

	rt = [publisher, title, name, date, time, content]
	return rt
	
#아이뉴스24
##----------------------------------------------------------------------
def crawling_inews24(soup):
	publisher = "아이뉴스24"

	title = soup.find("title")
	title = title.text

	name = soup.find("author")
	
	if name is not None :
		name = name.text.replace(" 기자","")
	else :
		name = ''

	time=''
	date=''

	content = soup.find(id="articleBody")
	if content is not None :
		content = content.text.replace('\n\n','\n').strip()
	else :
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt

#전자신문
##----------------------------------------------------------------------
def crawling_etnews(soup):
	publisher = "전자신문"
	
	# 타이틀 공백 및 개행 처리
	title = soup.select("div.article_title > h2")
	title = title[0].get_text()

	# 본문에 있는 기자 이름 패스
	name = ''

	# 날짜 공백으로 잘라서 구해오기
	date = soup.find("time", class_="date")
	date = date.text.split()
	time = date[3]
	date = date[2].replace('-','')

	# 본문구해오기
	content = soup.find("div", class_="article_txt")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt


#뉴시스
##----------------------------------------------------------------------
def crawling_newsis(soup):
	publisher = "뉴시스"
	
	# 타이틀 공백 및 개행 처리
	title = soup.find("h1", class_="tit title_area")
	title = title.text

	# 본문에 있는 기자 이름 패스
	name = ''

	# 날짜 공백으로 잘라서 구해오기
	date = soup.select("div.articleView > div.view > div.infoLine > div.left > p > span")
	date = date[0].get_text().split()
	time = date[2][0:5]
	date = date[1].replace('.','')

	# 본문구해오기
	content = soup.select("div.viewer")
	content = content[0].get_text().split('\n')
	conl = []
	for con in content:
		if(con != ''):
			conl.append(con)
	content = conl[:]

	rt = [publisher, title, name, date, time, content]
	return rt


#MTN뉴스
##----------------------------------------------------------------------
def crawling_mtn(soup):
	publisher = "MTN뉴스"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()

	# 기자 구하기
	name = soup.find("span", class_="blue")
	if name is not None :
		name = name.text
	else :
		name = ''

	# 날짜 구하기
	date = soup.find("span", class_="time")
	if date is not None :
		date = date.text.split()
		time = date[2][0:5]
		date = date[1].replace('-','')
	else :
		time = ''
		date = ''

	# 본문 구하기
	content = soup.find("div", class_="news-content")
	if content is not None :
		content = content.text.strip()
	else :
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt


#머니투데이
##----------------------------------------------------------------------
def crawling_moneytoday(soup):
	publisher = "머니투데이"
	
	# 타이틀 구하기
	title = soup.find("h1", class_="subject")
	title = title.text

	# 기자 구하기
	name = soup.find("li", class_="name")
	name = name.text.split(' ')
	name = name[0]

	# 날짜 구하기
	date = soup.find("li", class_="date")
	date = date.text.replace('.','').split(' ')
	time = date[1]
	date = date[0]

	#본문 구하기
	content = soup.find("div", class_="article_view")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#더벨
##----------------------------------------------------------------------
def crawling_thebell(soup):
	publisher = "더벨"
	
	# 타이틀 구하기
	title = soup.find("p", class_="tit")
	# sub_tit = soup.find("span", class_="tit_np")
	# title = title.text.replace(sub_tit.text,'').sprit()
	title = title.text.strip()

	# 기자 구하기
	name = soup.find("span", class_="user")
	name = name.text.split()
	name = name[0]

	# 날짜 구하기
	date = soup.find("span", class_="date")
	date = date.text.replace('-','').split(' ')
	time = date[2][0:5]
	date = date[1]

	# 본문 구하기
	content = soup.find("div", id="article_main")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt
	

#뉴스1
##----------------------------------------------------------------------
def crawling_news1(soup):
	publisher = "뉴스1"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()

	# 기자 구하기
	info = soup.find("div", class_="info")
	info = info.text.split()
	name = info[1]

	# 날짜 구하기
	for i in range (2, len(info)) :
		if(info[i] == '|') :
			break
	
	time = info[i+2][0:5]
	date = info[i+1].replace('-','')
	
	# 본문 구하기
	content = soup.find("div", id="articles_detail")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt


#더일렉
##----------------------------------------------------------------------
def crawling_thelec(soup):
	publisher = "더일렉"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()

	# 기자 구하기
	info = soup.find("section", class_="article-head-info")
	info = info.text.strip().split()
	name = info[0]

	# 날짜 구하기
	time = info[4][0:5]
	date = info[3].replace('.','')
	
	# 본문 구하기
	content = soup.find("div", id="article-view-content-div")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#중앙일보
##----------------------------------------------------------------------
def crawling_joongang(soup):
	publisher = "중앙일보"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('|')
	title = title[0].strip()

	# 기자 구하기
	name = soup.find("div", class_="byline")
	name = getattr(name, "text", "").strip().split()
	name = name[0] if name else None

	# 날짜 구하기
	date = soup.select("#articlPressdate")
	# date = soup.select("time")
	date = date[0]["value"]
	time = ''
	
	# 본문 구하기
	content = soup.find("div", id="article_body")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt


#헤럴드경제
##----------------------------------------------------------------------
def crawling_heraldcorp(soup):
	publisher = "헤럴드경제"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()

	# 기자 구하기
	name = ''

	# 날짜 구하기
	date = soup.find("li","article_date")
	if date is not None:
		date = date.text.split()
		time = date[1][0:5]
		date = date[0].replace('.','')
	else:
		date = ''
		time = ''
	
	# 본문 구하기
	content = soup.find("div", class_="article_area")
	if content is not None:
		content = content.text.strip()
	else:
		content = ''

	rt = [publisher, title, name, date, time, content]
	return rt


#조선비즈 + 조선일보
##----------------------------------------------------------------------
def crawling_chosun(soup, link):
	publisher = "조선일보"

	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split('-')
	title = title[0].strip()
	
	name = ''
	time = ''
	content = ''

	# 날짜 구하기 -- 링크에 포함된 일자 구해오기
	split_link = link.split('/')

	if len(split_link) > 7 :
		date = split_link[5]+split_link[6]+split_link[7]
		
		if len(date) != 8 : 
			date = split_link[4]+split_link[5]+split_link[6]

			if len(date) != 8 : 
				date = split_link[6]+split_link[7]+split_link[8]
				if len(date) != 8 : 
					rt = ['NoData','NoData','NoData','NoData','NoData','NoData'] 
	
		rt = [publisher, title, name, date, time, content]
	else :
		rt = ['NoData','NoData','NoData','NoData','NoData','NoData'] 

	return rt

#빅데이터뉴스
##----------------------------------------------------------------------
def crawling_thebigdata(soup):
	publisher = "빅데이터뉴스"
	
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.strip()

	# 기자+날짜
	vct  = soup.select("div.vcbt01 > div.vctlt01 > p")

	# 기자 구하기
	name = vct[0].text.split()
	name = name[0]

	# 날짜 구하기
	# date = soup.find("span", class_="txt_editing") #화면 출력 변경 23.09.19
	date = vct[1].text.split()
	
	time = date[1][0:5]
	date = date[0].replace('-','')
	
	# 본문 구하기
	# content = soup.find("div", class_="txt_article") #화면 출력 변경 23.09.19
	content = soup.find("div", class_="vcc01 news_article")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt

#다트
##----------------------------------------------------------------------
def crawling_dart(soup, link):
	publisher = "다트"
	
	title = ''
	name = ''
	time = ''
	content = ''

	# 날짜 구하기 -- 링크에 포함된 일자 구해오기
	split_link = link.split('rcpNo=')
	date = split_link[1][0:8]

	rt = [publisher, title, name, date, time, content]
	return rt

#약업신문
##----------------------------------------------------------------------
def crawling_yakup(soup):
	publisher = "약업신문"
	print(soup)
	# 타이틀 구하기
	title = soup.find("title")
	title = title.text.split(']')
	title = title[1].strip()

	# 기자 구하기
	name = soup.find("div", class_="name_con")
	name = name.text.strip().split()
	name = name[0]

	# 날짜 구하기
	date = soup.find("div", class_="date_con")
	date = date.text.strip().split()
	time = date[2][0:5]
	date = date[1].replace('.','')
	
	# 본문 구하기
	content = soup.find("div", class_="text_article_con")
	content = content.text.strip()

	rt = [publisher, title, name, date, time, content]
	return rt


##************************************************************************
def crawling_news(link, type):

	# 크롤링을 통해 뉴스 일자, 시간, 기자 정보 구하기
	hf = requests.get(link, headers={'User-agent': 'Mozilla/5.0'}, verify=False)

	# 크롤링 시 인코딩 문제가 있는 신문사는 아래 인코딩 추가
	el = ['news1']
	for i in range(len(el)) :
		if el[i] in link :
			hf.encoding = 'utf-8'
	
	soup = BeautifulSoup(hf.text, "html.parser")
	title = soup.find("title")
	if title is None :
		link = ''
	elif '페이지를찾을수없습니다' in title.text.replace(' ','') :
		link = ''

	# file=open("crawler.txt", "w",encoding="utf-8")
	# file.write(link)
	# file.write(hf.text)
	# file.close()

	# 각 뉴스별 크롤링 처리 및 리턴
	if 'www.yna' in link:          #연합뉴스
		li = crawling_yna(soup)
	elif 'moneys' in link:         #머니S
		li = crawling_moneys(soup)
	elif 'sedaily' in link:        #서울경제
		li = crawling_sedaily(soup)
	elif 'www.hankyung' in link:   #한국경제
		li = crawling_hankyung(soup)
	elif 'www.mk' in link:         #매일경제
		li = crawling_mk(soup)
	elif 'asiae' in link:          #아시아경제
		li = crawling_asiae(soup)
	elif 'www.edaily' in link:     #이데일리
		li = crawling_edaily(soup)
	elif 'etoday' in link:         #이투데이
		li = crawling_etoday(soup)
	elif 'fnnews' in link:         #파이낸셜뉴스
		li = crawling_fnnews(soup)
	elif 'newsprime' in link:      #프라임경제
		li = crawling_newsprime(soup)
	elif 'newstomato' in link:     #뉴스토마토
		li = crawling_newstomato(soup)
	elif 'newspim' in link:        #뉴스핌
		li = crawling_newspim(soup)
	elif 'inews24' in link:        #아이뉴스24
		li = crawling_inews24(soup)
	elif 'www.etnews' in link:     #전자신문
		li = crawling_etnews(soup)
	elif 'newsis' in link:         #뉴시스
		li = crawling_newsis(soup)
	elif 'news.mtn' in link:       #MTN뉴스
		li = crawling_mtn(soup)
	elif 'news.mt' in link:        #머니투데이
		li = crawling_moneytoday(soup)
	elif 'thebell' in link:        #더벨
		li = crawling_thebell(soup)
	elif '.joongang' in link:      #중앙일보
		li = crawling_joongang(soup)
	elif '.chosun' in link:        #조선일보 -- link에 포함된 기사일자 구해오기
		li = crawling_chosun(soup, link)
	elif 'news.tvchosun' in link:  #TV조선   -- link에 포함된 기사일자 구해오기
		li = crawling_chosun(soup, link)
	elif 'news1' in link:          #뉴스1
		li = crawling_news1(soup)
	elif 'heraldcorp' in link:     #헤럴드경제
		li = crawling_heraldcorp(soup)
	elif 'thelec' in link:         #더일렉
		li = crawling_thelec(soup)
	elif 'thebigdata' in link:     #빅데이타뉴스
		li = crawling_thebigdata(soup)
	elif 'dart.fss' in link:       #다트     -- link에 포함된 기사일자 구해오기
		li = crawling_dart(soup, link)
		
	#인코딩 오류 문제로 우선 주석처리
	# elif 'yakup' in link:          #약업신문
	# 	li = crawling_yakup(soup)

	else : li = ['NoData','NoData','NoData','NoData','NoData','NoData'] 

	
	# li = [publisher, title, name, date, time, content] 순서로 들어옴
	#type1 = 전체 print로 응답
	#type3 = [title, name, date, time]
	if type == 'type1' :
		for i in range(len(li)) :
			print(li[i])
	else :
		for i in range(len(li)) :
			print(li[i])

		if type == 'type3' :
			rt = [li[1], li[2], li[3], li[4]]
		return rt
