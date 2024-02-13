# md 파일에서 # 시장 정리 이후 텍스트를 읽어서 테이블에 등록하는 프로그램

# 필요한 모듈을 임포트합니다.
import re # 정규식을 사용하기 위한 모듈
import os # 파일과 디렉토리를 다루기 위한 모듈
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈
from datetime import datetime # 날짜와 시간을 다루기 위한 모듈

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 커서 생성
cursor = db.cursor()

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")


# 폴더 경로를 지정합니다.
folder_path = 'D:/Obsidian/Trader Sophia/10 Database/WatchList'

# 특정 파일만 처리할 것인지 플래그
only_specific_file = False
# only_specific_file = True

# 특정 파일 이름 
specific_file_name = "0 당일관종☆.md"
# specific_file_name = "정책.md"

# 전체 파일을 처리하는 경우, 관종 삭제 후 재등록 되도록 한다.
if only_specific_file != True:
    sql = f"DELETE FROM watchlist_sophia"
    print(sql)
    cursor.execute(sql)

# 폴더 내의 모든 파일을 읽습니다.
for file_name in os.listdir(folder_path):

    # 특정 파일만 처리하는 경우
    if only_specific_file:
        if file_name != specific_file_name:
            continue

    # 파일의 절대 경로를 구합니다.
    file_path = os.path.join(folder_path, file_name)
    # 파일을 엽니다.
    with open(file_path, encoding='utf-8') as f:
        # 파일의 내용을 읽습니다.
        content = f.read()
        # 파일의 이름에서 확장자를 제외하고 sector로 사용합니다.
        sector = file_name.split('.')[0]
        
        # 특정 파일만 처리하는 경우
        if only_specific_file:
            # 기존 섹터를 삭제한다.
            sql = f"DELETE FROM watchlist_sophia WHERE sector = '{sector}'"
            print(sql)
            cursor.execute(sql)
        
        # 파일의 내용을 줄 단위로 분리합니다.
        lines = content.split('\n')
        
        # theme, category, name, sort_stock, talent_fg, news_title, news_link을 초기화합니다.
        theme = ''
        category = ''
        name = ''
        sort_theme = 0
        sort_stock = 0
        talent_fg = ''
        news_title = ''
        news_link = ''
        news_content = ''
        comment = ''

        # 각 줄에 대해 처리합니다.
        for line in lines:
            # 줄이 비어있다면 스킵합니다.
            if line == '':
                continue

            # 줄이 #으로 시작한다면 theme으로 사용합니다.
            if line.startswith('# '):
                theme = line[2:]
                # sort_theme를 1 증가시킵니다.
                sort_theme += 1
                # theme이 바뀌면 sort_stock를 0으로, category 는 빈값으로 초기화합니다.
                sort_stock = 0
                category = ''
            # 줄이 ##으로 시작한다면 category로 사용합니다.
            elif line.startswith('## '):
                category = line[3:]
                # sort_theme를 1 증가시킵니다.
                sort_theme += 1
                # category가 바뀌면 sort_stock를 0으로 초기화합니다.
                stock_sort = 0
            # 줄이 - [[으로 시작한다면 name으로 사용합니다.
            elif line.startswith('- [['):
                # name을 추출합니다.
                name = re.search('\[\[(.*?)\]\]', line).group(1)
                talent_fg = re.search('\[\[(.*?)\]\](.*)', line).group(2)
                talent_fg = talent_fg.replace('(','').replace(')','')

                # sort_stock를 1 증가시킵니다.
                sort_stock += 1

                # news_title과 news_link을 초기화합니다.
                news_title = ''
                news_link = ''
                
                # 종목 코드를 구해옵니다.
                sql = f"SELECT code FROM stock WHERE name = '{name}'"
                cursor.execute(sql)
                code = cursor.fetchone()[0].decode('utf-8')
                
                # 테이블에 데이터를 삽입합니다.
                sql = "REPLACE INTO watchlist_sophia (sector, theme, category, code, name, sort_theme, sort_stock, talent_fg, news_title, news_link, create_dtime) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
                val = (sector, theme, category, code, name, sort_theme, sort_stock, talent_fg, news_title, news_link, datetime.now())
                print(f"Executing: {sql % (sector, theme, category, code, name, sort_theme, sort_stock, talent_fg, news_title, news_link, datetime.now())}")
                cursor.execute(sql, val)

            # 줄이 - [으로 시작한다면 news_title과 news_link으로 사용합니다.
            elif line.startswith('	- ['):
                # news_title과 news_link을 추출합니다.
                news_title = re.search('\[(.*?)\]\(', line).group(1)
                news_link = re.search('\(([^)]*)\)\s*$', line)
                if news_link is not None:
                    news_link = news_link.group(1)
                else:
                    news_link = line
                
                # 테이블에 뉴스정보를 업데이트 한다.
                sql = "UPDATE watchlist_sophia SET news_title = %s, news_link= %s WHERE sector = %s AND theme = %s AND category = %s AND code = %s"
                val = (news_title, news_link, sector, theme, category, code)
                print(f"Executing: {sql % (news_title, news_link, sector, theme, category, code)}")
                cursor.execute(sql, val)

            # 줄이 '	- ' 으로 시작한다면 comment으로 사용합니다.
            elif line.startswith('	- '):
                comment = line.replace('	- ','')
                
                # 테이블에 뉴스정보를 업데이트 한다.
                sql = "UPDATE watchlist_sophia SET comment = %s WHERE sector = %s AND theme = %s AND category = %s AND code = %s"
                val = (comment, sector, theme, category, code)
                print(f"Executing: {sql % (comment, sector, theme, category, code)}")
                cursor.execute(sql, val)

            # 줄이 '        - ' 으로 시작한다면 news_content로 사용합니다.
            elif line.startswith('        - '):
                news_content = line.replace('        - ','')
                
                # 테이블에 뉴스정보를 업데이트 한다.
                sql = "UPDATE watchlist_sophia SET news_content = %s WHERE sector = %s AND theme = %s AND category = %s AND code = %s"
                val = (news_content, sector, theme, category, code)
                print(f"Executing: {sql % (news_content, sector, theme, category, code)}")
                cursor.execute(sql, val)

                

# 데이터베이스에 변경사항을 저장합니다.
db.commit()


# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결 종료
db.close()