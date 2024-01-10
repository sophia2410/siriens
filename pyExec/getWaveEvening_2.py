# 마리아DB와 연결
import pymysql
import xlsxwriter

# DB 접속
conn = pymysql.connect(host='yunseul0907.cafe24.com', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')
cursor = conn.cursor()

# 쿼리 실행
cursor.execute("""SELECT B.yyyy, B.mm, B.dd
, CASE B.day
    WHEN 1 THEN '일'
    WHEN 2 THEN '월'
    WHEN 3 THEN '화'
    WHEN 4 THEN '수'
    WHEN 5 THEN '목'
    WHEN 6 THEN '금'
    WHEN 7 THEN '토'
  END AS day
, A.signal_grp, A.stock_last, ''
, CASE WHEN C.close_rate > 29.5 THEN '↑' ELSE '▲' END AS plus, C.close, C.close_rate, C.volume, round(C.amount/1000000) amount
, C.open, C.high, C.low
, A.title, A.link
FROM wave_evening_edit3 A
INNER JOIN calendar B
ON B.date = A.page_date
INNER JOIN daily_price C
ON C.date = A.page_date
AND C.code = A.code_last
ORDER BY B.yyyy, B.mm, B.dd, B.day, A.signal_grp, A.stock""")

# 엑셀 파일 생성
workbook = xlsxwriter.Workbook('result.xlsx')
worksheet = workbook.add_worksheet()

# 컬럼 이름 추가 
column_names = [desc[0] for desc in cursor.description]
worksheet.write_row(0, 0, column_names)

# 데이터 추가
row = 1 
for data in cursor:
    col = 0
    for d in data:
        if column_names[col] == 'link':
            worksheet.write_url(row, col, d) # 링크 컬럼은 하이퍼링크로
        else:
            worksheet.write(row, col, d) 
        col += 1
    row += 1
    
workbook.close()
conn.close()