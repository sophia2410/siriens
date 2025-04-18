import pandas as pd
import pymysql
import configparser
from tqdm import tqdm

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# DB 연결
conn = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# CSV 파일 경로
csv_path = "E:/Project/202410/data/_futures/futures_1day_2000-.csv"

# 데이터 불러오기
df = pd.read_csv(csv_path, encoding='cp949')  # 또는 'utf-8-sig'
df.columns = ['date', 'open', 'high', 'low', 'close', 'volume']
df['date'] = pd.to_datetime(df['date']).dt.date

# DB에 INSERT
with conn.cursor() as cursor:
    for _, row in tqdm(df.iterrows(), total=len(df)):
        sql = """
        INSERT INTO futures_1day (date, open, high, low, close, volume)
        VALUES (%s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            open=VALUES(open),
            high=VALUES(high),
            low=VALUES(low),
            close=VALUES(close),
            volume=VALUES(volume)
        """
        cursor.execute(sql, (
            row['date'], row['open'], row['high'], row['low'], row['close'], row['volume']
        ))
    conn.commit()

conn.close()
print("✅ 일별 데이터 등록 완료")
