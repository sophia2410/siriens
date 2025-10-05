import os
import pandas as pd
import pymysql
import configparser
import math

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

# 데이터 조회
query = """
    SELECT date, prev_close, open_price, gap_pt, prev_rsi14, prev_bb_pos_ratio, bb_pos_ratio
    FROM rule_based_rowdata
    WHERE gap_pt IS NOT NULL
    ORDER BY date ASC
"""
with db.cursor() as cursor:
    cursor.execute(query)
    rows = cursor.fetchall()

df = pd.DataFrame(rows)

# 저장 경로 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
output_dir = os.path.join(base_dir, "chart-strategy-Gap")
os.makedirs(output_dir, exist_ok=True)

# gap direction, bucket 생성
def get_gap_bucket(gap, step=1):
    abs_gap = abs(gap)
    low = math.floor(abs_gap / step) * step
    high = low + step
    return f"{low}-{high}"

df['gap_direction'] = df['gap_pt'].apply(lambda x: 'gapup' if x >= 0 else 'gapdown')
df['gap_bucket'] = df['gap_pt'].apply(get_gap_bucket)

# 그룹핑: 방향 + 구간
grouped = df.groupby(['gap_direction', 'gap_bucket'])

for (direction, bucket), group in grouped:
    if group.empty:
        continue

    filename = f"{direction}_{bucket}.md"
    filepath = os.path.join(output_dir, filename)

    with open(filepath, "w", encoding="utf-8") as f:
        f.write(f"# {direction.upper()} {bucket}pt 사례\n\n")

        for _, row in group.iterrows():
            date = str(row['date'])
            year = date[:4]
            prev_close = row['prev_close']
            open_price = row['open_price']
            gap_pt = row['gap_pt']
            rsi = row['prev_rsi14']
            prev_bb_pos_ratio = row['prev_bb_pos_ratio']
            bb_pos_ratio = row['bb_pos_ratio']

            f.write(f"## {date} (Gap: {gap_pt})\n")
            f.write(f"- Prev Close: {prev_close}\n")
            f.write(f"- Open Price: {open_price}\n")
            f.write(f"- RSI: {rsi}\n")
            f.write(f"- Prev BB Pos Ratio: {prev_bb_pos_ratio}\n")
            f.write(f"- BB Pos Ratio: {bb_pos_ratio}\n")
            f.write(f"![[chart-captures-BB+1day/{year}/{date}.png]]\n\n")

print("✅ 갭 포인트 단위별 마크다운 파일 생성 완료")
