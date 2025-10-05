import pymysql, pandas as pd, configparser, json
from decimal import Decimal, getcontext

getcontext().prec = 12

cfg = configparser.ConfigParser()
cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')

cnx = pymysql.connect(
    host=cfg.get('database', 'host'),
    user=cfg.get('database', 'user'),
    password=cfg.get('database', 'password'),
    db=cfg.get('database', 'db'),
    charset=cfg.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor
)

def check_conditions(row, cond):
    for key, val in cond.items():
        if key.endswith('_min'):
            col = key[:-4]
            if row[col] < val:
                return False
        elif key.endswith('_max'):
            col = key[:-4]
            if row[col] > val:
                return False
        elif key.endswith('_eq'):
            col = key[:-3]
            if row[col] != val:
                return False
        elif key == 'bb_touch':
            diff = abs(row['open_price'] - row[f'bb_{val}'])
            if diff > 0.03:
                return False
        elif key == 'bb_zone':
            px = row['open_price']
            if val == 'bb_at_center' and abs(px - row['bb_center']) > 0.03:
                return False
            elif val == 'bb_at_upper' and abs(px - row['bb_upper']) > 0.03:
                return False
            elif val == 'bb_at_lower' and abs(px - row['bb_lower']) > 0.03:
                return False
            elif val == 'bb_above_upper' and px <= row['bb_upper'] + 0.03:
                return False
            elif val == 'bb_below_lower' and px >= row['bb_lower'] - 0.03:
                return False
            elif val == 'bb_above_center' and not (row['bb_center'] + 0.03 < px < row['bb_upper'] - 0.03):
                return False
            elif val == 'bb_below_center' and not (row['bb_lower'] + 0.03 < px < row['bb_center'] - 0.03):
                return False
        elif key == 'prev_candle':
            if val == 'bull' and row['prev_close'] <= row['prev_open']:
                return False
            if val == 'bear' and row['prev_close'] >= row['prev_open']:
                return False
    return True

