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
base_path = "C:/KiwoomHero4/temp/20250508"
target_date = os.path.basename(base_path)
target_date_fmt = datetime.strptime(target_date, "%Y%m%d").date()

# 파일 경로
file_path = os.path.join(base_path, "chart_1day.xls")
if not os.path.exists(file_path):
    print(f"❌ {file_path} 파일 없음")
    exit()

print(f"📥 일봉 업로드 시작: {file_path}")

# 엑셀 파일 읽기
df = pd.read_excel(file_path, engine='xlrd')

# 열 이름 정리
df.columns = ['date', 'open', 'high', 'low', 'close', 'sma_5', 'sma_20', 'sma_120', 'volume']

# 날짜 형식 변환 및 필터링
df['date'] = pd.to_datetime(df['date']).dt.date
df = df[df['date'] == target_date_fmt]

# 필요한 컬럼만 정리
df = df[['date', 'open', 'high', 'low', 'close', 'volume', 'sma_5', 'sma_20', 'sma_120']]

# DB INSERT
with db.cursor() as cursor:
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
            row['date'],
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

db.close()
print("✅ 일봉 데이터 등록 완료")