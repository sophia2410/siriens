import pymysql
import configparser

def log(msg): print(f"▶ {msg}")

def get_connection():
    config = configparser.ConfigParser()
    config.read('E:/Project/202410/www/boot/common/db/database_config.ini')
    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset'),
        port=config.getint('database', 'port', fallback=3306),
        autocommit=False
    )

INSERT_SQL = """
INSERT INTO rule_based_rowdata (
    date, futures_start_time,
    prev_close, prev_bb_center, prev_bb_upper, prev_bb_lower, prev_bb_width,
    prev_bb_pos_ratio, bb_pos_ratio,
    prev_rsi14, prev_ema20, prev_ema60,
    open_price, close_60m, close_5m, close_5m_4,
    range_5m, vol_5m, up_5m, up_5m_2, up_5m_3, up_5m_4, 
    ret_5m, ret_5m_60m, ret_5m1_5m4,
    range_1m, vol_1m, up_1m, ret_1m,
    gap_pt, gap_pct, gap_pos
)
SELECT
    cal.date, cal.futures_start_time,
    prev.close AS prev_close,
    prev.bb_center, prev.bb_upper, prev.bb_lower,
    ROUND(prev.bb_upper - prev.bb_lower, 2) AS prev_bb_width,

    ROUND((prev.close - prev.bb_lower) / NULLIF(prev.bb_upper - prev.bb_lower, 0), 2) AS prev_bb_pos_ratio,
    ROUND((curr.open - prev.bb_lower) / NULLIF(prev.bb_upper - prev.bb_lower, 0), 2) AS bb_pos_ratio,

    prev.rsi_14, prev.ema_20, prev.ema_60,
    curr.open, curr.close,
    f5_1.close, f5_4.close,
    (f5_1.high - f5_1.low) AS range_5m,
    f5_1.volume,
    CASE WHEN f5_1.close > f5_1.open THEN 1 ELSE 0 END,
    CASE WHEN f5_2.close > f5_2.open THEN 1 ELSE 0 END,
    CASE WHEN f5_3.close > f5_3.open THEN 1 ELSE 0 END,
    CASE WHEN f5_4.close > f5_4.open THEN 1 ELSE 0 END,
    (f5_1.close - f5_1.open),
    (curr.close - f5_1.close),
    (f5_4.close - f5_1.close),
    (f1.high - f1.low),
    f1.volume,
    CASE WHEN f1.close > f1.open THEN 1 ELSE 0 END,
    (f1.close - f1.open),
    (curr.open - prev.close),
    (curr.open - prev.close) / NULLIF(prev.close, 0),
    CASE WHEN curr.open > prev.close THEN 1 ELSE 0 END
FROM calendar cal
JOIN (
    SELECT t1.* 
    FROM futures_60min t1
    JOIN (SELECT date, MAX(time) AS maxtime FROM futures_60min GROUP BY date) t2 
      ON t1.date = t2.date AND t1.time = t2.maxtime
) prev ON prev.date = (SELECT MAX(date) FROM calendar c2 WHERE c2.date < cal.date)
JOIN futures_60min curr ON curr.date = cal.date AND curr.time = cal.futures_start_time
JOIN (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
    FROM futures_5min
) f5_1 ON f5_1.date = cal.date AND f5_1.rn = 1
JOIN (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
    FROM futures_5min
) f5_2 ON f5_2.date = cal.date AND f5_2.rn = 2
JOIN (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
    FROM futures_5min
) f5_3 ON f5_3.date = cal.date AND f5_3.rn = 3
JOIN (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
    FROM futures_5min
) f5_4 ON f5_4.date = cal.date AND f5_4.rn = 4
JOIN (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY date ORDER BY datetime) AS rn
    FROM futures_1min
) f1 ON f1.date = cal.date AND f1.rn = 1
WHERE cal.date > (SELECT COALESCE(MAX(date), '2024-01-01') FROM rule_based_rowdata)
ORDER BY cal.date;
"""

def main():
    log("rule_based_rowdata 신규 적재 시작...")
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(INSERT_SQL)
        conn.commit()
        log("rule_based_rowdata 적재 완료.")
    except Exception as e:
        conn.rollback()
        log(f"오류 발생: {e}")
        raise
    finally:
        conn.close()
        print("전체 파이프라인 완료.")

if __name__ == "__main__":
    main()
