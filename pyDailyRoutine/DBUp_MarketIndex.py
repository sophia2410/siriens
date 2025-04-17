from datetime import datetime
import yfinance as yf
from pykrx import stock
import pymysql
import configparser

# index 변수를 한글로 변환하는 함수
def index_to_korean(index):
    if index == 'KOSPI':
        return '코스피'
    elif index == 'KOSDAQ':
        return '코스닥'
    else:
        return index

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

# 날짜 설정
query_date = "SELECT MAX(date) FROM daily_price WHERE date <= (SELECT DATE_ADD(now(), INTERVAL -2 Day))"
cursor.execute(query_date)
start_date = cursor.fetchone()[0].strftime('%Y-%m-%d')

query_date = "SELECT DATE_ADD(now(), INTERVAL +1 DAY)"
cursor.execute(query_date)
end_date = cursor.fetchone()[0].strftime('%Y-%m-%d')

# start_date = '2024-09-26'
# end_date = '2024-10-02'

# 지수 코드와 티커
index_dict = {
    'KOSPI': '^KS11',
    'KOSDAQ': '^KQ11',
    'S&P 500': '^GSPC',
    'NASDAQ': '^IXIC'
}

# 오늘 날짜
today_date = datetime.now().strftime('%Y-%m-%d')

# 각 지수 데이터 처리
for index, ticker in index_dict.items():
    data = yf.download(ticker, start=start_date, end=end_date)

    # MultiIndex 컬럼을 단순한 인덱스로 변환
    data.columns = data.columns.droplevel(1)
    print(f"{index} 변환된 컬럼명:", data.columns.tolist())

    if data.empty:
        print(f"No data found for {ticker} between {start_date} and {end_date}. Skipping.")
        continue
    data['close_rate'] = data['Close'].pct_change() * 100
    data['close_rate'] = data['close_rate'].fillna(0)

    # 기존 데이터 확인
    cursor.execute(f"SELECT date FROM market_index WHERE market_fg = '{index}'")
    existing_dates = {row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()}

    for row in data.itertuples():
        date = row.Index.strftime('%Y-%m-%d')

        # 데이터 존재 여부 확인
        if date in existing_dates and date != today_date:
            print(f"이미 존재하는 데이터: {index}, {date} (건너뜀)")
            continue

        # 거래대금 가져오기
        trading_value = stock.get_index_price_change(date, date, index)
        if trading_value.empty:
            amount = 0
        else:
            index_korean = index_to_korean(index)
            amount = trading_value["거래대금"].get(index_korean, 0)

        # SQL 실행
        sql = f"""
            INSERT INTO market_index (market_fg, date, open, high, low, close, volume, close_rate, amount)
            VALUES ('{index}', '{date}', {row.Open}, {row.High}, {row.Low}, {row.Close}, {row.Volume}, {row.close_rate}, {amount})
            ON DUPLICATE KEY UPDATE
                open = VALUES(open),
                high = VALUES(high),
                low = VALUES(low),
                close = VALUES(close),
                volume = VALUES(volume),
                close_rate = VALUES(close_rate),
                amount = VALUES(amount)
        """
        # print(sql)
        cursor.execute(sql)

    # DB 커밋
    db.commit()

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# DB 연결 닫기
db.close()