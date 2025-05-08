import pandas as pd
import pymysql
import configparser
from tqdm import tqdm
import os

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

# 분봉 종류 및 파일명 매핑
# base_path = "E:/Project/202410/data/_futures"
base_path = "C:/KiwoomHero4/temp/20250507"

# minute_types = ['1min', '5min', '10min', '15min', '60min']
minute_types = ['5min', '10min', '15min', '60min']

with db.cursor() as cursor:
    for m in minute_types:
        table_name = f"futures_{m}"
        file_path = os.path.join(base_path, f"chart_{m}.csv")
        print(f"📥 {table_name} 업로드 시작: {file_path}")

        df = pd.read_csv(file_path, encoding='cp949')
        print(f"📊 CSV 컬럼 확인: {df.columns.tolist()}")  # 실제 컬럼 확인

        # 먼저 rename 처리
        df.rename(columns={
            '시가': 'open',
            '고가': 'high',
            '저가': 'low',
            '종가': 'close',
            '거래량': 'volume',
            '종가 단순 5': 'sma_5',
            '20': 'sma_20',
            '120': 'sma_120'
        }, inplace=True)

        # datetime 처리
        df['datetime'] = pd.to_datetime(df['날짜'] + ' ' + df['시간'])
        df['date'] = df['datetime'].dt.date
        df['time'] = df['datetime'].dt.time

        # 이제 필요한 컬럼만 선택 (컬럼명 통일 후에!)
        df = df[['date', 'time', 'datetime', 'open', 'high', 'low', 'close', 'sma_5', 'sma_20', 'sma_120', 'volume']]

        for _, row in tqdm(df.iterrows(), total=len(df)):
            sql = f"""
            INSERT IGNORE INTO {table_name} (date, time, datetime, open, high, low, close, volume, sma_5, sma_20, sma_120)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """
            cursor.execute(sql, (
                row['date'], row['time'], row['datetime'],
                float(row['open']) if pd.notna(row['open']) else None,
                float(row['high']) if pd.notna(row['high']) else None,
                float(row['low']) if pd.notna(row['low']) else None,
                float(row['close']) if pd.notna(row['close']) else None,
                int(row['volume']) if pd.notna(row['volume']) else 0,
                float(row['sma_5']) if pd.notna(row['sma_5']) else None,
                float(row['sma_20']) if pd.notna(row['sma_20']) else None,
                float(row['sma_120']) if pd.notna(row['sma_120']) else None
            ))
        db.commit()
        print(f"✅ {table_name} 등록 완료")

db.close()
print("🎉 전체 등록 완료")