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

def get_rsi_bucket(rsi, step=10):
    try:
        r = float(rsi)
    except (ValueError, TypeError):
        return 'unknown'
    bucket_floor = int(r // step) * step
    # ensure upper limit
    if bucket_floor >= 100:
        bucket_floor = 90
    return f"{bucket_floor}-{bucket_floor + step}"

# 데이터 조회
query = """
    SELECT date, prev_close, open_price, gap_pt, prev_rsi14, prev_bb_pos_ratio
    FROM rule_based_rowdata
    WHERE prev_rsi14 IS NOT NULL
    ORDER BY date ASC
"""
with db.cursor() as cursor:
    cursor.execute(query)
    rows = cursor.fetchall()

df = pd.DataFrame(rows)

# 람다로 RSI 버킷 컬럼 추가
df['rsi_bucket'] = df['prev_rsi14'].apply(get_rsi_bucket)

# 저장 경로 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
output_dir = os.path.join(base_dir, "chart-strategy-Rule")
os.makedirs(output_dir, exist_ok=True)

# 버킷별 마크다운 생성
grouped = df.groupby('rsi_bucket')

for bucket, group in grouped:
    # 두 조건으로 분기
    for direction, sub_group in [("gapup", group[group['gap_pt'] >= 0]), ("gapdown", group[group['gap_pt'] < 0])]:
        if sub_group.empty:
            continue  # 해당 조건 그룹에 데이터 없으면 생략

        safe_bucket = bucket.replace('/', '-')
        filename = f"RSI_{safe_bucket}_{direction}.md"
        filepath = os.path.join(output_dir, filename)

        with open(filepath, "w", encoding="utf-8") as f:
            f.write(f"# RSI {bucket} ({'Gap ≥ 0' if direction == 'gapup' else 'Gap < 0'})\n\n")

            for _, row in sub_group.iterrows():
                date = str(row['date'])
                year = date[:4]
                prev_close = row['prev_close']
                open_price = row['open_price']
                gap_pt = row['gap_pt']
                rsi = row['prev_rsi14']
                bb_pos_ratio = row['prev_bb_pos_ratio']

                f.write(f"## {date} (RSI: {rsi})\n")
                f.write(f"- Prev Close: {prev_close}\n")
                f.write(f"- Open Price: {open_price}\n")
                f.write(f"- Gap: {gap_pt}\n")
                f.write(f"- BB Pos Ratio: {bb_pos_ratio}\n")
                f.write(f"![[chart-captures-BB+1day/{year}/{date}.png]]\n\n")


print("✅ RSI 버킷별 마크다운 파일 생성 완료")
