# 📁 generate_strategy_condition_sql.py
import pymysql
import configparser
from collections import defaultdict

def decode_if_bytes(val):
    return val.decode('utf-8') if isinstance(val, bytes) else val

def quote_if_str(val):
    try:
        float(val)
        return val  # 숫자는 그대로
    except (ValueError, TypeError):
        return f"'{val}'"  # 문자열은 따옴표 감싸기

def build_condition_sql(feature_name, operator, value1, value2=None):
    try:
        feature_name = decode_if_bytes(feature_name)
        operator = decode_if_bytes(operator)
        value1 = decode_if_bytes(value1)
        value2 = decode_if_bytes(value2) if value2 is not None else None

        val1 = quote_if_str(value1)
        val2 = quote_if_str(value2) if value2 is not None else None

        if not feature_name or not operator:
            return ""

        if operator.lower() == 'between':
            return f"({feature_name} BETWEEN {val1} AND {val2})"
        elif operator in ['=', '!=', '>', '<', '>=', '<=']:
            return f"({feature_name} {operator} {val1})"
        elif operator.lower() == 'like':
            return f"({feature_name} LIKE {val1})"
        elif operator.lower() == 'in':
            return f"({feature_name} IN ({val1}))"
        return ""
    except Exception as e:
        print(f"⚠️ build_condition_sql 에러: {e}")
        return ""

# 설정 불러오기
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

with db.cursor() as cursor:
    cursor.execute("SELECT id, name, bb_level, rsi_range, ema_cross FROM futures_bb_rsi_strategy")
    strategies = cursor.fetchall()

    cursor.execute("SELECT * FROM futures_bb_rsi_strategy_conditions")
    all_conditions = cursor.fetchall()

# 조건을 전략별로 group_id 기준으로 분류
strategy_condition_map = defaultdict(lambda: defaultdict(list))
for cond in all_conditions:
    strategy_condition_map[cond['strategy_id']][cond['group_id']].append(cond)

# 전략별 SQL 생성
for strategy in strategies:
    sid = strategy['id']
    name = decode_if_bytes(strategy['name'])
    bb_level = decode_if_bytes(strategy['bb_level'])
    rsi_range = decode_if_bytes(strategy['rsi_range'])
    ema_cross = decode_if_bytes(strategy['ema_cross'])

    grouped_conditions = strategy_condition_map.get(sid, {})
    or_clauses = []

    for group_id, conditions in grouped_conditions.items():
        and_parts = []
        print(f"📌 전략 {sid} 그룹 {group_id} 조건:")
        for cond in conditions:
            feature = decode_if_bytes(cond['feature_name'])
            operator = decode_if_bytes(cond['operator'])
            value1 = decode_if_bytes(cond['value1'])
            value2 = decode_if_bytes(cond['value2']) if cond['value2'] is not None else None

            print(f"  ▶ feature: {feature}, op: {operator}, val1: {value1}, val2: {value2}")
            sql_part = build_condition_sql(feature, operator, value1, value2)
            print(f"    → SQL: {sql_part}")
            if sql_part:
                and_parts.append(sql_part)
        if and_parts:
            or_clauses.append(f"({' AND '.join(and_parts)})")

    # 전략 정의 조건도 함께 WHERE에 포함
    fixed_conditions = []
    if bb_level:
        fixed_conditions.append(f"bb_level = '{bb_level}'")
    if rsi_range:
        fixed_conditions.append(f"rsi_range = '{rsi_range}'")
    if ema_cross:
        fixed_conditions.append(f"ema_cross = '{ema_cross}'")

    base_filter = ' AND '.join(fixed_conditions)

    if or_clauses:
        condition_filter = ' OR\n    '.join(or_clauses)
        where_clause = f"({base_filter}) AND (\n    {condition_filter}\n)" if base_filter else f"{condition_filter}"
        sql = f"""
-- 전략 #{sid}: {name}
SELECT '{sid}' AS strategy_id, date
FROM futures_bb_rsi_features_60m
WHERE
    {where_clause}
ORDER BY date;
"""
        print(sql)
    else:
        print(f"-- 전략 #{sid}: {name} → 조건 없음\n")

db.close()
