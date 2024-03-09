import os
import pymysql
import configparser
from datetime import datetime

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

# 커서 생성
cursor = db.cursor()

#****************************************************************************
# 조건값 설정 (True: 파일에서 종목명 읽어오기, False: 쿼리로 종목 가져오기)
# use_file = True  # stock_dbdown_list.txt
use_file = False
#****************************************************************************

if use_file:
    # 텍스트 파일에서 종목명 읽어오기
    with open('E:/Project/202410/www/PyObsidian/stock_dbdown_list.txt', 'r', encoding='utf-8') as f:
        stock_list = [line.strip() for line in f if line.strip()]
else:
    # 쿼리에서 종목 가져오기
    query = f'''SELECT code as stock_code, name as stock_name
                FROM stock A
                WHERE EXISTS ( SELECT code
                                FROM (SELECT distinct code FROM signals UNION
                                      SELECT distinct code FROM stock_comment UNION
                                      SELECT distinct code FROM stock_keyword ) B
                                WHERE B.code = A.code )'''
    cursor.execute(query)
    stock_list = cursor.fetchall()

# 저장할 폴더 지정
# folder_path = 'D:/Obsidian/Trader Sophia/99 Inbox/stock'
folder_path = 'D:/Obsidian/Trader Sophia/10 Database/Stock'