def run_rule_backtest(date_from, date_to, json_path):
    with open(json_path, 'r', encoding='utf-8') as f:
        rules = json.load(f)

    with cnx.cursor() as cur:
        cur.execute("""
        SELECT cal.date, cal.futures_start_time,
               prev.close AS prev_close,
               prev.bb_center AS prev_bb_center, prev.bb_upper AS prev_bb_upper, prev.bb_lower AS prev_bb_lower, ROUND(prev.bb_upper - prev.bb_lower,2) prev_bb_width,
               ROUND((prev.close - prev.bb_lower) / (prev.bb_upper - prev.bb_lower), 2) AS prev_bb_pos_ratio,
               ROUND((curr.open - prev.bb_lower) / (prev.bb_upper - prev.bb_lower), 2) AS bb_pos_ratio,
               prev.rsi_14 AS prev_rsi14,
               prev.ema_20 AS prev_ema20, prev.ema_60 AS prev_ema60,
               curr.open AS open_price,
               curr.close AS close_60m,
               f5_1.close AS close_5m,
               f5_4.close AS close_5m_4,
               f5_1.high - f5_1.low AS range_5m,
               f5_1.volume AS vol_5m,
               CASE WHEN f5_1.close > f5_1.open THEN 1 ELSE 0 END AS up_5m,
               f5_1.close - f5_1.open AS ret_5m,
               (curr.close - f5_1.close) AS ret_5m_60m, 
               (f5_4.close - f5_1.close) AS ret_5m_5m4, 
               (curr.open - prev.close) AS gap_pt,
               (curr.open - prev.close) / prev.close AS gap_pct,
               CASE WHEN curr.open > prev.close THEN 1 ELSE 0 END AS gap_posㅔㅗㅔ
        FROM calendar cal
        JOIN (
            SELECT t1.* FROM futures_60min t1
            JOIN (
                SELECT date, MAX(time) AS maxtime FROM futures_60min GROUP BY date
            ) t2 ON t1.date = t2.date AND t1.time = t2.maxtime
        ) prev ON prev.date = (SELECT MAX(date) FROM calendar c2 WHERE c2.date < cal.date)
        JOIN futures_60min curr ON curr.date = cal.date AND curr.time = cal.futures_start_time
        JOIN (
            SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
            FROM futures_5min
        ) f5_1 ON f5_1.date = cal.date AND f5_1.rn = 1
        JOIN (
            SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
            FROM futures_5min
        ) f5_4 ON f5_4.date = cal.date AND f5_4.rn = 4
        WHERE cal.date BETWEEN %s AND %s AND cal.cal_yn = 'Y'
        ORDER BY cal.date
        """, (date_from, date_to))
        rows = cur.fetchall()
        df = pd.DataFrame(rows)

    SL_PT = Decimal('1.5')
    TP_MULT = Decimal('0.50')  # TP는 여전히 BB 기준 허용 가능
    detail_rows = []

    with cnx.cursor() as cur:
        cur.execute("DROP TABLE IF EXISTS morning_pfilter_trades_detail")
        cur.execute("""
        CREATE TABLE morning_pfilter_trades_detail (
            trade_date DATE,
            strategy_id VARCHAR(32),
            direction ENUM('LONG','SHORT'),
            entry_price DECIMAL(10,4),
            exit_price DECIMAL(10,4),
            outcome ENUM('TP','SL','CLS'),
            ret_r DECIMAL(8,4),
            bb_width DECIMAL(8,4),
            prev_close DECIMAL(10,4),
            prev_open DECIMAL(10,4),
            prev_rsi14 DECIMAL(8,4),
            prev_bb_center DECIMAL(10,4),
            prev_bb_upper DECIMAL(10,4),
            prev_bb_lower DECIMAL(10,4),
            PRIMARY KEY (trade_date, strategy_id)
        ) ENGINE=InnoDB
        """)
        cnx.commit()

    for rule in rules:
        sid = rule['strategy_id']
        cond = rule['conditions']
        direction = rule.get('direction', 'LONG')
        mode = rule.get('entry_exit_mode', 'first5m_to_close')
        if isinstance(mode, dict):
            entry_key = mode.get('entry', 'close_5m')
            exit_key = mode.get('exit', 'close_60m')
        else:
            entry_key, exit_key = {
                'prevclose_to_open': ('prev_close', 'open_price'),
                'open_to_close': ('open_price', 'close_60m'),
                'first5m_to_close': ('close_5m', 'close_60m')
            }.get(mode, ('close_5m', 'close_60m'))

        for row in df.itertuples():
            if not check_conditions(row._asdict(), cond):
                continue

            entry = Decimal(str(getattr(row, entry_key)))
            exit_px = Decimal(str(getattr(row, exit_key)))
            bb_width = Decimal(str(row.prev_bb_upper)) - Decimal(str(row.prev_bb_lower))

            if direction == 'LONG':
                tp = entry + TP_MULT * bb_width
                sl = entry - SL_PT
                if exit_px >= tp:
                    exit_px = tp; outcome = 'TP'
                elif exit_px <= sl:
                    exit_px = sl; outcome = 'SL'
                else:
                    outcome = 'CLS'
                ret_r = (exit_px - entry) / SL_PT
            else:
                tp = entry - TP_MULT * bb_width
                sl = entry + SL_PT
                if exit_px <= tp:
                    exit_px = tp; outcome = 'TP'
                elif exit_px >= sl:
                    exit_px = sl; outcome = 'SL'
                else:
                    outcome = 'CLS'
                ret_r = (entry - exit_px) / SL_PT

            detail_rows.append((
                row.date, sid, direction, float(entry), float(exit_px), outcome, float(ret_r), float(bb_width),
                float(row.prev_close), float(row.prev_open), float(row.prev_rsi14),
                float(row.prev_bb_center), float(row.prev_bb_upper), float(row.prev_bb_lower)
            ))

    with cnx.cursor() as cur:
        cur.executemany("""
        INSERT INTO morning_pfilter_trades_detail
          (trade_date, strategy_id, direction, entry_price, exit_price, outcome, ret_r, bb_width,
           prev_close, prev_open, prev_rsi14, prev_bb_center, prev_bb_upper, prev_bb_lower)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """, detail_rows)
        cnx.commit()

if __name__ == '__main__':
    run_rule_backtest('2024-01-01', '2025-07-14', 'E:/Project/202410/www/pyFutures/rule_based_strategy_conditions.json')
    print("✅ Rule-based backtest complete with entry/exit flexibility and fixed SL")
