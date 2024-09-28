#!C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe
#-*- coding: utf-8 -*-

import requests
from bs4 import BeautifulSoup
import pymysql

# 특수문자 처리
def special_char(str):
    pt = str.replace('"','\\"').replace("'", "\\'")
    pt = pt.replace('∼','~')
    pt = pt.replace('＆','&')

    # pt = re.sub('[a-zA-Z]' , '', str)
    # pt = re.sub('[\{\}\[\]\/?.,;:|\)*~`!^\-_+<>@\#$%&\\\=\(\'\"]', '', pt)
    return pt


# 인포스탁 데이터 가져오기
##---------------------------------------------------------------------- 
def call(link, file):
    conn = pymysql.connect(host='siriens.mycafe24.com', user='siriens', password='hosting1004!', db='siriens', charset='utf8')
    cur = conn.cursor()

    href = requests.get(link, headers={'User-agent': 'Mozilla/5.0'})
    soup = BeautifulSoup(href.content.decode('utf-8','replace'), "html.parser")

    # 테마 / 테마설명 구하기
    str1 = soup.find("div", class_="detailTit")
    theme = str1.text
    # print(theme)

    str2 = soup.find("div", class_="detailCon")
    theme_comment = special_char(str2.text)
    # print(theme_comment)

    sql = f"REPLACE INTO rawdata_infostock_theme(str1, str2, str3) VALUES ('Theme_info', '{theme}', '{theme_comment}')"
    file.write(sql)
    file.write('\n')
    cur.execute(sql)
    
    # 테마이력 구하기
    t_his = soup.find("article", class_="listTable themeHistory relateTable")
    if t_his is not None:
        for history in t_his.find_all("tr"):
            if history.text != '일자 내용':
                date = history.find("td", class_="big")
                date = date.text.replace('. ', '').strip()
                # print(date)
                
                d_con = history.find("div", class_="txtBox line-2")
                content = special_char(d_con.text)
                # print(content)

                sql = f"REPLACE INTO rawdata_infostock_theme(str1, str2, str3, str4) VALUES ('Theme_history', '{theme}', '{date}', '{content}')"
                file.write(sql)
                file.write('\n')
                cur.execute(sql)

                for stock in d_con.find_all("a"):
                    stock_nm = stock.text
                    # print(stock_nm)
                    stock_cd = stock.attrs['href'].split('code=')[1]
                    # print(stock_cd)

                    sql = f"REPLACE INTO rawdata_infostock_theme(str1, str2, str3, str5, str6) VALUES ('Theme_history', '{theme}', '{date}', '{stock_cd}', '{stock_nm}')"
                    file.write(sql)
                    file.write('\n')
                    cur.execute(sql)

    # 테마종목 구하기
    t_stock = soup.find("article", class_="listTable type-issue")
    for stock in t_stock.find_all("tr"):
        if stock.text != '종목명 테마기업 요약':
            s = stock.find_all("p", class_="stockInfoMobile")
            stock_nm = s[0].text
            # print(stock_nm)

            stock_cd = s[1].text.replace('(','').replace(')','')
            # print(stock_cd)
            
            st_comment = stock.find("div", class_="issueCon stockInfoMobile").text.strip()
            st_comment = special_char(st_comment)
            # print(st_comment)

            sql = f"REPLACE INTO rawdata_infostock_theme(str1, str2, str5, str6, str7) VALUES ('Theme_stock', '{theme}', '{stock_cd}', '{stock_nm}', '{st_comment}')"
            file.write(sql)
            file.write('\n')
            cur.execute(sql)

    # 테마마스터 테이블 등록
        
        #기등록된 테마에 대해 테마코드 등록
        sql1 =  """
                UPDATE rawdata_infostock_theme A 
                 INNER JOIN theme B
                   ON B.theme_nm = A.str2
                  SET A.theme_cd = B.theme_cd
                 WHERE A.theme_cd IS NULL
                  AND A.proc_yn = 'N'
                 """
        cur.execute(sql1)

        #신규주 테마 (I9000 이상) 제외 하고 코드 생성
        sql2 =  """
                INSERT 
                  INTO theme (theme_cd, theme_nm, theme_content, theme_grp, create_dtime)
                SELECT concat('I', lpad(@RNUM:=@RNUM+nxtval,4,'0')), A.str2, A.str3, 'INFOSTOCK', now() 
                  FROM rawdata_infostock_theme A
                     , (SELECT @RNUM:=1) R
                     , (SELECT SUBSTR(MAX(theme_cd),2,5) nxtval FROM theme WHERE theme_cd < 'I9000' ) C 
                 WHERE A.theme_cd IS NULL 
                   AND A.str1 = 'Theme_info'
                   AND A.proc_yn = 'N'
                """
        cur.execute(sql2)

        #신규테마코드 적용하기
        sql3 =  """
                UPDATE rawdata_infostock_theme A 
                 INNER JOIN theme B
                   ON B.theme_nm = A.str2
                  SET A.theme_cd = B.theme_cd
                 WHERE A.theme_cd IS NULL
                  AND A.proc_yn = 'N'
                 """
        cur.execute(sql3)
        
        #일별 theme history 생성
        sql4 =  """
                REPLACE 
                   INTO siriens_infostock_theme (report_date, theme_cd, issue, create_dtime) 
                 SELECT str3, theme_cd, str4, now() 
                   FROM rawdata_infostock_theme 
                  WHERE str1 = 'Theme_history' 
                    AND str4 IS NOT NULL 
                    AND proc_yn = 'N'
                """
        cur.execute(sql4)

        # 상품-테마마스터 등록
        sql5 =  """
                REPLACE 
                   INTO stock_theme (code, theme_cd, content, create_dtime) 
                 SELECT str5, theme_cd, str7, now() 
                   FROM rawdata_infostock_theme 
                  WHERE str1 = 'Theme_stock' 
                    AND proc_yn = 'N'
                """
        cur.execute(sql5)

        # 임시테이블 처리여부 'Y' 업데이트
        sql6 =  """
                UPDATE rawdata_infostock_theme SET proc_yn = 'Y' WHERE proc_yn = 'N'
                """
        cur.execute(sql6)

    cur.execute('commit;')
    conn.close()

def main():
    print('처리중....................')
    file=open("infostock.txt", "w", encoding="utf-8")
    
    # 인포스탁 크롤링
    for i in range(910,918,1):
        link = 'http://localhost/scrap/infostock/ThemeHistory_' +  str(i) + '.html'
        print('********* ' + str(i) + ' ********************************************************************************************************')
        call(link, file)
    
    file.close()
    print('End!!!')
##----------------------------------------------------------------------

if __name__ == "__main__":
    # 인포스탁 테마이력 크롤링 #신규테마 등록 시 작업 필요
    main()