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
# csv_path = "E:/Project/202410/data/_futures/chart_1day.csv"
csv_path = "C:/KiwoomHero4/temp/20250507/chart_1day.csv"

# 데이터 불러오기
# 열 이름: 날짜, 시가, 고가, 저가, 종가, 종가 단순 5, 20, 120, 거래량
df = pd.read_csv(csv_path, encoding='cp949')
df.columns = ['date', 'open', 'high', 'low', 'close', 'sma_5', 'sma_20', 'sma_120', 'volume']
df['date'] = pd.to_datetime(df['date']).dt.date

# DB에 INSERT
with conn.cursor() as cursor:
    for _, row in tqdm(df.iterrows(), total=len(df)):
        sql = """
        INSERT INTO futures_1day (date, open, high, low, close, volume, sma_5, sma_20, sma_120)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            open=VALUES(open),
            high=VALUES(high),
            low=VALUES(low),
            close=VALUES(close),
            volume=VALUES(volume),
            sma_5=VALUES(sma_5),
            sma_20=VALUES(sma_20),
            sma_120=VALUES(sma_120)
        """
        cursor.execute(sql, (
            row['date'], row['open'], row['high'], row['low'], row['close'],
            row['volume'], row['sma_5'], row['sma_20'], row['sma_120']
        ))
    conn.commit()

conn.close()
print("✅ 일별 데이터 등록 완료")