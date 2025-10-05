import pymysql
from decimal import Decimal, getcontext
import configparser
from bb_rsi_utils import calculate_from_db  # 📌 bb_rsi_utils 활용

getcontext().prec = 12

cfg = configparser.ConfigParser()
cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')

cnx = pymysql.connect(
    host=cfg.get('database', 'host'),
    user=cfg.get('database', 'user'),
    password=cfg.get('database', 'password'),
    db=cfg.get('database', 'db'),
    charset=cfg.get('database', 'charset'),
    autocommit=False,
    cursorclass=pymysql.cursors.DictCursor,
)

def upsert(date, time_, open_px, bb_center, bb_upper, bb_lower, rsi, cur):
    center = Decimal(str(bb_center))
    upper  = Decimal(str(bb_upper))
    lower  = Decimal(str(bb_lower))
    rsi    = Decimal(str(rsi)).quantize(Decimal('0.001'))
    open_px = Decimal(str(open_px))
    
    cur.execute(
        """INSERT INTO futures_first_open_ind
                (date,time,open_price,bb_center,bb_upper,bb_lower,rsi14,sma20)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
           ON DUPLICATE KEY UPDATE
                bb_center=VALUES(bb_center), bb_upper=VALUES(bb_upper),
                bb_lower =VALUES(bb_lower), rsi14=VALUES(rsi14),
                sma20   =VALUES(sma20)""",
        (date, time_, open_px, center, upper, lower, rsi, center)
    )

def build_first_open_ind(date_from: str, date_to: str):
    with cnx.cursor() as cur:
        # 📌 1. 일자별 첫 봉 open 가격 조회
        cur.execute("""
            SELECT f.date, f.time, f.open
              FROM futures_60min f
              JOIN (
                    SELECT date, MIN(datetime) AS first_dt
                    FROM futures_60min
                    WHERE date BETWEEN %s AND %s
                    GROUP BY date
              ) sub ON f.datetime = sub.first_dt
              ORDER BY f.date
        """, (date_from, date_to))
        first_bars = cur.fetchall()

        for bar in first_bars:
            date = bar['date']
            time_ = bar['time']
            open_px = float(bar['open'])

            # 📌 2. 'date + time' → datetime 형태로 조합
            datetime_str = f"{date} {time_}"

            # 📌 3. bb_rsi_utils 모듈로 BB20 / RSI14 계산
            try:
                indicators = calculate_from_db(datetime_str, open_px)
                upsert(date, time_, open_px,
                       indicators['bb_center'],
                       indicators['bb_upper'],
                       indicators['bb_lower'],
                       indicators['rsi'], cur)
            except Exception as e:
                print(f"[{date} {time_}] 계산 실패 → {str(e)}")
                continue

        cnx.commit()

if __name__ == '__main__':
    build_first_open_ind('2024-01-01', '2025-07-11')
    print('✅ Wilder‑RSI first‑open indicators updated')
