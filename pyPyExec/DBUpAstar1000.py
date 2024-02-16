
import pymysql
import csv
import glob
import configparser

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# MariaDB 연결
conn = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 커서 생성
cur = conn.cursor()

# 파일 읽기
# f = open('E:/★2030100★ 꿈은 이루어진다/★ 유목민 ★/@시.리. 클래스/월급독립프로젝트_오프_1기/aStar 관심종목 1000선/아스타관심종목_카테고리입력/아스타1.csv', 'r', encoding='euc-kr')
# 아스타5~아스타11까지의 파일 경로 리스트 만들기
# filepaths = glob.glob('E:/★2030100★ 꿈은 이루어진다/★ 유목민 ★/@시.리. 클래스/월급독립프로젝트_오프_1기/aStar 관심종목 1000선/아스타관심종목_카테고리입력/아스타[1-9].csv') + glob.glob('E:/★2030100★ 꿈은 이루어진다/★ 유목민 ★/@시.리. 클래스/월급독립프로젝트_오프_1기/aStar 관심종목 1000선/아스타관심종목_카테고리입력/아스타1[0-1].csv')
# 아스타 로 시작하는 모든 파일 읽기
filepaths = glob.glob('E:/★2030100★ 꿈은 이루어진다/★ 유목민 ★/@시.리. 클래스/월급독립프로젝트_오프_1기/aStar 관심종목 1000선/아스타관심종목_카테고리입력/아스타*.csv')

# 리스트를 반복문으로 순회하면서 파일 열기
for filepath in filepaths:
    # with 문으로 파일 열고 닫기
    with open(filepath, 'r', encoding='euc-kr') as f:
        
        # reader 객체 생성
        datas = csv.reader(f)
        next(datas)

        category_index = 0
        subcategory_index = 1

        prev_category = ''

        # reader 객체를 반복문으로 순회하면서 DB에 삽입
        for row in datas:
            # 행의 첫 번째 요소와 두 번째 요소가 공백이 아니면 대분류와 중분류 변수에 저장
            if row[0] != '':
                category = row[0]
            if row[1] != '':
                subcategory = row[1]
                
            if category+subcategory != prev_category:
                category_index += 1
                subcategory_index = 1

            # 행의 마지막 요소인 종목코드를 6자리로 맞추고 앞에 0을 채움
            code = row[8].zfill(6)
            
            # SQL 문 작성
            sql = "INSERT INTO watchlist_astar (sector, theme, sort_theme, stock_idx, name, code) VALUES (%s, %s, %s, %s, %s, %s)"
            # SQL 문 실행
            print(sql, (category, subcategory, category_index, subcategory_index, row[2], code))
            cur.execute(sql, (category, subcategory, category_index, subcategory_index, row[2], code))
            
            subcategory_index += 1
            prev_category = category+subcategory

# 커밋
conn.commit()
# 연결과 커서 닫기
conn.close()
cur.close()
# 파일 닫기
f.close()