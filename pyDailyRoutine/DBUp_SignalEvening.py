#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
# -*- coding: utf-8 -*-

import requests
import pandas as pd
from bs4 import BeautifulSoup
from datetime import datetime
import pymysql
import re
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

def extract_title(title):
    # 불필요한 날짜, "장 전 뉴스 Check", "Signal Evening" 등의 부분을 제거
    cleaned_title = re.sub(r'^\d{4}\.\d{2}\.\d{2}\.\(.\)\s*(\[장 전 뉴스 Check\]|Signal Evening)\s*', '', title)
    
    # 제일 바깥의 따옴표를 제거 (양 끝에 있을 경우)
    if cleaned_title.startswith('"') and cleaned_title.endswith('"'):
        cleaned_title = cleaned_title[1:-1]

    # 최종적으로 정리된 제목 반환
    return cleaned_title.strip()

# 시그널리포트 가져오기
##---------------------------------------------------------------------- 

def main():
    print('처리중....................')
    conn = pymysql.connect(host='siriens.mycafe24.com', user='siriens', password='hosting1004!', db='siriens', charset='utf8')
    cur = conn.cursor()
    
    #파일명이 '20240901' 형식이라 문자형 일자 구해오기
    sql = "SELECT MAX(date_str) AS date_str FROM calendar a WHERE date <= now()"
    # sql = "SELECT date_str AS date_str FROM calendar a WHERE date = '2024-09-24'"
    
    df = pd.read_sql(sql, conn)
    date_str = df['date_str'].values[0].decode('utf-8')

    ## market_report 장전뉴스, 시그널이브닝 타이틀 넣어주기
    # 이브닝 리포트 링크
    evening_link = 'http://localhost/scrap/' + date_str + '.html'
    print(f"Evening link: {evening_link}")
    
    evening_href = requests.get(evening_link, headers={'User-agent': 'Mozilla/5.0'})
    evening_soup = BeautifulSoup(evening_href.content.decode('utf-8', 'replace'), "html.parser")
    
    # 이브닝 리포트 타이틀 추출
    evening_title = evening_soup.find("title")
    if evening_title:
        evening_title_text = evening_title.text
        # 제목에서 필요없는 부분 제거 (날짜와 " " 사이의 내용만 가져오기)
        evening_cleaned_title = extract_title(evening_title_text)
        print(f"Evening Report Title: {evening_cleaned_title}")

    # 모닝 리포트 링크
    morning_link = 'http://localhost/scrap/' + date_str + 'P.html'
    print(f"Morning link: {morning_link}")
    
    morning_href = requests.get(morning_link, headers={'User-agent': 'Mozilla/5.0'})
    morning_soup = BeautifulSoup(morning_href.content.decode('utf-8', 'replace'), "html.parser")

    # 모닝 리포트 타이틀 추출
    morning_title = morning_soup.find("title")
    if morning_title:
        morning_title_text = morning_title.text
        # 제목에서 필요없는 부분 제거 (날짜와 " " 사이의 내용만 가져오기)
        morning_cleaned_title = extract_title(morning_title_text)
        print(f"Morning Report Title: {morning_cleaned_title}")

    # 첫 번째 뉴스 제목과 링크 추출

    module_text = morning_soup.find('div', {'class': 'se-module se-module-text'})
    if module_text:
        first_news_tag = module_text.find('p', {'class': 'se-text-paragraph'})
        if first_news_tag:
            first_news_a = first_news_tag.find('a', href=True)  # 해당 <p> 태그 안의 첫 번째 <a> 태그를 찾음
            if first_news_a:
                first_news = first_news_a.text.strip()  # 뉴스 제목
                first_news_link = first_news_a['href'].split('#')[0]  # 뉴스 링크에서 # 이후는 제거
            else:
                first_news = first_news_tag.text.strip()  # <a> 태그가 없는 경우 <p> 태그의 텍스트 사용
                first_news_link = 'No Link'
        else:
            first_news = 'No News'
            first_news_link = 'No Link'
    else:
        first_news = 'No News'
        first_news_link = 'No Link'

    ## 시그널 이브닝 크롤링 저장 처리
    # 이브닝 일자 셋팅
    
    date_obj = datetime.strptime(date_str, '%Y%m%d') # 문자열을 datetime 객체로 변환
    dt = date_obj.strftime('%Y-%m-%d') # datetime 객체를 원하는 형식의 문자열로 변환

    # 데이터베이스에 이브닝 리포트 타이틀과 모닝 리포트 타이틀, 첫 번째 뉴스 저장
    sql = '''
        INSERT INTO market_report (date, morning_report_title, morning_news_title, morning_news_link, evening_report_title)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            morning_report_title=%s,
            morning_news_title=%s,
            morning_news_link=%s,
            evening_report_title=%s
    '''
    # SQL 실행
    cur.execute(sql, (
        dt, morning_cleaned_title, first_news, first_news_link, evening_cleaned_title, 
        morning_cleaned_title, first_news, first_news_link, evening_cleaned_title
    ))
    conn.commit()


    # 제목 ", ' 에 \ 붙여주기 && 특수문자 제거
    ec_ptt = special_char(evening_title_text)

    # 본문 읽어와서 DB 등록하기
    # 사용변수 일부 초기화
    hf = '';
    file=open("signal.txt", "w", encoding="utf-8")
    
    for p_tag in evening_soup.find_all("p"):
        if "네이버 톡톡" in p_tag.text:
            p_tag.decompose()

    for content in evening_soup.find_all("p", class_="se-text-paragraph se-text-paragraph-align-"):

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
                    print(st)
                    for std in st.split(',') :
                        std.split('(')
                        print(std.split('(')[0].split()[0])
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

            sql = f"REPLACE INTO signal_evening(page_date, page_fg, page_title, signal_grp, grouping, stocks, stock, link, title, news_date, del_yn, save_yn, today_pick,  create_date) "\
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
                sql = f"UPDATE signal_evening"\
                    f"   SET crawling_title = '{cr_title}'"\
                    f"     , crawling_name  = '{cnd[1]}'"\
                    f"     , crawling_date = CASE WHEN LENGTH('{cnd[2]}') = 8 THEN STR_TO_DATE('{cnd[2]}', '%Y%m%d') ELSE NULL END"\
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

                sql = f"UPDATE signal_evening SET content = '{ctt}' WHERE page_date = '{dt}' AND link = '{hf}'"
                file.write(sql)
                file.write('\n')
                cur.execute(sql)
                cur.execute('commit;')

                # file.write(ctt)
                # file.write('\n')
                ctt = ''
                hf  = ''

    sql = f"UPDATE signal_evening A"\
            f" INNER JOIN ( SELECT Y.cd, Y.nm, Y.nm_sub1, X.link "\
            f"                FROM (SELECT SUBSTR(link_1, 1, INSTR(link_1,'/')-1) AS link_2, link "\
            f"                        FROM (SELECT link, replace(replace(link,'http://', ''),'https://', '') AS link_1 "\
            f"                                FROM signal_evening "\
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

    # 크롤링한 데이터 반영 안정화 되면 소스 수정. 테스트 중에는 아래 로직 잠시 주석처리. 2023.07.30
                    # # 이브닝 등록 완료 후 기등록 데이터와 비교, 보완처리
                    # # signals에 등록된 뉴스의 경우 데이터 불러오기
                    # sql = f"UPDATE signal_evening A"\
                    #         f" INNER JOIN signals B"\
                    #         f"    ON B.link      = A.link"\
                    #         f"   SET A.news_date    = B.news_date"\
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


    # 크롤링한 데이터 반영 안정화 되었다 판단하여 수동처리 로직 다시 코드화. 2024.06.23
    # 타이틀 누락건 업데이트 처리
    sql = f"UPDATE signal_evening "\
            f"SET title = crawling_title "\
            f"WHERE page_fg = 'E' "\
            f"AND page_date = '{dt}' "\
            f"AND title = '' "\
            f"AND (crawling_title IS NOT NULL OR crawling_title != '')"

    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()

    # date, time, name을 signals 데이터에 업데이트
    sql = f"""
            UPDATE signals A
            INNER JOIN (
                SELECT * FROM signal_evening
                WHERE page_fg = 'E'
                AND page_date = '{dt}'
                AND crawling_date IS NOT NULL
            ) B
            ON B.link = A.link
            SET A.news_date = B.crawling_date
            """
    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()

    # date, time, name을 signals 데이터에 업데이트
    sql = f"""
            UPDATE signals A
            INNER JOIN (
                SELECT * FROM signal_evening
                WHERE page_fg = 'E'
                AND page_date = '{dt}'
                AND crawling_time IS NOT NULL
                AND crawling_time != ''
            ) B
            ON B.link = A.link
            SET A.news_time = B.crawling_time 
            """
    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()

    # date, time, name을 signals 데이터에 업데이트
    sql = f"""
            UPDATE signals A
            INNER JOIN (
                SELECT * FROM signal_evening
                WHERE page_fg = 'E'
                AND page_date = '{dt}'
                AND crawling_name IS NOT NULL
                AND crawling_name != ''
            ) B
            ON B.link = A.link
            SET A.writer = B.crawling_name 
            """
    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()


    # signals 데이터 기준으로 signal_evening 업데이트
    sql = f"UPDATE signal_evening A "\
            f"INNER JOIN signals B "\
            f"ON B.link = A.link "\
            f"SET A.news_date = B.news_date, "\
            f"A.news_time = B.news_time, "\
            f"A.title = (CASE WHEN A.title = '' THEN B.title ELSE A.title END), "\
            f"A.publisher = B.publisher, "\
            f"A.writer = B.writer, "\
            f"A.code = B.code, "\
            f"A.stock = B.name, "\
            f"A.content = (CASE WHEN A.content IS NULL THEN B.content WHEN A.content = '' THEN B.content ELSE A.content END), "\
            f"A.exists_yn = 'Y', "\
            f"A.confirm_fg = B.confirm_fg, "\
            f"A.signal_id = B.signal_id "\
            f"WHERE  page_date = '{dt}' "\
            f"AND page_fg = 'E'"

    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()

    # 크롤링한 데이터는 뉴스 확인 처리 안해도 되도록 업데이트
    sql = f"UPDATE signal_evening "\
            f"SET confirm_fg = '2', "\
            f"news_date = crawling_date, "\
            f"news_time = crawling_time "\
            f"WHERE  page_date = '{dt}' "\
            f"AND page_fg = 'E' "\
            f"AND (confirm_fg != '1' OR confirm_fg IS NULL) "\
            f"AND crawling_date IS NOT NULL"

    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    conn.commit()

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