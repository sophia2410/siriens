# 📁 generate_strategy_condition_sql_and_results.py
import pymysql
import configparser
from collections import defaultdict
from datetime import timedelta

def decode_if_bytes(val):
    return val.decode('utf-8') if isinstance(val, bytes) else val

def quote_if_str(val):
    try:
        float(val)
        return val
    except (ValueError, TypeError):
        return f"'{val}'"

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
    cursor.execute("SELECT id, name, bb_level, rsi_range, ema_cross, exp_dir FROM futures_bb_rsi_strategy")
    strategies = cursor.fetchall()

    cursor.execute("SELECT * FROM futures_bb_rsi_strategy_conditions")
    all_conditions = cursor.fetchall()

    strategy_condition_map = defaultdict(lambda: defaultdict(list))
    for cond in all_conditions:
        strategy_condition_map[cond['strategy_id']][cond['group_id']].append(cond)

    insert_data = []
    used_dates = set()

    def fetch_price_data(target_date):
        cursor.execute("SELECT close FROM futures_60min WHERE date = %s ORDER BY time DESC LIMIT 1", (target_date,))
        row = cursor.fetchone()
        return row['close'] if row else None

    def fetch_next_open(next_date):
        cursor.execute("SELECT open FROM futures_60min WHERE date = %s ORDER BY time ASC LIMIT 1", (next_date,))
        row = cursor.fetchone()
        return row['open'] if row else None

    for strategy in strategies:
        sid = strategy['id']
        name = decode_if_bytes(strategy['name'])
        bb_level = decode_if_bytes(strategy['bb_level'])
        rsi_range = decode_if_bytes(strategy['rsi_range'])
        ema_cross = decode_if_bytes(strategy['ema_cross'])
        direction = decode_if_bytes(strategy['exp_dir'])

        grouped_conditions = strategy_condition_map.get(sid, {})
        or_clauses = []

        for group_id, conditions in grouped_conditions.items():
            and_parts = []
            for cond in conditions:
                sql_part = build_condition_sql(cond['feature_name'], cond['operator'], cond['value1'], cond['value2'])
                if sql_part:
                    and_parts.append(sql_part)
            if and_parts:
                or_clauses.append(f"({' AND '.join(and_parts)})")

        base_filter = []
        if bb_level:
            base_filter.append(f"bb_level = '{bb_level}'")
        if rsi_range:
            base_filter.append(f"rsi_range = '{rsi_range}'")
        if ema_cross:
            base_filter.append(f"ema_cross = '{ema_cross}'")

        where_clause = ' AND '.join(base_filter)
        if or_clauses:
            where_clause += f" AND ( {' OR '.join(or_clauses)} )"

        full_query = f"SELECT date FROM futures_bb_rsi_features_60m WHERE {where_clause} ORDER BY date"
        cursor.execute(full_query)
        matched_dates = cursor.fetchall()

        for row in matched_dates:
            d = row['date']
            if d in used_dates:
                continue
            used_dates.add(d)

            next_day = d + timedelta(days=1)
            close = fetch_price_data(d)
            next_open = fetch_next_open(next_day)
            if close and next_open:
                point_profit = next_open - close if direction == '상승' else close - next_open
                trade_profit = point_profit * 50000 * 3
                return_pct = (point_profit / close) * 100
                gap = next_open - close
                gap_dir = '상승' if gap > 0 else '하락'
                insert_data.append((sid, d, direction, point_profit, trade_profit, return_pct, gap, gap_dir, close, next_open))

    if insert_data:
        cursor.execute("DELETE FROM futures_bb_rsi_strategy_result")
        insert_sql = """
            INSERT INTO futures_bb_rsi_strategy_result
            (strategy_id, date, exp_dir, point_profit, trade_profit, return_pct, gap, gap_dir, close, next_open)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.executemany(insert_sql, insert_data)
        db.commit()
        print(f"✅ 총 {len(insert_data)}건 결과 저장 완료")
    else:
        print("⚠️ 저장할 결과 없음")

db.close()
