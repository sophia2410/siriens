import pymysql
from decimal import Decimal, getcontext
import configparser

# Wilder‑RSI 14 for first 60‑min open (Kiwoom style)
# --------------------------------------------------
# 1. sequentially walk every 60‑min bar in chrono order
# 2. maintain Wilder avgGain / avgLoss (α = 1/14)
# 3. when the bar is the FIRST bar of a trading day (08:45 or 09:45)
#    → upsert its BB20 / SMA20 and Wilder‑RSI14 into futures_first_open_ind

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

ALPHA = Decimal('1')/Decimal('14')  # Wilder smoothing factor

def log_upsert_input_to_file(date, time_, open_px, window20, avg_gain, avg_loss, gains_seed, losses_seed):
    def fmt(lst):
        return ','.join([f"{p:.3f}" for p in lst])

    log_line = (
        f"[UPSERT INPUT] {date} {time_} | open: {open_px:.3f} | "
        f"window20: [{fmt(window20)}] | "
        f"avg_gain: {avg_gain:.6f}, avg_loss: {avg_loss:.6f} | "
        f"gains_seed: [{fmt(gains_seed)}] | "
        f"losses_seed: [{fmt(losses_seed)}]\n"
    )

    with open("first_open_debug.log", "a", encoding="utf-8") as f:
        f.write(log_line)

def upsert(date, time_, open_px, window20, avg_gain, avg_loss, cur):
    center = sum(window20)/Decimal('20')
    mean_sq = sum((p-center)**2 for p in window20)/Decimal('20')
    std  = mean_sq.sqrt()
    upper = center + std*Decimal('2')
    lower = center - std*Decimal('2')
    if avg_loss == 0:
        rsi = Decimal('100')
    else:
        rs = avg_gain/avg_loss
        rsi = Decimal('100') - (Decimal('100')/(Decimal('1')+rs))
    rsi = rsi.quantize(Decimal('0.001'))

    cur.execute(
        """INSERT INTO futures_first_open_ind
                (date,time,open_price,bb_center,bb_upper,bb_lower,rsi14,sma20)
             VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
             ON DUPLICATE KEY UPDATE
                bb_center=VALUES(bb_center), bb_upper=VALUES(bb_upper),
                bb_lower =VALUES(bb_lower), rsi14=VALUES(rsi14),
                sma20   =VALUES(sma20)""",
        (date, time_, open_px, center, upper, lower, rsi, center))


def build_first_open_ind(date_from: str, date_to: str):
    with cnx.cursor() as cur:
        # chronological 60‑min bars covering period
        cur.execute(
            """SELECT date,time,open,close
                 FROM   futures_60min
                 WHERE  date BETWEEN %s AND %s
                 ORDER  BY datetime""", (date_from, date_to))
        bars = cur.fetchall()

        # first‑bar lookup set
        cur.execute("SELECT date,time FROM vw_first60_bar WHERE date BETWEEN %s AND %s",
                    (date_from, date_to))
        first_map = {(r['date'], r['time']) for r in cur.fetchall()}

        window20 = []  # last 20 closes (Decimal)
        avg_gain = avg_loss = None
        prev_close = None

        for b in bars:
            price = Decimal(str(b['close']))
            if prev_close is not None:
                diff = price - prev_close
                gain = max(diff, Decimal('0'))
                loss = max(-diff, Decimal('0'))
                if avg_gain is None:  # seed after first 14 diffs available
                    gains_seed.append(gain)
                    losses_seed.append(loss)
                else:
                    # Wilder update
                    avg_gain = (avg_gain*Decimal('13') + gain)/Decimal('14')
                    avg_loss = (avg_loss*Decimal('13') + loss)/Decimal('14')
            else:
                gains_seed, losses_seed = [], []

            prev_close = price
            window20.append(price)
            if len(window20) > 20:
                window20.pop(0)

            # seed initialise when we accumulated 14 diffs (i.e., 15 prices)
            if avg_gain is None and len(window20) == 20:  # first time ready
                avg_gain = sum(gains_seed)/Decimal('14')
                avg_loss = sum(losses_seed)/Decimal('14')

            # if this bar is first bar of the day, upsert indicator
            key = (b['date'], b['time'])
            if key in first_map and len(window20) == 20 and avg_gain is not None:

                log_upsert_input_to_file(
                    b['date'], b['time'], Decimal(str(b['open'])),
                    list(window20), avg_gain, avg_loss,
                    list(gains_seed), list(losses_seed)
                )

                upsert(b['date'], b['time'], Decimal(str(b['open'])),
                       list(window20), avg_gain, avg_loss, cur)

        cnx.commit()

if __name__ == '__main__':
    build_first_open_ind('2024-01-01','2025-07-11')
    print('✅ Wilder‑RSI first‑open indicators updated')
