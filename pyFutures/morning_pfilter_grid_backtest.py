"""Grid Back‑test: p‑filter strategy  (entry = 첫 5분봉 종가)
============================================================
• 진입 시점  : 시가 + 첫 5분봉 **종가** 확정 직후 (08:50 or 09:50)
• 테스트 파라미터
    P_BANDS : (p_long 이상 → LONG, p_long 이하 → SHORT)
    TP_LIST : BB 폭 배율 (롱: +tp*BB , 숏: −tp*BB)
    SL_LIST : BB 폭 배율 (롱: −sl*BB , 숏: +sl*BB)
• entry_price : **first5m_close** 로 계산 → 손익 정확도 ↑
• 출력 : 조합별 trades / wins / losses / win_rate / avg_R / total_R → morning_pfilter_grid
============================================================"""

import pymysql, joblib, configparser, pandas as pd
from decimal import Decimal, getcontext
from datetime import timedelta

getcontext().prec = 12

# ── 파라미터 그리드 ─────────────────────────────────────────
P_BANDS = [(Decimal('0.60'), Decimal('0.40')),
           (Decimal('0.70'), Decimal('0.30')),
           (Decimal('0.75'), Decimal('0.25')),
           (Decimal('0.80'), Decimal('0.20'))]
TP_LIST = [Decimal('0.50'), Decimal('0.40'), Decimal('0.60')]
SL_LIST = [Decimal('0.25'), Decimal('0.20'), Decimal('0.30')]

# ── DB / 모델 로드 ──────────────────────────────────────────
cfg = configparser.ConfigParser(); cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')
cnx = pymysql.connect(host=cfg.get('database','host'),user=cfg.get('database','user'),
                     password=cfg.get('database','password'),db=cfg.get('database','db'),
                     charset=cfg.get('database','charset'),cursorclass=pymysql.cursors.DictCursor)
model = joblib.load('E:/Project/202410/www/pyFutures/first60_model.pkl')

# ── Feature SQL (시가 + 첫 5m 1bar) ─────────────────────────
FEATURE_SQL = """
WITH first60 AS (
  SELECT date, MIN(datetime) AS first_dt
  FROM   futures_60min GROUP BY date ),
first5 AS (
  SELECT f.date, f.open, f.close AS first5_close, f.high, f.low, f.volume,
         ROW_NUMBER() OVER(PARTITION BY f.date ORDER BY f.datetime) AS seq
  FROM   futures_5min f )
SELECT  f.date,
        f.open_price,
        f5.first5_close        AS entry_price,
        f.bb_upper-f.bb_lower  AS bb_width,
        f.bb_center, f.bb_upper, f.bb_lower, f.rsi14,
        m.range_5m, m.vol_5m, m.up_5m, m.ret_5m,
        (SELECT s2.close FROM futures_60min s2
         WHERE s2.date=(SELECT MAX(date)FROM futures_60min WHERE date<f.date)
         ORDER BY s2.datetime DESC LIMIT 1) AS prev_close,
        f.open_price - (SELECT s2.close FROM futures_60min s2
                         WHERE s2.date=(SELECT MAX(date)FROM futures_60min WHERE date<f.date)
                         ORDER BY s2.datetime DESC LIMIT 1)              AS gap_abs,
        (f.open_price - (SELECT s2.close FROM futures_60min s2
                         WHERE s2.date=(SELECT MAX(date)FROM futures_60min WHERE date<f.date)
                         ORDER BY s2.datetime DESC LIMIT 1)) /
         (SELECT s2.close FROM futures_60min s2
          WHERE s2.date=(SELECT MAX(date)FROM futures_60min WHERE date<f.date)
          ORDER BY s2.datetime DESC LIMIT 1)                               AS gap_pct,
        CASE WHEN f.open_price > (SELECT s2.close FROM futures_60min s2
                                   WHERE s2.date=(SELECT MAX(date)FROM futures_60min WHERE date<f.date)
                                   ORDER BY s2.datetime DESC LIMIT 1)
             THEN 1 ELSE 0 END                                             AS gap_pos,
        t.first_dt, s.close AS first_close
FROM futures_first_open_ind f
JOIN vw_first5m_1bar m ON m.date=f.date
JOIN first5 f5        ON f5.date=f.date AND f5.seq=1
JOIN first60 t        ON t.date=f.date
JOIN futures_60min s  ON s.datetime=t.first_dt
WHERE f.date BETWEEN %s AND %s
ORDER BY f.date;"""

