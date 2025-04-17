import pandas as pd
import pymysql
import configparser
from tqdm import tqdm

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# DB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# CSV 파일 경로
csv_path = "E:/Project/202410/data/_futures/futures_1min_20240102_20250414.csv"

# 데이터 불러오기
df = pd.read_csv(csv_path, encoding='cp949')

# datetime 처리
df['datetime'] = pd.to_datetime(df['날짜'] + ' ' + df['시간'])
df['date'] = df['datetime'].dt.date
df['time'] = df['datetime'].dt.time

# 컬럼명 통일
df.columns = ['date_raw', 'time_raw', 'open', 'high', 'low', 'close', 'volume', 'datetime', 'date', 'time']

# 컬럼 선택
df = df[['date', 'time', 'datetime', 'open', 'high', 'low', 'close', 'volume']]

# DB 삽입
with db.cursor() as cursor:
    for _, row in tqdm(df.iterrows(), total=len(df)):
        sql = """
        INSERT IGNORE INTO futures_1min (date, time, datetime, open, high, low, close, volume)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(sql, (
            row['date'], row['time'], row['datetime'],
            row['open'], row['high'], row['low'], row['close'], row['volume']
        ))
    db.commit()

db.close()
print("✅ 등록 완료")
