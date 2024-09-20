import os
import pymysql
import re
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

# 저장된 폴더 지정
folder_path = 'D:/Obsidian/Trader Sophia/10 Database/Stock/'

# 파일명을 저장한 텍스트 문서의 경로
file_list_path = 'E:/Project/202410/www/PyObsidian/stock_dbup_list.txt'  # 예: file_list.txt에 처리할 파일명을 저장

# 파일명 목록을 읽어옵니다.
with open(file_list_path, 'r', encoding='utf-8') as file_list:
    # 파일명을 한 줄씩 읽어 리스트에 저장하고, 각 파일명에 '.md'를 붙입니다.
    filenames_to_process = [line.strip() + '.md' for line in file_list if line.strip()]

# 파일명 목록에 있는 파일만 처리
k = 0
for filename in filenames_to_process:
    # 파일 경로 생성
    file_path = os.path.join(folder_path, filename)
    
    # 파일이 실제로 존재하는지 확인
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        continue

    print('#####'+ str(k) +'##########################################################')
    k=k+1
    print(file_path)

    # 파일 읽기
    with open(file_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()
        
    # 메타데이터 건너뛰기 -- 메타데이터 제거되어 주석처리 2023.11.19 
    # while lines and lines[0].strip() == '---':
    #     lines.pop(0)
    # while lines and lines[0].strip() != '---':
    #     lines.pop(0)
    # if lines and lines[0].strip() == '---':
    #     lines.pop(0)
        
    # 파일이 비어있는 경우 건너뛰기
    if not lines:
        continue
    
    # 파일 내용 출력
    # print(f"File content: {lines}")

    # 종목코드 추출 (첫 번째 줄)
    split_line = lines[0].split(": ")
    if len(split_line) < 2:
        continue  # 콜론 뒤에 내용이 없는 경우 건너뛰기
    stock_code = split_line[1].strip()
    
    print(stock_code)

    # 나머지 줄을 순회하며 정보 추출
    keywords = []
    summaries = []
    histories = []
    articles = []
    company_info = []
    themes = []
    others = []
    current_section = None
    for line in lines[1:]:
        if "기업 정보:" in line:
            current_section = 'company_info'
        elif "종목 키워드:" in line:
            current_section = 'keyword'
        elif "종목 테마(키워드):" in line:
            current_section = 'theme'
        elif "종목 요약:" in line:
            current_section = 'summary'
        elif "종목 히스토리:" in line:
            current_section = 'history'
        elif "종목 뉴스:" in line:
            current_section = 'article'
        elif "추가 정보:" in line:
            current_section = 'other'
        elif current_section == 'company_info':
            # "ssl.pstatic.net/imgfinance/chart"을 포함하지 않는 라인만 추가
            if "ssl.pstatic.net/imgfinance/chart" not in line:
                company_info.append(line.strip())
        elif current_section == 'keyword':
            keywords.append(line.strip())
        elif current_section == 'theme':
            themes.append(line.strip())
        elif current_section == 'summary':
            summaries.append(line.strip())
        elif current_section == 'history':
            histories.append(line.strip())
        elif current_section == 'article':
            articles.append(line.strip())
        elif current_section == 'other':
            others.append(line.strip())
    
    print(company_info)
    print(keywords)
    print(themes)
    print(summaries)
    # print(histories)
    print(articles)
    print(others)

    # 데이터베이스 업데이트: 기존 데이터 삭제 후 등록
    try:
        #stock_info
        sql_query = f"DELETE FROM stock_info WHERE code='{stock_code}'"
        # print(f"Executing: {sql_query}")
        cursor.execute(sql_query)
        
        company_info = company_info[:-2]
        company_info_str = '\r\n'.join(company_info)

        if company_info :
            sql_query = "INSERT INTO stock_info (code, category, content, remark, create_dtime) VALUES (%s, 'Company_Info', %s, 'Obsidian', now())"
            # print(f"Executing: {sql_query % (stock_code, company_info_str)}")
            cursor.execute(sql_query, (stock_code, company_info_str))

        if others and others[-1].strip() == '':
            others = others[:-1]
        others_str = '\r\n'.join(others)

        if others :
            sql_query = "INSERT INTO stock_info (code, category, content, remark, create_dtime) VALUES (%s, 'Others', %s, 'Obsidian', now())"
            # print(f"Executing: {sql_query % (stock_code, others_str)}")
            cursor.execute(sql_query, (stock_code, others_str))

        #stock_keyword
        sql_query = f"DELETE FROM stock_keyword WHERE code='{stock_code}'"
        # print(f"Executing: {sql_query}")
        cursor.execute(sql_query)
        
        for keyword in keywords:
            # 키워드 앞의 '- ' 제거
            keyword = keyword.lstrip('- ').strip()
            # 빈 문자열이나 '---'는 건너뛰기
            if keyword and keyword != '---':
                sql_query = f"INSERT INTO stock_keyword (code, category, keyword, remark, create_dtime) VALUES ('{stock_code}', 'Keyword', '{keyword}', 'Obsidian', now())"
                # print(f"Executing: {sql_query}")
                cursor.execute(sql_query)

        for theme in themes:
            # 테마 앞의 '- ' 제거
            theme = theme.lstrip('- ').strip()
            # 빈 문자열이나 '---'는 건너뛰기
            if theme and theme != '---':
                sql_query = f"INSERT INTO stock_keyword (code, category, keyword, remark, create_dtime) VALUES ('{stock_code}', 'Theme', '{theme}', 'Obsidian', now())"
                # print(f"Executing: {sql_query}")
                cursor.execute(sql_query)

        #stock_comment
        sql_query = f"DELETE FROM stock_comment WHERE code='{stock_code}'"
        # print(f"Executing: {sql_query}")
        cursor.execute(sql_query)

        for summary in summaries:
            # 요약 앞의 '- ' 제거
            summary = summary.lstrip('- ').strip()
            # 빈 문자열이나 '---'는 건너뛰기
            if summary and summary != '---':
                sql_query = "INSERT INTO stock_comment (code, comment, remark, create_dtime) VALUES (%s, %s, 'Obsidian', now())"
                # print(f"Executing: {sql_query % (stock_code, summary)}")
                cursor.execute(sql_query, (stock_code, summary))

        # 시그널은 종목별로가 아니라 시.리. 시장정리를 가져오는 것으로 변경
        # #stock_history
        # sql_query = f"DELETE FROM stock_history WHERE code='{stock_code}'"
        # # print(f"Executing: {sql_query}")
        # cursor.execute(sql_query)

        # for history in histories:
        #     # 요약 앞의 '- ' 제거
        #     history = history.lstrip('- ').strip()
        #     # 빈 문자열이나 '---'는 건너뛰기
        #     if history and history != '---':
        #         sql_query = "INSERT INTO stock_history (code, history, remark, create_dtime) VALUES (%s, %s, 'Obsidian', now())"
        #         # print(f"Executing: {sql_query % (stock_code, history)}")
        #         cursor.execute(sql_query, (stock_code, history))

        #signals
        # 기사 추가 등록: 기존에 등록되지 않은 링크는 추가 등록, 아니면 기사 내용 업데이트
        i = 0
        while i < len(articles):
            match = re.match(r"\((.*?)\) \[(.*?)\]\((.*?)\)", articles[i])
            if match is not None:  # 패턴이 일치하는 경우만 처리
                date, title_link, link = match.groups()
                content = ''
                if i + 1 < len(articles) and articles[i+1].strip():  # 다음 요소가 있고 내용이 있는 경우
                    content = articles[i+1]
                    i += 1  # 내용을 처리했으므로 인덱스를 증가
        
                sql_query = "SELECT * FROM signals WHERE code=%s AND link=%s"
                # print(f"Executing: {sql_query % (stock_code, link)}")
                cursor.execute(sql_query, (stock_code, link))
        
                if cursor.fetchone() is None:  # 해당 기사가 없는 경우만 추가
                    sql_query = "INSERT INTO signals (code, news_date, title, link, content) VALUES (%s, %s, %s, %s, %s)"
                    # print(f"Executing: {sql_query % (stock_code, date, title_link, link, content)}")
                    cursor.execute(sql_query, (stock_code, date, title_link, link, content))
                else:  # 해당 기사가 있는 경우 date와 content 업데이트
                    sql_query = "UPDATE signals SET news_date=%s, content=REPLACE(%s, '<br>','\r\n') WHERE code=%s AND link=%s"
                    # print(f"Executing: {sql_query % (date, content, stock_code, link)}")
                    cursor.execute(sql_query, (date, content, stock_code, link))
            # else:
                # print(f"Pattern did not match for article: {articles[i]}")
            i += 1  # 다음 요소로 이동

    except Exception as e:
        print(f"An error occurred: {e}")

# 데이터베이스 변경 사항 저장
db.commit()

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결 종료
db.close()