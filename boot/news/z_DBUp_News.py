#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
#-*- coding: utf-8 -*-

import requests
from bs4 import BeautifulSoup
import sys
sys.path.append("E:/Project/202410/www/boot/common/python")
from crawling_news import crawling_news

# 뉴스 가져오기
##----------------------------------------------------------------------

def call(link):
	crawling_news(link, 'type1')

##----------------------------------------------------------------------

if __name__ == "__main__":
	# news.php 에서 크롤링 요청
	
	# call(sys.argv[1])

	call('https://www.moneys.co.kr/article/2023122315571525837')

	# http://www.thebigdata.co.kr/view.php?ud=202308071124588701cd1e7f0bdf_23
	# https://www.businesspost.co.kr/BP?command=article_view&num=322864


# http://www.thevaluenews.co.kr/news/view.php?idx=177131
# https://marketinsight.hankyung.com/article/202307200942r
# http://news.heraldcorp.com/view.php?ud=20170721000175
#https://dealsite.co.kr/articles/106317
#https://www.dnews.co.kr/uhtml/view.jsp?idxno=202306131720316560423
# https://www.mk.co.kr/news/stock/10788958

	#---------------------------------------------------------------------------------------------------------------------------------------------
	# 뉴스 크롤링 테스트용 주소
	# call('https://biz.chosun.com/it-science/ict/2023/01/18/RESV46D5JJHWXMRRBRM2CD6YR4/?utm_source=naver&utm_medium=original&utm_campaign=biz')
	# call('https://www.edaily.co.kr/news/read?newsId=03079926625804672') #이데일리
	# call('https://www.etoday.co.kr/news/view/2115683') #이투데이
	# call('https://www.fnnews.com/news/202111171004226661') #파이낸셜뉴스
	# call('https://www.yna.co.kr/view/AKR20210226129000002?input=1195m') #연합뉴스    
	# call('http://www.newstomato.com/ReadNews.aspx?no=1172339&inflow=N') #뉴스토마토
	# call('https://news.mt.co.kr/mtview.php?no=2023011810011835255') #머니투데이
	# call('https://www.hankyung.com/economy/article/2023011949305') #한국경제
	# call('https://view.asiae.co.kr/article/2023012009375357592') #아시아경제
	# call('https://www.etnews.com/20190128000131') #전자신문
	# call('https://newsis.com/view/?id=NISX20230120_0002165672&cID=10301&pID=10300') #뉴시스
	# call('https://moneys.mt.co.kr/news/mwView.php?no=2023011916223193539') #머니s
	# call('https://www.sedaily.com/NewsView/29KM1UAZD2') #서울경제
	

	#https://www.yna.co.kr/view/AKR20230119004500091?input=1195m # 연합뉴스 추가 확인
	# call('https://www.dailian.co.kr/news/view/1194722') #데일리안
	# call('https://www.newspim.com/news/view/20230119000573') #뉴스핌
	# call('https://news.mtn.co.kr/news-detail/2022061417343840239') #머니투데이방송
	# call('https://www.hellot.net/news/article.html?no=73813') #헬로T
	# call('https://www.kpanews.co.kr/article/show.asp?idx=239467&category=D') #약사공론
	# call('https://pharm.edaily.co.kr/news/read?newsId=01348086632526704&mediaCodeNo=257') #팜이데일리
	# call('https://www.news1.kr/articles/4929426') #뉴스1
	# http://www.thebell.co.kr/free/content/ArticleView.asp?key=202301180942290280105133&svccode=00&page=1&sort=thebell_check_time #더벨
	# call('https://n.news.naver.com/mnews/article/009/0005076671?sid=101') #매일경제
	# call('http://www.dt.co.kr/contents.html?article_no=2023011702100932078001&ref=naver') #디지털타임즈
	# call(' https://news.mtn.co.kr/news-detail/2023011912150194981') #연합뉴스
	# http://news.heraldcorp.com/view.php?ud=20230119000519 # 헤럴드경제
	# https://www.enetnews.co.kr/news/articleView.html?idxno=9205 #이넷뉴스
	# https://www.newsway.co.kr/news/view?tp=1&ud=2020060511564242696 $뉴스웨이
	#---------------------------------------------------------------------------------------------------------------------------------------------