import yfinance as yf
from pykrx import stock
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈
from datetime import datetime, date, timedelta  # 날짜와 시간을 다루기 위한 모듈
import time


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

start_date = '20230219'
end_date = '20231218'


start_date = date(int (start_date[:4]), int (start_date[4:6]), int (start_date[6:]))
end_date = date(int (end_date[:4]), int (end_date[4:6]), int (end_date[6:]))

# timedelta 객체 생성
delta = timedelta (days=1)

# start_date부터 end_date까지 루프를 돌면서, 각 날짜에 대해 stock.get_index_price_change 함수를 호출
while start_date <= end_date:
    # start_date를 'yyyymmdd' 형식의 문자열로 변환
    date_str = start_date.strftime ('%Y%m%d')

    # stock.get_index_price_change 함수를 호출
    trading_value = stock.get_index_price_change (date_str, date_str, "KOSPI")

    # 거래대금 컬럼만 선택
    trading_value = trading_value["거래대금"]
    trading_value = trading_value.to_frame()
    row = trading_value.loc["코스피"]

    sql = f"UPDATE market_index SET amount = {row.거래대금} WHERE market_fg = 'KOSPI' AND date = '{date_str}'"
    print(sql)
    # SQL 쿼리 실행
    cursor.execute(sql)
    # DB에 반영
    db.commit()

    # stock.get_index_price_change 함수를 호출
    trading_value = stock.get_index_price_change (date_str, date_str, "KOSDAQ")

    # 거래대금 컬럼만 선택
    trading_value = trading_value["거래대금"]
    trading_value = trading_value.to_frame()
    row = trading_value.loc["코스닥"]

    sql = f"UPDATE market_index SET amount = {row.거래대금} WHERE market_fg = 'KOSDAQ' AND date = '{date_str}'"
    print(sql)
    # SQL 쿼리 실행
    cursor.execute(sql)
    # DB에 반영
    db.commit()

    start_date += delta
    time.sleep(1)

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결을 닫습니다.
db.close()