for stock_name in stock_list:
    # 종목 코드 가져오기
    if use_file:
        cursor.execute(f"SELECT code as stock_code, name as stock_name FROM stock WHERE name='{stock_name}'")
        stock_code_tuple = cursor.fetchone()
        if not stock_code_tuple:
            print(f"{stock_name}에 해당하는 종목 코드를 찾을 수 없습니다.")
            continue
        print(stock_code_tuple)  # 추가된 코드

        # 바이트를 문자열로 디코딩
        stock_code = stock_code_tuple[0].decode('utf-8')
        stock_name = stock_code_tuple[1].decode('utf-8')
    else:
        # 바이트를 문자열로 디코딩
        stock_code = stock_name[0].decode('utf-8')
        stock_name = stock_name[1].decode('utf-8')

    # 기업 정보 가져오기
    cursor.execute(f"SELECT content FROM stock_info WHERE code='{stock_code}' AND category = 'Company_Info'")
    company_info = cursor.fetchone()
    
    # 종목 키워드 가져오기
    cursor.execute(f"SELECT CONCAT('- ',keyword) keyword FROM stock_keyword WHERE code='{stock_code}' AND category = 'Keyword' ORDER BY id")
    stock_keywords = cursor.fetchall()

    # 종목 테마(키워드) 가져오기
    cursor.execute(f"SELECT CONCAT('- ',keyword) keyword FROM stock_keyword WHERE code='{stock_code}' AND category = 'Theme' ORDER BY id")
    stock_themes = cursor.fetchall()

    # 종목 요약 가져오기
    cursor.execute(f"SELECT CONCAT('- ',comment) comment FROM stock_comment  WHERE code='{stock_code}' ORDER BY id")
    stock_summaries = cursor.fetchall()

    # 종목 히스토리 가져오기
    query = f'''
    SELECT CONCAT('- (', STR_TO_DATE(h.date, '%Y%m%d'), ')') date
        , REPLACE(REPLACE(REGEXP_REPLACE(h.history, '^[0-9]+\.|^\W+', ' '),'[[','['),']]',']') AS history 
        , CONCAT(' [[', h.remark, ']]') remark
    FROM stock_history h
    JOIN stock s 
    ON h.history like concat ('%', concat('[[', s.name, ']]'), '%') 
    WHERE s.code = '{stock_code}'
    ORDER BY h.report_date;
    '''
    cursor.execute(query)
    stock_history = cursor.fetchall()

    # 관련 기사 가져오기
    query = f"""
    SELECT STR_TO_DATE(date, '%Y%m%d') date
        , CONCAT('[',REPLACE(REPLACE(title, '[',''),']','_'),'](',link,')') title
        , REPLACE(content,"\r\n", "<br>") content
    FROM signals
    WHERE code='{stock_code}'
    ORDER BY date DESC
    """
    cursor.execute(query)
    articles = cursor.fetchall()

    # 추가 정보 가져오기
    cursor.execute(f"SELECT content FROM stock_info WHERE code='{stock_code}' AND category = 'Others'")
    others = cursor.fetchone()

    # 파일 이름 설정 (주식명.txt)
    filename = f"{stock_name}.md"
    
    # 파일 경로 생성
    file_path = os.path.join(folder_path, filename)
    
    # 파일 쓰기
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(f"종목코드: {stock_code}\n")
        f.write("\n---\n") # 구분선 추가
    
        f.write(f"기업 정보:\n")

        # 기업 차트 정보 추가
        company_info_str = f'![](https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{stock_code}.png?sidcode=1705826920773)\n\n\n'
        f.write(f"{company_info_str}\n")

        # 기업 정보가 있는 경우에만 쓰기
        if company_info:
            company_info = company_info[0].decode('utf-8')
            company_info_str = company_info.replace('\r\n\r\n', '\r\n') # 연속된 두개의 빈 줄을 하나의 빈 줄로 변경합니다.
            company_info_str = company_info.strip('\n') # 문서 시작과 끝에 있는 빈 줄을 제거합니다.
            f.write(f"{company_info_str}\n")
        # else :
            # company_info = '▷ 시총 \n▷ 최대주주 및 특수관계인 지분 - \n▷ 재무추이\n- 2022년 매출액 영업이익 \n- 2021년 매출액 영업이익 \n- 2020년 매출액 영업이익 \n\n▷ 부채비율 % 유보율 % (2023년 6월 기준) \n'
            # company_info_str = company_info + '▷ 미상환 전환사채 및 신주인수권부사채 등 발행현황 \n▷ 매출비율 : \n▷ 전자공시 : [반기보고서/]() \n▷ 홈페이지 : '

        f.write("\n---\n") # 구분선 추가
        f.write(f"종목 키워드:\n")
        # 종목 키워드가 있는 경우에만 쓰기
        if stock_keywords:
            keywords = '\n'.join([keyword[0].decode('utf-8') if keyword[0] is not None else '' for keyword in stock_keywords])
            f.write(f"{keywords}\n")

        f.write("\n---\n") # 구분선 추가
        f.write(f"종목 테마(키워드):\n")
        # 종목 테마(키워드)가 있는 경우에만 쓰기
        if stock_themes:
            themes = '\n'.join([theme[0].decode('utf-8') if theme[0] is not None else '' for theme in stock_themes])
            f.write(f"{themes}\n")

        f.write("\n---\n") # 구분선 추가
        f.write(f"종목 요약:\n")

        # 종목 요약이 있는 경우에만 쓰기
        if stock_summaries:
            summaries = '\n'.join([summary[0].decode('utf-8') if summary[0] is not None else '' for summary in stock_summaries])
            f.write(f"{summaries}\n")

        f.write("\n---\n") # 구분선 추가
        f.write(f"종목 뉴스:\n")

        if articles:
            for article in articles:
                date, title, content = article
                date = date.strftime('%Y-%m-%d') if date is not None else ''
                title = title.decode('utf-8') if title is not None else ''
                content = content.decode('utf-8') if content is not None else ''
                f.write(f"({date}) ")
                f.write(f"{title}\n")
                f.write(f"{content}\n")
                f.write("\n")  # 기사 간에 공백 추가

        f.write("\n---\n") # 구분선 추가
        f.write(f"종목 히스토리:\n")

        # 종목 히스토리가 있는 경우에만 쓰기
        # if stock_history:
        #     histories  = '\n'.join([history[0].decode('utf-8') if history[0] is not None else '' for history in stock_history])
        #     f.write(f"{histories}\n")

        # 종목 히스토리가 있는 경우에만 쓰기
        if stock_history:
            histories  = '\n'.join([f"{history[0].decode('utf-8') if history[0] is not None else ''} {history[1].decode('utf-8') if history[1] is not None else ''} {history[2].decode('utf-8') if history[2] is not None else ''}" for history in stock_history])
            f.write(f"{histories}\n")

        f.write("\n---\n") # 구분선 추가
        f.write(f"추가 정보:\n")
        # 기업 정보가 있는 경우에만 쓰기
        if others:
            others = others[0].decode('utf-8')
            f.write(f"{others}\n")

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결 종료
db.close()