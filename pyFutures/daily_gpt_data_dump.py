# gpt 전략분석용 캔들데이터 다운로드

import pandas as pd
import pymysql
import configparser
import os
import datetime

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

# 1️⃣ 기준일: 마지막 영업일
with conn.cursor() as cursor:
    cursor.execute("SELECT MAX(date) FROM calendar WHERE date <= NOW()")
    cursor.execute("SELECT MAX(date) FROM calendar WHERE date <= '2025-05-30'")
    base_date = cursor.fetchone()[0]  # 예: 2025-06-10

# 2️⃣ 기준일 포함, 과거 10영업일 추출
query = f"""
SELECT date
FROM calendar
WHERE date <= '{base_date}'
  AND cal_yn = 'Y'
ORDER BY date DESC
LIMIT 10
"""
calendar_df = pd.read_sql(query, conn)
date_list = calendar_df['date'].tolist()
min_date = date_list[-1]  # 10일 전
max_date = date_list[0]   # 기준일

min_date = datetime.datetime.strptime('2024-01-01', '%Y-%m-%d')
max_date = datetime.datetime.strptime('2025-05-30', '%Y-%m-%d')

# 3️⃣ 60분봉 데이터 추출
query_60min = f"""
SELECT datetime, open, high, low, close, volume, sma_5, sma_20, sma_120, rsi_14
FROM futures_60min
WHERE DATE(datetime) BETWEEN '{min_date}' AND '{max_date}'
ORDER BY datetime
"""
df_60min = pd.read_sql(query_60min, conn)

# 4️⃣ 일봉 데이터 추출
query_daily = f"""
SELECT date, open, high, low, close, volume, sma_5, sma_20, sma_120, rsi_14
FROM futures_1day
WHERE date <= '{max_date}'
ORDER BY date
"""
df_daily = pd.read_sql(query_daily, conn)

# 5️⃣ 저장
output_path = r'E:\★2030100★ 꿈은 이루어진다\2025 선물매매\GPT 전략분석\데이터_원본'
os.makedirs(output_path, exist_ok=True)

# 60분봉 저장
file_60min = f"futures_60min_last10days_{max_date.strftime('%Y%m%d')}_60min.csv"
df_60min.to_csv(os.path.join(output_path, file_60min), index=False, encoding='utf-8-sig')

# 일봉 저장
file_daily = f"futures_daily_until_{max_date.strftime('%Y%m%d')}.csv"
df_daily.to_csv(os.path.join(output_path, file_daily), index=False, encoding='utf-8-sig')

print(f"✅ 60분봉 저장 완료: {file_60min}")
print(f"✅ 일봉 저장 완료: {file_daily}")