# 필요한 모듈 임포트
import pymysql
import xlwt
import xlwings as xw
import os
import time
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈
from datetime import datetime # 날짜와 시간을 다루기 위한 모듈

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL 데이터베이스에 연결합니다. 연결 정보는 자신의 환경에 맞게 수정하세요.
db = pymysql.connect(
	host=config.get('database', 'host'),
	user=config.get('database', 'user'),
	password=config.get('database', 'password'),
	db=config.get('database', 'db'),
	charset=config.get('database', 'charset')
)

# 커서 객체 생성
cursor = db.cursor()

# 관종 해당일자 구하기
# 처리 시작
watchlist_date = datetime.today().strftime('%Y%m%d')

# 쿼리문 실행
sql = """SELECT '' a, '' b, A.name, '' c, '' d, '' e, '' f, '' g, A.code, A.sector, A.theme, A.category, A.comment
        FROM watchlist_sophia A
        WHERE sector = '0 당일관종'
        ORDER BY A.sort_theme, A.sort_stock"""
cursor.execute(sql)
# 결과를 모두 가져오기
result = cursor.fetchall()
# 커서와 연결 닫기
cursor.close()
db.close()

# 엑셀 파일 생성
wb = xlwt.Workbook()
# 첫 번째 시트 활성화
ws =  wb.add_sheet('watchlist')
# 헤더 작성하기
header = ['a', 'b', 'name', 'c', 'd', 'e', 'f', 'g', 'code', 'sector', 'theme', 'category', 'comment']
for c in range(len(header)):
    ws.write(0, c, header[c])
    
# 결과를 엑셀 파일에 쓰기
for r in range(len(result)):
    for c in range(len(result[r])):
        if result[r][c] is not None:
            ws.write(r+1, c, result[r][c].decode('utf-8'))
        else:
            ws.write(r+1, c, result[r][c])
# 엑셀 파일 저장
filename = f"C:/KiwoomHero4/today_watchlist/{watchlist_date}.xls"
wb.save(filename)

# 엑셀 파일 열기
app = xw.App(visible=False)
book = app.books.open(filename)

# 저장하기
book.save()
book.close()
app.quit()