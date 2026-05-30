import pandas as pd
import pymysql
import configparser
import os
from tqdm import tqdm
from datetime import datetime

# 📁 엑셀 기본 경로
base_path = "E:/Project/202410/data/_futures/FuturesChart"

# ✅ 공통 컬럼 정의 및 매핑
column_names = [
    "날짜", "시가", "고가", "저가", "종가", "종가 지수 5", "10", "20", "60", "120",
    "중심선", "상한선", "하한선", "RSI 14",
    "종가 단순 5", "10_", "20_", "40_", "120_",
    "거래량", "단순 5__", "20__", "60__", "120__",
    "RSI 14_", "macd_hist", "macd", "macd_signal"
]

columns_map = {
    "날짜": "date",
    "시가": "open",
    "고가": "high",
    "저가": "low",
    "종가": "close",
    "거래량": "volume",
    "종가 단순 5": "sma_5",
    "20_": "sma_20",
    "120_": "sma_120",
    "RSI 14": "rsi_14",
    "중심선": "bb_center",
    "상한선": "bb_upper",
    "하한선": "bb_lower",
    "20": "ema_20",
    "60": "ema_60",
    "macd_hist": "macd_hist",
    "macd": "macd",
    "macd_signal": "macd_signal"
}

# ✅ DB 설정
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor
)

# ✅ 주기별 처리 루프
timeframes = ['1day', '1week']
for tf in timeframes:
    print(f"\n[{tf}] 데이터 처리 시작")

    excel_file = f"chart_{tf}.xls"
    excel_path = os.path.join(base_path, excel_file)
    table_name = f"futures_{tf}"

    # ✅ 엑셀 로드 및 컬럼 매핑
    df = pd.read_excel(excel_path, engine='xlrd')
    df.columns = column_names[:len(df.columns)]
    df = df.rename(columns=columns_map)

    # ✅ 필요한 컬럼만 필터링
    required_cols = list(columns_map.values())
    df = df[[col for col in required_cols if col in df.columns]]

    # ✅ 날짜 및 숫자 변환
    df['date'] = pd.to_datetime(df['date'], errors='coerce').dt.date
    df = df.dropna(subset=['date'])

    numeric_cols = [col for col in required_cols if col != 'date']
    for col in numeric_cols:
        df[col] = pd.to_numeric(df[col], errors='coerce')

    # ✅ 기존 최종 날짜 조회
    with db.cursor() as cursor:
        cursor.execute(f"SELECT MAX(date) AS max_date FROM {table_name}")
        row = cursor.fetchone()
        max_existing_date = row['max_date'] if row['max_date'] else datetime(2000, 1, 1).date()

    df = df[df['date'] > max_existing_date]
    if df.empty:
        print(f"[{tf}] 신규 데이터 없음. 스킵.")
        continue

    # ✅ DB 저장
    with db.cursor() as cursor:
        sql = f"""
            INSERT INTO {table_name} (
                date, open, high, low, close, volume,
                sma_5, sma_20, sma_120, rsi_14,
                bb_center, bb_upper, bb_lower,
                ema_20, ema_60, macd, macd_signal, macd_hist
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        for _, row in tqdm(df.iterrows(), total=len(df)):
            if row['date'] < datetime(2023, 1, 1).date():
                break
            cursor.execute(sql, (
                row['date'], row['open'], row['high'], row['low'], row['close'],
                row['volume'], row['sma_5'], row['sma_20'], row['sma_120'], row['rsi_14'],
                row['bb_center'], row['bb_upper'], row['bb_lower'], row['ema_20'], row['ema_60'],
                row['macd'], row['macd_signal'], row['macd_hist']
            ))
            print(f" {tf} - {row['date']} 처리완료")
        db.commit()

    print(f"[{tf}] 데이터 업로드 완료: {len(df)}건")

db.close()
print("모든 주기 데이터 처리 완료")
