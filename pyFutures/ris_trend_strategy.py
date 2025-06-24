# 추세/비추세 판단

import pandas as pd
import pymysql
import configparser
from datetime import datetime

# 설정 파일 로드
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# DB 연결 설정
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# ✅ 사용자 입력
selected_date = '2024-02-02'  # 분석 기준 일자
minute_type = '60min'  # 분석 주기 (예: 60min)

# MACD 계산 함수 정의
def calculate_macd(df, short=12, long=26, signal=9):
    df['ema_short'] = df['close'].ewm(span=short, adjust=False).mean()
    df['ema_long'] = df['close'].ewm(span=long, adjust=False).mean()
    df['macd'] = df['ema_short'] - df['ema_long']
    df['macd_signal'] = df['macd'].ewm(span=signal, adjust=False).mean()
    df['macd_hist'] = df['macd'] - df['macd_signal']
    return df

# 데이터 로드
table_name = f"futures_{minute_type}"
query = f"""
SELECT datetime, close, sma_5, sma_20, sma_120, rsi_14
FROM {table_name}
WHERE DATE(datetime) <= '{selected_date}'
  AND DATE(datetime) >= DATE_SUB('{selected_date}', INTERVAL 5 DAY)
ORDER BY datetime
"""

# 실행 및 DataFrame 변환
df = pd.read_sql(query, db)
db.close()

# MACD 계산
calculate_macd(df)

# 시장 구조 판단
results = []
for i in range(1, len(df)):
    row = df.iloc[i]
    prev = df.iloc[i - 1]

    rsi = row['rsi_14']
    if pd.isna(rsi):
        continue

    rsi_signal = None
    if rsi < 30:
        rsi_signal = '과매도'
    elif rsi > 70:
        rsi_signal = '과매수'

    if rsi_signal:
        macd_hist = row['macd_hist']
        sma_5 = row['sma_5']
        sma_20 = row['sma_20']
        sma_60 = row['sma_120']

        conditions = {
            'macd_expanding': macd_hist > prev['macd_hist'],
            'ema_separation': (sma_5 > sma_20 > sma_60) or (sma_5 < sma_20 < sma_60),
        }

        trend_score = sum(conditions.values())
        market_structure = '추세장' if trend_score >= 2 else '비추세장'

        results.append({
            'datetime': row['datetime'],
            'rsi': rsi,
            'rsi_signal': rsi_signal,
            'macd_hist': macd_hist,
            'macd_expanding': conditions['macd_expanding'],
            'ema_separation': conditions['ema_separation'],
            'market_structure': market_structure
        })

result_df = pd.DataFrame(results)
print(result_df)

# 엑셀 저장 (선택)
result_df.to_excel(f"strategy_check_{selected_date}.xlsx", index=False, encoding='utf-8-sig')
