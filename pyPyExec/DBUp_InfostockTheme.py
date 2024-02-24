#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
#-*- coding: utf-8 -*-

import requests
from bs4 import BeautifulSoup
import pymysql

# 인포스탁 데이터 가져오기
##---------------------------------------------------------------------- 

def call(link):
    print('처리중....................')
    conn = pymysql.connect(host='siriens.mycafe24.com', user='siriens', password='hosting1004!', db='siriens', charset='utf8')
    cur = conn.cursor()

    href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
    soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")
    
    # 테마내역 DB등록하기
    file=open("infostock.txt", "w", encoding="utf-8")
    for content in soup.find_all("a"):
        
        print(content.text)
        sql = f"REPLACE INTO rawdata_infostock_theme(str1, str2) VALUES ('ThemeAllAllowed', {content.text}')"
        file.write(sql)
        file.write('\n')
        cur.execute(sql)
        
    cur.execute('commit;')

    file.close()
    conn.close()
    print('End!!!')
##----------------------------------------------------------------------

if __name__ == "__main__":
    # news.php 에서 크롤링 요청
    # call(sys.argv[1])

    # 인포스탁 크롤링
    call('http://localhost/scrap/infostock/ThemeAllAllowed.html')

    # main()