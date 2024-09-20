###########################################################
# 시그널리포트 종목을 [[종목명]] 으로 변경하기 위한 리스트 생성
###########################################################

import pymysql
import configparser

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

# 커서 생성
cursor = db.cursor()


# 오늘 날짜와 가장 가까운 날짜를 구합니다.
query_date = f"SELECT MAX(date) FROM daily_price WHERE date <= (select DATE_ADD(now(), INTERVAL 0 DAY))"

cursor.execute(query_date)
closest_date = cursor.fetchone()[0].decode('utf-8')

# 쿼리를 실행하고 결과를 가져옵니다.
query = f"""
        SELECT name 
        FROM stock a 
        WHERE EXISTS (SELECT code FROM daily_price b WHERE b.date = {closest_date} AND b.code = a.code)
        AND last_yn ='Y'
        AND name NOT LIKE '%%스팩%%'  -- '스팩'이라는 단어가 포함된 경우를 제외
        AND name != '대상'  -- '대상'이라는 단어와 일치하는 경우를 제외
        AND name != 'YW'
		AND name != 'LF'
		AND name != 'CJ'
		AND name != '나노'
		AND name != '테스'
		AND name != '레이'
        ORDER BY name DESC
        """
cursor.execute(query)
fetch_words = [item[0] for item in cursor.fetchall()]
words = [word.decode('utf-8') for word in fetch_words]
# print(words)

# 데이터베이스 연결 종료
db.close()