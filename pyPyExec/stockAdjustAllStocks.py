# 수정주가 반영 (기간 이벤트 발생 여부 체크)

import yfinance as yf
import pymysql
import configparser
from pytz import timezone
import pandas as pd
from datetime import datetime, timedelta

# 1. 종목의 티커를 생성하는 함수 (코스피: .KS, 코스닥: .KQ)
def create_ticker(code, market_fg):
    code = code.decode('utf-8')  # 바이트 스트링을 문자열로 변환
    market_fg = market_fg.decode('utf-8')  # 바이트 스트링을 문자열로 변환
    if market_fg == 'KOSPI':  # 코스피 종목
        return code + ".KS"
    elif market_fg == 'KOSDAQ':  # 코스닥 종목
        return code + ".KQ"
    else:
        raise ValueError(f"Unknown market type for code {code} with market flag {market_fg}")

# 2. 수정주가 이벤트(배당, 주식 분할)를 확인하는 함수
def check_adjustment_events(db_conn, ticker, code):
    stock = yf.Ticker(ticker)
    code_str = code.decode('utf-8')

        # stock.info  # 종목에 대한 기본 정보 (딕셔너리 형태)
        # stock.history(period="5d") # 최근 주가 데이터 확인 (기본적으로 마지막 5일)
        # stock.dividends # 배당 데이터
        # stock.splits # 주식 분할 데이터
        # stock.financials # 재무제표
        # stock.balance_sheet # 밸런스 시트
        # stock.cashflow # 현금 흐름표 (연간)
        # stock.history(period="1d")['Close'][-1]  # 마지막 종가 출력 # 최근 주가 및 가격 

    # 주식 분할(Splits) 데이터 가져오기
    splits = stock.splits if isinstance(stock.splits, pd.Series) else pd.Series()
    
    # 현재 시간에 시간대 정보를 추가 (예: 'Asia/Seoul' 또는 다른 적절한 시간대)
    current_time = datetime.now(timezone('Asia/Seoul'))

    # 최근 365일 내 주식 분할 이벤트 확인
    last_split_date = splits.index[-1] if not splits.empty else None
    print(f"[DEBUG] Last split date for {code_str}: {last_split_date}")
    split_event = last_split_date and last_split_date >= (current_time - timedelta(days=41))
    print(f"[DEBUG] Split event occurred within last 365 days: {split_event}")

    # 이벤트가 있으면 True와 이벤트 날짜 및 종류 반환
    # 이미 처리된 이벤트인지 확인
    if split_event:
        cursor = db_conn.cursor()
        query = """
        SELECT COUNT(*) 
        FROM stock_adjustment_history 
        WHERE code = %s AND event_date = %s
        """
        print(f"[DEBUG] Executing query: {query}")
        print(f"[DEBUG] Query parameters: code = {code_str}, event_date = {last_split_date}")

        cursor.execute(query, (code_str, last_split_date))
        result = cursor.fetchone()

        # 이벤트가 이미 처리된 경우 False 반환
        if result[0] > 0:
            print(f"[DEBUG] Already Processing: {split_event}")
            return False, None, None

        # 이벤트가 처리되지 않은 경우 True 반환
        return True, last_split_date, '주식분할/증자'
    
    return False, None, None

# 3. daily_price 테이블에서 해당 코드의 최소일자와 최대일자를 구하는 함수
def get_min_max_date(db_conn, code):
    cursor = db_conn.cursor(pymysql.cursors.DictCursor)

    query = """
    SELECT MIN(date) AS min_date, MAX(date) AS max_date 
    FROM daily_price 
    WHERE code = %s
    """
    cursor.execute(query, (code,))
    result = cursor.fetchone()

    if result and result['min_date'] and result['max_date']:
        return result['min_date'], result['max_date']
    else:
        raise ValueError(f"No price data found for code: {code}")

# 4. 주가 데이터를 Yahoo Finance에서 가져오는 함수
def fetch_adjusted_data(ticker, start_date, end_date):
    df = yf.download(ticker, start=start_date, end=end_date)
    return df[['Adj Close', 'Open', 'High', 'Low', 'Close', 'Volume']]

# 5. 수정주가 데이터를 MySQL 테이블에 업데이트하는 함수
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
        # cursor.execute(update_query_daily_price, (
        #     adj_close, open_price, high_price, low_price, volume, code, date
        # ))

        # daily_pykrx 테이블 업데이트
        update_query_daily_pykrx = """
        UPDATE daily_pykrx 
        SET close = %s, open = %s, high = %s, low = %s, volume = %s, is_adjusted = 1
        WHERE code = %s AND date = %s
        """
        # cursor.execute(update_query_daily_pykrx, (
        #     adj_close, open_price, high_price, low_price, volume, code, date
        # ))

    # db_conn.commit()

# 6. 수정 이력을 기록하는 함수
def log_adjustment_history(db_conn, code, event_type, event_date, min_date, max_date, remarks=None):
    cursor = db_conn.cursor()

    # event_date의 시간대 정보를 제거하여 MySQL에 맞는 형식으로 변환
    if event_date.tzinfo is not None:
        event_date = event_date.astimezone(timezone('UTC')).replace(tzinfo=None)

    insert_query = """
    INSERT INTO stock_adjustment_history (code, event_type, event_date, min_date, max_date, remarks)
    VALUES (%s, %s, %s, %s, %s, %s)
    """
    # cursor.execute(insert_query, (code, event_type, event_date, min_date, max_date, remarks))
    # db_conn.commit()

# 7. 설정 파일을 읽고 데이터베이스 연결 생성
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 8. kiwoom_stock 테이블에서 종목 코드와 시장 구분을 가져오기
cursor = db.cursor(pymysql.cursors.DictCursor)
query_kiwoom_stock = "SELECT code, market_fg FROM kiwoom_stock WHERE market_fg IN ('KOSPI', 'KOSDAQ')"
cursor.execute(query_kiwoom_stock)
stocks = cursor.fetchall()

# 9. 각 종목에 대해 배당 및 분할 이벤트를 확인하고, 수정주가 업데이트
for stock in stocks:
    code = stock['code']
    market_fg = stock['market_fg']
    
    # 티커 생성
    ticker = create_ticker(code, market_fg)

    # 수정주가 발생 이벤트가 있는지 확인 (배당, 주식 분할 등)
    event_occurred, event_date, event_type = check_adjustment_events(db, ticker, code)
    
    if event_occurred:
        print(f"수정주가 이벤트 발생---------------------------------------------------------------------------: {code}, 이벤트 종류: {event_type}, 이벤트 날짜: {event_date}")

        # 이벤트가 발생한 경우 해당 종목의 수정주가 업데이트
        min_date, max_date = get_min_max_date(db, code)
        adjusted_data = fetch_adjusted_data(ticker, min_date, max_date)
        update_adjusted_price_to_db(db, adjusted_data, code)

        # 수정 이력 기록
        log_adjustment_history(db, code, event_type, event_date, min_date, max_date)
    else:
        print(f"수정주가 이벤트 없음: {code}")

# 10. 데이터베이스 연결 종료
db.close()
