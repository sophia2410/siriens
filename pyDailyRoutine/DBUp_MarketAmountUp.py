## 뭐하는 화면인지 생각이 안난다. 코멘트 적자!!!

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

start_date = '20260101'
end_date = '20260904'


start_date = date(int (start_date[:4]), int (start_date[4:6]), int (start_date[6:]))
end_date = date(int (end_date[:4]), int (end_date[4:6]), int (end_date[6:]))

# timedelta 객체 생성
delta = timedelta (days=1)

# start_date부터 end_date까지 날짜별 거래대금 복구
# DB에 실제 존재하는 거래일만 UPDATE하며,
# 주말/휴일 또는 PyKRX 빈 데이터는 안전하게 건너뜁니다.
while start_date <= end_date:
    date_str = start_date.strftime('%Y%m%d')
    db_date_str = start_date.strftime('%Y-%m-%d')

    for market_fg, korean_name in [
        ('KOSPI', '코스피'),
        ('KOSDAQ', '코스닥')
    ]:
        try:
            # DB에 해당 거래일 데이터가 있는 경우에만 조회/수정
            cursor.execute(
                """
                SELECT 1
                FROM market_index
                WHERE market_fg = %s
                  AND date = %s
                LIMIT 1
                """,
                (market_fg, db_date_str)
            )

            if cursor.fetchone() is None:
                continue

            trading_value = stock.get_index_price_change(
                date_str,
                date_str,
                market_fg
            )

            if trading_value is None or trading_value.empty:
                print(f"[SKIP] 데이터 없음: {market_fg} {db_date_str}")
                continue

            if korean_name not in trading_value.index:
                print(
                    f"[SKIP] 지수명 없음: {market_fg} {db_date_str} "
                    f"/ index={list(trading_value.index)}"
                )
                continue

            amount = trading_value.loc[korean_name, '거래대금']

            if amount is None:
                print(f"[SKIP] 거래대금 NULL: {market_fg} {db_date_str}")
                continue

            amount = int(amount)

            # 0은 정상 거래대금으로 보기 어려우므로 기존 값을 덮어쓰지 않음
            if amount <= 0:
                print(
                    f"[SKIP] 거래대금 0 이하: "
                    f"{market_fg} {db_date_str} amount={amount}"
                )
                continue

            cursor.execute(
                """
                UPDATE market_index
                SET amount = %s
                WHERE market_fg = %s
                  AND date = %s
                """,
                (amount, market_fg, db_date_str)
            )

            print(
                f"[UPDATE] {market_fg} {db_date_str} "
                f"amount={amount:,}"
            )

        except Exception as e:
            print(
                f"[ERROR] {market_fg} {db_date_str}: {e}"
            )

    # 날짜 하나 처리 후 반영
    db.commit()

    start_date += delta
    time.sleep(1)

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결을 닫습니다.
db.close()