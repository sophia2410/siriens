import os
import pandas as pd
import pymysql
import configparser

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor
)

def to_str_safe(val):
    return val.decode('utf-8') if isinstance(val, bytes) else str(val)

# 데이터 조회
query = """
SELECT 
    a.date,
    a.open AS o1, a.close AS c1,
    b.open AS o2, b.close AS c2,
    c.open AS o3, c.close AS c3,
    
    ROUND((a.open + a.close + b.open + b.close + c.open + c.close) / 6, 2) AS avg_price
FROM futures_5min a
JOIN futures_5min b ON b.datetime = DATE_ADD(a.datetime, INTERVAL 5 MINUTE)
JOIN futures_5min c ON c.datetime = DATE_ADD(a.datetime, INTERVAL 10 MINUTE)
WHERE 
    a.date >= '2024-01-05'
    AND a.time = '08:45:00'
    AND GREATEST(
        ABS(a.open - b.open), ABS(a.open - c.open),
        ABS(a.close - b.close), ABS(a.close - c.close),
        ABS(b.open - c.open), ABS(b.close - c.close)
    ) <= 0.35  -- 0.2pt 이내로 모두 붙어있으면 횡보로 판단
    AND GREATEST(
        ABS(a.close - a.open), ABS(b.close - b.open), ABS(c.close - c.open)
    ) <= 0.35  -- 실체도 작을 때
ORDER BY a.date
"""
with db.cursor() as cursor:
    cursor.execute(query)
    rows = cursor.fetchall()

df = pd.DataFrame(rows)

# 저장 경로 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
output_dir = os.path.join(base_dir, "chart-strategy-Patten")
os.makedirs(output_dir, exist_ok=True)


# 두 조건으로 분기
filename = f"Patten_시작5분봉횡보.md"
filepath = os.path.join(output_dir, filename)

with open(filepath, "w", encoding="utf-8") as f:

    for row in df.itertuples():
        date = str((row.date))
        year = date[:4]

        f.write(f"## {date}\n")
        f.write(f"![[chart-captures-BB+1day/{year}/{date}.png]]\n\n")


print("✅ RSI 버킷별 마크다운 파일 생성 완료")
