import pandas as pd
import pymysql
import configparser
import os
from tqdm import tqdm
from datetime import datetime
from futures_bb_rsi_features import generate_missing_features

# 📌 DB 설정 로드
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')  # ← 경로 맞게 수정

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 📂 파일 및 테이블 매핑
upload_list = [
    ("chart_5min.xls", "futures_5min"),
    ("chart_15min.xls", "futures_15min"),
    ("chart_60min.xls", "futures_60min")
]

base_path = "C:/KiwoomHero4/temp"

# ✅ 엑셀 컬럼명 고정
column_names = [
    "날짜", "시간", "open", "high", "low", "close", "지수5", "3",
    "ema_20", "ema_60", "ema_120",
    "bb_center", "bb_upper", "bb_lower", "rsi_14",
    "sma_5", "sma_10", "sma_20", "sma_40", "sma_120",
    "volume",
    "vol_sma_5", "vol_sma_20", "vol_sma_60", "vol_sma_120",
    "rsi_dup",
    "macd_hist", "macd", "macd_signal"
]

# ✅ 공통 + 확장 컬럼 정의
common_cols = ['date', 'time', 'datetime', 'open', 'high', 'low', 'close', 'volume',
               'sma_5', 'sma_20', 'sma_120', 'rsi_14']
extra_cols = ['bb_center', 'bb_upper', 'bb_lower',
              'ema_20', 'ema_60', 'macd', 'macd_signal', 'macd_hist']
all_cols = common_cols + extra_cols

# ✅ 엑셀 파싱 함수
def parse_excel(filepath):
    df = pd.read_excel(filepath, engine='xlrd')
    df.columns = column_names[:len(df.columns)]
    df['datetime'] = pd.to_datetime(df['날짜'].astype(str) + ' ' + df['시간'].astype(str))
    df['date'] = df['datetime'].dt.date
    df['time'] = df['datetime'].dt.time
    return df

# ✅ 테이블 최신 등록 시간
def get_latest_datetime(cursor, table):
    cursor.execute(f"SELECT MAX(datetime) FROM {table}")
    row = cursor.fetchone()
    return row[0] if row[0] else datetime(2000, 1, 1)

# ✅ INSERT 실행
def insert_rows(df, table, cursor):
    latest_dt = get_latest_datetime(cursor, table)
    df = df[df['datetime'] > latest_dt]
    print(f"{table}: {len(df)} rows to insert")

    if table == "futures_5min":
        df_filtered = df[common_cols]
        sql = f"""
        INSERT IGNORE INTO {table}
        (date, time, datetime, open, high, low, close, volume,
         sma_5, sma_20, sma_120, rsi_14)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        for _, row in tqdm(df_filtered.iterrows(), total=len(df_filtered)):
            cursor.execute(sql, tuple(row[col] if pd.notna(row[col]) else None for col in df_filtered.columns))
    else:
        df_filtered = df[all_cols]
        sql = f"""
        INSERT IGNORE INTO {table}
        (date, time, datetime, open, high, low, close, volume,
         sma_5, sma_20, sma_120, rsi_14,
         bb_center, bb_upper, bb_lower,
         ema_20, ema_60, macd, macd_signal, macd_hist)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                %s, %s, %s, %s, %s, %s, %s, %s)
        """
        for _, row in tqdm(df_filtered.iterrows(), total=len(df_filtered)):
            cursor.execute(sql, tuple(row[col] if pd.notna(row[col]) else None for col in df_filtered.columns))

# ✅ 실행 메인
with db.cursor() as cursor:
    for filename, table in upload_list:
        filepath = os.path.join(base_path, filename)
        if not os.path.exists(filepath):
            print(f"파일 없음: {filepath}")
            continue
        df = parse_excel(filepath)
        insert_rows(df, table, cursor)

    db.commit()

db.close()
print("[OK] 모든 분봉 데이터 업로드 완료 / 전략 생성 계속...")

generate_missing_features("futures_60min", "futures_bb_rsi_features_60m")
generate_missing_features("futures_15min", "futures_bb_rsi_features_15m")

print("[OK] 전략 생성 완료")