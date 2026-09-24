import requests
import pandas as pd
from datetime import datetime, timedelta
import pymysql
import configparser
from pykrx import stock

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
cursor = db.cursor()

# 한국시간 기준 오늘 날짜
today = datetime.now()
today_str = today.strftime('%Y-%m-%d')

# 대상 지수 및 티커 매핑
index_dict = {
    'KOSPI': '^KS11',
    'KOSDAQ': '^KQ11',
    'S&P 500': '^GSPC',
    'NASDAQ': '^IXIC'
}

# 수집 기간 설정 (과거 7일)
range_days = '12d'

# 거래대금 가져오기 함수
def get_amount(index_name_eng, date_str):
    # 영어 지수명을 한글로 매핑
    korean_map = {
        'KOSPI': '코스피',
        'KOSDAQ': '코스닥'
    }

    korean_name = korean_map.get(index_name_eng)
    if not korean_name:
        return 0  # 미국 지수 등은 거래대금 없음

    try:
        pykrx_date = date_str.replace('-', '')
        df = stock.get_index_price_change(pykrx_date, pykrx_date, index_name_eng)
        if df.empty:
            return None
        return int(df.loc[korean_name, '거래대금'])
    except Exception as e:
        print(f"❌ 거래대금 오류 ({index_name_eng} {date_str}): {e}")
        return None

# DB의 직전 거래일 종가 기준 등락률 계산
def get_close_rate(index_name, date_str, close_price):
    cursor.execute("""
        SELECT close
        FROM market_index
        WHERE market_fg = %s
          AND date < %s
          AND close IS NOT NULL
        ORDER BY date DESC
        LIMIT 1
    """, (index_name, date_str))
    prev = cursor.fetchone()

    if not prev or prev[0] is None or float(prev[0]) == 0:
        return None

    return round(((float(close_price) / float(prev[0])) - 1) * 100, 4)

# 지수별 데이터 처리
for index_name, ticker in index_dict.items():
    print(f"📥 {index_name} 데이터 요청 중...")

    url = f"https://query1.finance.yahoo.com/v8/finance/chart/{ticker}?interval=1d&range={range_days}"
    headers = {
        "User-Agent": "Mozilla/5.0"
    }

    try:
        response = requests.get(url, headers=headers)
        result = response.json()['chart']['result'][0]
    except Exception as e:
        print(f"❌ {index_name} 요청 실패: {e}")
        continue

    timestamps = result['timestamp']
    quotes = result['indicators']['quote'][0]

    # 날짜 변환
    if index_name in ['KOSPI', 'KOSDAQ']:
        # 한국 지수는 이미 한국시간 기준
        dates = [datetime.fromtimestamp(ts).strftime('%Y-%m-%d') for ts in timestamps]
    else:
        # 미국 지수는 UTC 날짜 기준 (KST 변환하지 않음)
        dates = [datetime.utcfromtimestamp(ts).strftime('%Y-%m-%d') for ts in timestamps]

    # 데이터프레임 구성
    data = pd.DataFrame({
        'date': dates,
        'open': quotes['open'],
        'high': quotes['high'],
        'low': quotes['low'],
        'close': quotes['close'],
        'volume': quotes['volume']
    })

    # 기존 날짜 확인
    cursor.execute("SELECT date FROM market_index WHERE market_fg = %s", (index_name,))
    existing_dates = {row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()}

    # 저장
    for row in data.itertuples():
        date_str = row.date

        close_rate = get_close_rate(index_name, date_str, row.close)
        amount = get_amount(index_name, date_str) if index_name in ['KOSPI', 'KOSDAQ'] else None

        sql = f"""
            INSERT INTO market_index 
                (market_fg, date, open, high, low, close, volume, close_rate, amount)
            VALUES 
                (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                open = VALUES(open),
                high = VALUES(high),
                low = VALUES(low),
                close = VALUES(close),
                volume = VALUES(volume),
                close_rate = VALUES(close_rate),
                amount = COALESCE(VALUES(amount), amount)
        """

        cursor.execute(sql, (
            index_name,
            date_str,
            float(row.open) if row.open else None,
            float(row.high) if row.high else None,
            float(row.low) if row.low else None,
            float(row.close) if row.close else None,
            int(row.volume) if row.volume else 0,
            float(close_rate) if close_rate is not None else None,
            int(amount)
        ))

    db.commit()
    print(f"✅ {index_name} 저장 완료")

# 종료 처리
cursor.close()
db.close()
print("✅ 모든 지수 처리 완료")
