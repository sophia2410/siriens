"""Batch back‑test: p‑filter strategy (포인트 손익 컬럼 추가)
--------------------------------------------------
* 진입 시점 : 시가 + 첫 5분봉 종료 직후
    - 08:50  (시가 08:45)
    - 09:50  (시가 09:45)
* 룰        : p ≥ 0.70 → Long,  p ≤ 0.30 → Short, else Skip
* 청산      : 첫 60분봉 마감가  or  TP 0.5*BB폭  /  SL 0.25*BB폭
* 로그 테이블: morning_pfilter_trades  
      entry_price / exit_price / ret(R) / pt_profit(pt)
--------------------------------------------------"""

import pymysql, joblib, configparser, pandas as pd
from decimal import Decimal, getcontext
from datetime import timedelta

getcontext().prec = 12

cfg = configparser.ConfigParser()
cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')

MODEL_PATH = 'E:/Project/202410/www/pyFutures/first60_model.pkl'
TP_MULT = Decimal('0.50')
SL_MULT = Decimal('0.25')

model = joblib.load(MODEL_PATH)

# ── feature query (시가 + 첫 5m 1bar) ──
FEATURE_SQL = """
WITH first60 AS (
  SELECT date, MIN(datetime) AS first_dt
  FROM   futures_60min
  GROUP  BY date
), first5 AS (
  SELECT  date,
          close AS first5_close,
          ROW_NUMBER() OVER(PARTITION BY date ORDER BY datetime) AS seq
  FROM    futures_5min
)
SELECT  f.date,
        f5.first5_close AS entry_price,
        f.bb_center, f.bb_upper, f.bb_lower, f.rsi14,
        m.range_5m, m.vol_5m, m.up_5m, m.ret_5m,
        -- prev_close
        (
          SELECT s2.close FROM futures_60min s2
          WHERE s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
          ORDER  BY s2.datetime DESC LIMIT 1
        )                               AS prev_close,
        -- gap features
        f5.first5_close - (
          SELECT s2.close FROM futures_60min s2
          WHERE s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
          ORDER  BY s2.datetime DESC LIMIT 1
        )                               AS gap_abs,
        (f5.first5_close - (
          SELECT s2.close FROM futures_60min s2
          WHERE s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
          ORDER  BY s2.datetime DESC LIMIT 1)) /
          (
          SELECT s2.close FROM futures_60min s2
          WHERE s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
          ORDER  BY s2.datetime DESC LIMIT 1)         AS gap_pct,
        CASE WHEN f5.first5_close > (
            SELECT s2.close FROM futures_60min s2
            WHERE s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER  BY s2.datetime DESC LIMIT 1) THEN 1 ELSE 0 END AS gap_pos,
        t.first_dt,
        s.close AS first_close
FROM futures_first_open_ind f
JOIN vw_first5m_1bar   m ON m.date = f.date
JOIN first60           t ON t.date = f.date
JOIN first5            f5 ON f5.date = f.date AND f5.seq = 1
JOIN futures_60min     s ON s.datetime = t.first_dt AND s.date = f.date
WHERE f.date BETWEEN %s AND %s
ORDER BY f.date;
"""

feature_cols = ['bb_center','bb_upper','bb_lower','rsi14',
                'range_5m','vol_5m','up_5m','ret_5m',
                'prev_close','gap_abs','gap_pct','gap_pos']

def run_pfilter_bt(date_from:str, date_to:str):
    db = pymysql.connect(host=cfg.get('database','host'),user=cfg.get('database','user'),
                         password=cfg.get('database','password'),db=cfg.get('database','db'),
                         charset=cfg.get('database','charset'),cursorclass=pymysql.cursors.DictCursor)
    cur = db.cursor()
    cur.execute(FEATURE_SQL,(date_from,date_to))
    rows = cur.fetchall()
    for r in rows:
        X_vals = [float(r[c]) for c in feature_cols]
        X_df   = pd.DataFrame([X_vals], columns=feature_cols)
        p_long = Decimal(str(model.predict_proba(X_df)[0][1]))
        direction = 'SKIP'
        if p_long >= Decimal('0.70'):
            direction='LONG'
        elif p_long <= Decimal('0.30'):
            direction='SHORT'
        if direction=='SKIP':
            continue
        entry = Decimal(str(r['entry_price']))
        bb_width = Decimal(str(r['bb_upper']))-Decimal(str(r['bb_lower']))
        if direction=='LONG':
            tp = entry + TP_MULT*bb_width
            sl = entry - SL_MULT*bb_width
        else:
            tp = entry - TP_MULT*bb_width
            sl = entry + SL_MULT*bb_width
        # 60m bar sequence
        cur.execute("SELECT high,low,close FROM futures_60min WHERE datetime = %s", (r['first_dt'],))
        bars = cur.fetchall()
        outcome='CLS'; exit_px=Decimal(str(bars[-1]['close']))
        for b in bars:
            hi = Decimal(str(b['high'])); lo = Decimal(str(b['low']))
            if direction=='LONG':
                if hi>=tp: outcome='TP'; exit_px=tp; break
                if lo<=sl: outcome='SL'; exit_px=sl; break
            else:
                if lo<=tp: outcome='TP'; exit_px=tp; break
                if hi>=sl: outcome='SL'; exit_px=sl; break
        # 결과 지표
        ret_r = (exit_px-entry)/(TP_MULT*bb_width) if direction=='LONG' else (entry-exit_px)/(TP_MULT*bb_width)
        pt_profit = (exit_px-entry) if direction=='LONG' else (entry-exit_px)
        # store trade
        cur.execute("""
            INSERT INTO morning_pfilter_trades
              (date, entry_price, exit_price, direction, p_long, tp, sl, outcome, ret, pt_profit)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
              exit_price = VALUES(exit_price),
              outcome    = VALUES(outcome),
              ret        = VALUES(ret),
              pt_profit  = VALUES(pt_profit)""",
            (r['date'], entry, exit_px, direction, float(p_long), tp, sl, outcome, float(ret_r), float(pt_profit)))
    db.commit(); cur.close(); db.close()

if __name__=='__main__':
    run_pfilter_bt('2024-01-01','2025-07-11')
    print('✅ p‑filter backtest complete')
