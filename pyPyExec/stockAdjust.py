# 수정주가 반영

import yfinance as yf
import pymysql
import configparser
from datetime import datetime
# 1. 종목의 티커를 생성하는 함수 (코스피: .KS, 코스닥: .KQ)
def create_ticker(code, market_fg):
    market_fg = market_fg.decode('utf-8')  # 바이트 스트링을 문자열로 변환

    if market_fg == 'KOSPI':  # 코스피 종목
        return code + ".KS"
    elif market_fg == 'KOSDAQ':  # 코스닥 종목
        return code + ".KQ"
    else:
        raise ValueError(f"Unknown market type for code {code} with market flag {market_fg}")

# 2. daily_price 테이블에서 해당 코드의 최소일자와 최대일자, 그리고 시장 구분을 구하는 함수
def get_min_max_date_and_market(db_conn, code):
    cursor = db_conn.cursor(pymysql.cursors.DictCursor)

    # kiwoom_stock 테이블에서 종목 정보와 시장 구분 가져오기
    query_stock = """
    SELECT market_fg FROM kiwoom_stock WHERE code = %s
    """
    cursor.execute(query_stock, (code,))
    stock_info = cursor.fetchone()

    if not stock_info:
        raise ValueError(f"No market info found for code: {code}")

    # daily_price 테이블에서 해당 코드의 최소일자와 최대일자를 구함
    query = """
    SELECT MIN(date) AS min_date, MAX(date) AS max_date 
    FROM daily_price 
    WHERE code = %s
    """
    cursor.execute(query, (code,))
    result = cursor.fetchone()

    if result and result['min_date'] and result['max_date']:
        return result['min_date'], result['max_date'], stock_info['market_fg']
    else:
        raise ValueError(f"No price data found for code: {code}")

# 3. 주가 데이터를 Yahoo Finance에서 가져오는 함수
def fetch_adjusted_data(ticker, start_date, end_date):
    df = yf.download(ticker, start=start_date, end=end_date)
    return df[['Adj Close', 'Open', 'High', 'Low', 'Close', 'Volume']]

# 4. 수정주가 데이터를 MySQL 테이블에 업데이트하는 함수
def update_adjusted_price_to_db(db_conn, data, code):
    cursor = db_conn.cursor()

    for date, row in data.iterrows():
        adj_close = row['Adj Close']
        open_price = row['Open']
        high_price = row['High']
        low_price = row['Low']
        close_price = row['Close']
        volume = row['Volume']

        # daily_price 테이블 업데이트
        update_query_daily_price = """
        UPDATE daily_price 
        SET close = %s, open = %s, high = %s, low = %s, volume = %s, is_adjusted = 1
        WHERE code = %s AND date = %s
        """
        cursor.execute(update_query_daily_price, (
            adj_close, open_price, high_price, low_price, volume, code, date
        ))

        # daily_pykrx 테이블 업데이트
        update_query_daily_pykrx = """
        UPDATE daily_pykrx 
        SET close = %s, open = %s, high = %s, low = %s, volume = %s, is_adjusted = 1
        WHERE code = %s AND date = %s
        """
        cursor.execute(update_query_daily_pykrx, (
            adj_close, open_price, high_price, low_price, volume, code, date
        ))

    db_conn.commit()

# 5. 수정 이력을 기록하는 함수
def log_adjustment_history(db_conn, code, event_type, min_date, max_date, remarks=None):
    cursor = db_conn.cursor()

    insert_query = """
    INSERT INTO stock_adjustment_history (code, event_type, event_date, min_date, max_date, remarks)
    VALUES (%s, %s, %s, %s, %s, %s)
    """
    cursor.execute(insert_query, (code, event_type, datetime.now().date(), min_date, max_date, remarks))
    db_conn.commit()

# 6. 설정 파일을 읽고 데이터베이스 연결 생성
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 7. 종목 코드 입력 및 데이터베이스에서 정보 가져오기
code = "457190" 
min_date, max_date, market_fg = get_min_max_date_and_market(db, code)

# 8. 시장 구분에 따른 티커 생성
ticker = create_ticker(code, market_fg)

# 9. 수정주가 데이터를 가져와 데이터베이스에 업데이트
adjusted_data = fetch_adjusted_data(ticker, min_date, max_date)
update_adjusted_price_to_db(db, adjusted_data, code)

# 10. 수정 이력을 기록 (이벤트 종류: 수동)
log_adjustment_history(db, code, "수동", min_date, max_date, "수정주가 업데이트")

# 11. 데이터베이스 연결 종료
db.close()
