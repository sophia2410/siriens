# 필요한 모듈 임포트
import os
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

with open("E:/Project/202410/www/pyObsidian/vars_downExcel.txt", "r", encoding="utf-8") as f:
    lines = f.readlines()
    filename = lines[0].strip() 
    orderby = lines[1].strip() 
    query = "".join(lines[2:])

# 관종 해당일자 구하기
# 처리 시작
day_date = datetime.today().strftime('%Y%m%d')

# 쿼리문 실행
sql = f"SELECT V.name, V.code, V.group_key, V.tot_trade_amt, V.close_rate_str, V.tot_trade_amt_str "\
      f"FROM ({query}) V "\
      f"{orderby}"

# print(sql)
cursor.execute(sql)

# 결과를 모두 가져오기
result = cursor.fetchall()
# 커서와 연결 닫기
cursor.close()
db.close()

md_file = f'D:/Obsidian/Trader Sophia/20 Study/watchlist_0day/{filename}_{day_date}.md'

if  os.path.exists(md_file):
    os.remove(md_file) 

prev_key = ''
with open(md_file, "w", encoding="utf-8") as f:
    for row in result:
        name = row[0].decode('utf-8')
        code = row[1].decode('utf-8')
        
        group_key = row[2]
        print(type(group_key))
        if isinstance(group_key, bytes):
            group_key = group_key.decode('utf-8')

        close_rate_str = row[4].decode('utf-8')
        tot_trade_amt_str = row[5].decode('utf-8')

        if prev_key != group_key:
            f.write(f"# {group_key}\n")
            prev_key = group_key

        f.write(f"- [[{name}]] {close_rate_str} / {tot_trade_amt_str} \n")
        chart_day  = f'![](https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{code}.png?sidcode=1705826920773)'
        chart_week = f'![](https://ssl.pstatic.net/imgfinance/chart/item/area/week/{code}.png?sidcode=1705826920773)'
        f.write(f'{chart_day}{chart_week}\n\n\n')