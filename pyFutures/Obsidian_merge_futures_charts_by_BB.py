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

def is_number(val) -> bool:
    try:
        float(val)
        return True
    except (ValueError, TypeError):
        return False

# 전략 설명과 조건을 가져오기
def get_strategy_descriptions(bb_level, rsi_range, ema_cross):
    bb_level = to_str_safe(bb_level)
    rsi_range = to_str_safe(rsi_range)
    ema_cross = to_str_safe(ema_cross)

    strategy_sql = """
        SELECT id, name, exp_dir, description
        FROM futures_bb_rsi_strategy
        WHERE bb_level = %s AND rsi_range = %s AND ema_cross = %s
    """
    with db.cursor() as cursor:
        cursor.execute(strategy_sql, (bb_level, rsi_range, ema_cross))
        strategies = cursor.fetchall()

    result = {"상승": [], "하락": []}

    for row in strategies:
        sid = row['id']
        name = to_str_safe(row['name'])
        description = to_str_safe(row['description'])

        clean_name = name.replace("#", "")
        # desc = f"### 📘 {clean_name} (전략 ID: {sid})\n"
        desc = f"### 📘 {clean_name}\n"
        desc += f"{description.strip()}\n"
        desc += "\n```\nWHERE"

        # 조건 조회
        cond_sql = """
            SELECT group_id, feature_name, operator, value1, value2
            FROM futures_bb_rsi_strategy_conditions
            WHERE strategy_id = %s
            ORDER BY group_id ASC, id ASC
        """
        with db.cursor() as c2:
            c2.execute(cond_sql, (sid,))
            conds = c2.fetchall()

        groups = {}
        for c in conds:
            gid = c['group_id']
            if gid not in groups:
                groups[gid] = []

            feature = to_str_safe(c['feature_name'])
            op = to_str_safe(c['operator']).upper()
            v1_raw = to_str_safe(c['value1'])
            v2_raw = to_str_safe(c['value2'])

            v1 = v1_raw if is_number(v1_raw) else f"'{v1_raw}'"
            v2 = v2_raw if is_number(v2_raw) else f"'{v2_raw}'"

            cond = f"({feature} BETWEEN {v1} AND {v2})" if op == 'BETWEEN' else f"({feature} {op} {v1})"
            groups[gid].append(cond)

        if groups:
            desc += "\n  AND (\n"
            group_clauses = ["    (" + " AND ".join(conds) + ")" for conds in groups.values()]
            desc += " OR\n".join(group_clauses)
            desc += "\n  )"

        desc += "\n```\n"
        exp_dir = to_str_safe(row['exp_dir'])
        result[exp_dir].append(desc)

    return result

# 데이터 조회
query = """
    SELECT date, bb_level, rsi_range, ema_cross, gap_dir
    FROM futures_bb_rsi_features_60m
    WHERE bb_level IS NOT NULL AND rsi_range IS NOT NULL AND ema_cross IS NOT NULL AND gap_dir IS NOT NULL
    ORDER BY date ASC
"""
with db.cursor() as cursor:
    cursor.execute(query)
    rows = cursor.fetchall()

df = pd.DataFrame(rows)

# 저장 경로 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
output_dir = os.path.join(base_dir, "chart-strategy-BB")
os.makedirs(output_dir, exist_ok=True)

# 그룹별 마크다운 생성
grouped = df.groupby(['bb_level', 'rsi_range', 'ema_cross', 'gap_dir'])

for keys, group in grouped:
    bb_level, rsi_range, ema_cross, gap_dir = map(to_str_safe, keys)

    filename = f"{bb_level}-{rsi_range}-{ema_cross}-{gap_dir}.md"
    filepath = os.path.join(output_dir, filename)

    with open(filepath, "w", encoding="utf-8") as f:
        # 전략 요약
        strategy_info = get_strategy_descriptions(bb_level, rsi_range, ema_cross)

        f.write(f"# 📊 전략 요약: {bb_level} / {rsi_range} / {ema_cross} / {gap_dir}\n\n")

        if strategy_info["상승"]:
            f.write("## 🔺 상승 전략\n\n")
            for s in strategy_info["상승"]:
                f.write(s + "\n")

        if strategy_info["하락"]:
            f.write("## 🔻 하락 전략\n\n")
            for s in strategy_info["하락"]:
                f.write(s + "\n")

        f.write("---\n\n")

        # 이미지 삽입
        for _, row in group.iterrows():
            date = str(row['date'])
            year = date[:4]
            f.write(f"## {date}\n")
            f.write(f"![[chart-captures-BB+1day/{year}/{date}.png]]\n\n")

print("✅ 전략별 마크다운 파일 생성 완료")
