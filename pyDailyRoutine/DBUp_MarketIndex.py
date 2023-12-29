import yfinance as yf
from pykrx import stock
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈
from datetime import datetime # 날짜와 시간을 다루기 위한 모듈

# index 변수를 한글로 변환하는 함수를 정의함
def index_to_korean(index):
    # index 변수가 'KOSPI'라면, '코스피'를 반환함
    if index == 'KOSPI':
        return '코스피'
    # index 변수가 'KOSDAQ'이라면, '코스닥'을 반환함
    elif index == 'KOSDAQ':
        return '코스닥'
    # 그 외의 경우에는, index 변수를 그대로 반환함
    else:
        return index

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

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

# 1주전 거래일 구해오기. 등락률을 위해 이전 데이터 필요, 주말, 연휴 등 때문에 넉넉하게 지난주 데이터를 구한다.
query_date = f"SELECT DATE_FORMAT(MAX(date), '%Y-%m-%d') FROM daily_price WHERE date <= (SELECT DATE_FORMAT(DATE_ADD(now(), INTERVAL -1 WEEK), '%Y%m%d'))"

cursor.execute(query_date)
start_date = cursor.fetchone()[0].decode('utf-8')

# 1주전 거래일 구해오기. 등락률을 위해 이전 데이터 필요, 주말, 연휴 등 때문에 넉넉하게 지난주 데이터를 구한다.
query_date = f"SELECT DATE_FORMAT(DATE_ADD(now(), INTERVAL +1 DAY), '%Y-%m-%d')"

cursor.execute(query_date)
end_date= cursor.fetchone()[0].decode('utf-8')

# start_date = '2023-12-15'
# end_date = '2023-12-16'

# 지수 코드와 티커를 딕셔너리로 저장
index_dict = {
    'KOSPI': '^KS11',
    'KOSDAQ': '^KQ11',
    'S&P 500': '^GSPC',
    'NASDAQ': '^IXIC'
}

# index_dict의 키와 값에 대해 반복
for index, ticker in index_dict.items():
    # yf.download로 데이터 다운로드
    data = yf.download(ticker, start_date, end_date)
    
    # 등락률 계산
    data['close_rate'] = data['Close'].pct_change() * 100
    # nan을 0으로 대체
    data['close_rate'] = data['close_rate'].fillna(0)

    # 데이터의 행에 대해 반복
    for row in data.itertuples():
        # row.Index 값을 'yyyymmdd' 형식의 문자열로 변환
        date_str = row.Index.strftime ('%Y%m%d')
        
        # stock.get_index_price_change 함수를 호출하여 거래대금을 구함
        trading_value = stock.get_index_price_change (date_str, date_str, index)

        if trading_value.empty:
            # 비어 있다면, row 변수에 0 을 할당함
            amount = 0
        else:
            # 거래대금 컬럼만 선택하고, 코스피나 코스닥 행만 선택함
            trading_value = trading_value["거래대금"]
            # 비어 있지 않다면, row 변수에 trading_value.loc[index]를 할당함
            # index 변수를 한글로 변환하는 함수를 사용하여 index 값을 바꿈
            index_korean = index_to_korean(index)
            # index_korean 값을 사용하여 행을 선택함
            amount = trading_value.loc[index_korean]

        # 'yyyymmdd' 형식의 문자열로 삽입
        # insert 문에 trading_value 컬럼을 추가하고, row 변수를 삽입함
        sql = f"INSERT IGNORE INTO market_index (market_fg, date, open, high, low, close, volume, close_rate, amount) VALUES ('{index}', '{date_str}', {row.Open}, {row.High}, {row.Low}, {row.Close}, {row.Volume}, {row.close_rate}, {amount})"
        print(sql)
        # SQL 쿼리 실행
        cursor.execute(sql)
    # DB에 반영
    db.commit()

    print(data)
    
# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결을 닫습니다.
db.close()