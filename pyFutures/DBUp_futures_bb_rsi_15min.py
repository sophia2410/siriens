import pandas as pd
import pymysql
import configparser
import os
from tqdm import tqdm
from datetime import datetime

# 설정 읽기
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

# 파일 경로
base_path = "C:/KiwoomHero4/temp/BB_RSI_Backtest"
file_path = os.path.join(base_path, "BB_RSI_60m.xlsx")

# 엑셀 읽기
df = pd.read_excel(file_path, engine='openpyxl')

# 컬럼 매핑
df.columns = [
    "날짜", "시간", "open", "high", "low", "close", "종가 지수 5", "3", "ema_20", "ema_60", "ema_120",
    "bb_center", "bb_upper", "bb_lower", "rsi_14", "volume",
    "단순5", "20", "60", "120", "rsi_dup",
    "macd_hist", "macd", "macd_signal"
]

# 날짜/시간 합치기
df['datetime'] = pd.to_datetime(df['날짜'].astype(str) + ' ' + df['시간'].astype(str), errors='coerce')
df['date'] = df['datetime'].dt.date
df['time'] = df['datetime'].dt.time

# 필요한 컬럼만 정리
df = df[[
    'date', 'time', 'datetime', 'open', 'high', 'low', 'close', 'volume',
    'bb_center', 'bb_upper', 'bb_lower', 'rsi_14',
    'ema_20', 'ema_60',
    'macd', 'macd_signal', 'macd_hist'
]]

# DB 업로드
with db.cursor() as cursor:
    for _, row in tqdm(df.iterrows(), total=len(df)):
        sql = """
        INSERT IGNORE INTO futures_bb_rsi_60m
        (date, time, datetime, open, high, low, close, volume,
         bb_center, bb_upper, bb_lower, rsi14,
         ema20, ema60, macd, macd_signal, macd_hist)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(sql, (
            row['date'], row['time'], row['datetime'],
            float(row['open']) if pd.notna(row['open']) else None,
            float(row['high']) if pd.notna(row['high']) else None,
            float(row['low']) if pd.notna(row['low']) else None,
            float(row['close']) if pd.notna(row['close']) else None,
            int(row['volume']) if pd.notna(row['volume']) else 0,
            float(row['bb_center']) if pd.notna(row['bb_center']) else None,
            float(row['bb_upper']) if pd.notna(row['bb_upper']) else None,
            float(row['bb_lower']) if pd.notna(row['bb_lower']) else None,
            float(row['rsi_14']) if pd.notna(row['rsi_14']) else None,
            float(row['ema_20']) if pd.notna(row['ema_20']) else None,
            float(row['ema_60']) if pd.notna(row['ema_60']) else None,
            float(row['macd']) if pd.notna(row['macd']) else None,
            float(row['macd_signal']) if pd.notna(row['macd_signal']) else None,
            float(row['macd_hist']) if pd.notna(row['macd_hist']) else None
        ))
    db.commit()

db.close()
print("🎉 futures_bb_rsi_60m 업로드 완료")