FEATURE_COLS = ['bb_center','bb_upper','bb_lower','rsi14',
                'range_5m','vol_5m','up_5m','ret_5m',
                'prev_close','gap_abs','gap_pct','gap_pos']

CREATE_SUMMARY_SQL = """
CREATE TABLE IF NOT EXISTS morning_pfilter_grid (
  ph DECIMAL(4,2), pl DECIMAL(4,2), tp DECIMAL(4,2), sl DECIMAL(4,2),
  trades INT, wins INT, losses INT, win_rate DECIMAL(6,2),
  avg_R DECIMAL(8,3), total_R DECIMAL(10,3), PRIMARY KEY(ph,pl,tp,sl)) ENGINE=InnoDB;"""
with cnx.cursor() as cur: cur.execute(CREATE_SUMMARY_SQL); cnx.commit()

def run_grid(date_from:str, date_to:str):
    cur = cnx.cursor(); cur.execute(FEATURE_SQL,(date_from,date_to))
    data = cur.fetchall(); cur.close()

    df_feat = pd.DataFrame(data)
    X_all = df_feat[FEATURE_COLS].astype(float)
    df_feat['p_long'] = model.predict_proba(X_all)[:,1]

    for pH,pL in P_BANDS:
        for tpM in TP_LIST:
            for slM in SL_LIST:
                trades=wins=losses=0; total_r=Decimal('0')
                for row in df_feat.itertuples():
                    p_val = Decimal(str(row.p_long))
                    direction='SKIP'
                    if p_val>=pH: direction='LONG'
                    elif p_val<=pL: direction='SHORT'
                    if direction=='SKIP': continue
                    trades+=1
                    entry = Decimal(str(row.entry_price))
                    bb_width = Decimal(str(row.bb_width))
                    tp = entry + tpM*bb_width if direction=='LONG' else entry - tpM*bb_width
                    sl = entry - slM*bb_width if direction=='LONG' else entry + slM*bb_width
                    exit_px = Decimal(str(row.first_close))
                    if (direction=='LONG' and exit_px>=tp) or (direction=='SHORT' and exit_px<=tp):
                        exit_px = tp
                    elif (direction=='LONG' and exit_px<=sl) or (direction=='SHORT' and exit_px>=sl):
                        exit_px = sl
                    ret_r = (exit_px-entry)/(tpM*bb_width) if direction=='LONG' else (entry-exit_px)/(tpM*bb_width)
                    if ret_r>0: wins+=1
                    elif ret_r<0: losses+=1
                    total_r += Decimal(str(ret_r))
                if trades==0: continue
                win_rate = round(wins/trades*100,2); avg_r = round(total_r/trades,3)
                with cnx.cursor() as cur:
                    cur.execute("""
                      INSERT INTO morning_pfilter_grid(ph,pl,tp,sl,trades,wins,losses,win_rate,avg_R,total_R)
                      VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                      ON DUPLICATE KEY UPDATE trades=%s,wins=%s,losses=%s,win_rate=%s,avg_R=%s,total_R=%s""",
                      (pH,pL,tpM,slM,trades,wins,losses,win_rate,avg_r,total_r,
                       trades,wins,losses,win_rate,avg_r,total_r))
    cnx.commit()

if __name__=='__main__':
    run_grid('2024-01-01','2025-07-11')
    print('✅ grid back‑test complete → morning_pfilter_grid')
