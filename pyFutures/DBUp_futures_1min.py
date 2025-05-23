import pandas as pd
import pymysql
import configparser
import os
from tqdm import tqdm
from datetime import datetime

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

# 폴더 경로에서 일자 추출
base_path = "C:/KiwoomHero4/temp/20250522"
target_date = os.path.basename(base_path)
target_date_fmt = datetime.strptime(target_date, "%Y%m%d").date()

minute_types = ['5min', '10min', '15min', '60min']

with db.cursor() as cursor:
    for m in minute_types:
        table_name = f"futures_{m}"
        file_path = os.path.join(base_path, f"chart_{m}.xls")
        if not os.path.exists(file_path):
            print(f"❌ {file_path} 파일 없음")
            continue

        print(f"📥 {table_name} 업로드 시작: {file_path}")

        # xls 읽기
        df = pd.read_excel(file_path, engine='xlrd')

        df.columns = ['날짜', '시간', '시가', '고가', '저가', '종가', '종가 단순 5', '20', '120', '거래량', 'RSI 14']

        # 컬럼명 정리
        df.rename(columns={
            '시가': 'open',
            '고가': 'high',
            '저가': 'low',
            '종가': 'close',
            '거래량': 'volume',
            '종가 단순 5': 'sma_5',
            '20': 'sma_20',
            '120': 'sma_120',
            'RSI 14': 'rsi_14'
        }, inplace=True)

        # 날짜+시간 합쳐서 datetime 생성
        df['datetime'] = pd.to_datetime(df['날짜'].astype(str) + ' ' + df['시간'].astype(str))
        df['date'] = df['datetime'].dt.date
        df['time'] = df['datetime'].dt.time

        # 해당 일자만 필터링
        df = df[df['date'] == target_date_fmt]

        # 필요한 컬럼만 정리
        df = df[['date', 'time', 'datetime', 'open', 'high', 'low', 'close', 'volume', 'sma_5', 'sma_20', 'sma_120','rsi_14']]

        # DB 업로드
        for _, row in tqdm(df.iterrows(), total=len(df)):
            sql = f"""
            INSERT IGNORE INTO {table_name} (date, time, datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, rsi_14)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
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
                float(row['sma_120']) if pd.notna(row['sma_120']) else None,
                float(row['rsi_14']) if pd.notna(row['rsi_14']) else None
            ))
        db.commit()
        print(f"✅ {table_name} 등록 완료")

db.close()
print("🎉 전체 등록 완료")
