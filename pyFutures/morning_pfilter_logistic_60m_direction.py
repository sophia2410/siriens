import pymysql
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import classification_report, roc_auc_score
import configparser
import sys
import joblib

"""
Logistic model – first 60‑min bar direction
------------------------------------------------------------
FEATURES
• BB / RSI / SMA20  (futures_first_open_ind)
• Gap (abs / pct / pos)  vs 전일 마지막 60‑min 봉 종가
• 첫 25분 5‑분봉 모멘텀   (first5m_features)
TARGET
    y = 1  if first_close > first_open
      = 0  otherwise

• class_weight='balanced' 로 학습
"""

# ─────────────────────── DB 연결 ───────────────────────────
cfg = configparser.ConfigParser()
cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')
cnx = pymysql.connect(
    host=cfg.get('database', 'host'),
    user=cfg.get('database', 'user'),
    password=cfg.get('database', 'password'),
    db=cfg.get('database', 'db'),
    charset=cfg.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor,
)

# ─────────────────────── SQL ───────────────────────────────
SQL = """
WITH first60 AS (
    SELECT date, MIN(datetime) AS first_dt
    FROM   futures_60min
    GROUP  BY date
)
SELECT  f.date,
        f.bb_center, f.bb_upper, f.bb_lower, f.rsi14,
        m.range_5m,  m.vol_5m,   m.up_5m,  m.ret_5m,

        -- 직전 거래일 마지막 종가
        (
            SELECT s2.close
            FROM   futures_60min s2
            WHERE  s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER  BY s2.datetime DESC LIMIT 1
        )                                           AS prev_close,

        -- gap 계산용 시가 (cast)
        CAST(f.open_price AS DECIMAL(10,4))          AS first_open,
        CAST(s.close      AS DECIMAL(10,4))          AS first_close,

        -- gap features
        CAST(f.open_price AS DECIMAL(10,4)) - (
            SELECT s2.close
            FROM   futures_60min s2
            WHERE  s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER BY s2.datetime DESC LIMIT 1
        )                                           AS gap_abs,

        (CAST(f.open_price AS DECIMAL(10,4)) - (
            SELECT s2.close
            FROM   futures_60min s2
            WHERE  s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER BY s2.datetime DESC LIMIT 1
        )) / (
            SELECT s2.close
            FROM   futures_60min s2
            WHERE  s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER BY s2.datetime DESC LIMIT 1
        )                                           AS gap_pct,

        CASE WHEN CAST(f.open_price AS DECIMAL(10,4)) > (
            SELECT s2.close
            FROM   futures_60min s2
            WHERE  s2.date = (SELECT MAX(date) FROM futures_60min WHERE date < f.date)
            ORDER BY s2.datetime DESC LIMIT 1
        ) THEN 1 ELSE 0 END                         AS gap_pos

FROM    futures_first_open_ind f
JOIN    vw_first5m_1bar  m  ON m.date = f.date
JOIN    first60         t   ON t.date = f.date
JOIN    futures_60min   s   ON s.datetime = t.first_dt
WHERE   f.date BETWEEN '2024-01-01' AND '2025-07-11';
"""

# ───────────────────── 데이터 로드 ─────────────────────────
with cnx.cursor() as cur:
    cur.execute(SQL)
    rows = cur.fetchall()

df = pd.DataFrame(rows)
print("Rows loaded:", len(df))

# ── 숫자 변환 ──
for col in df.columns:
    if col != 'date':
        df[col] = pd.to_numeric(df[col], errors='coerce')

# ── 타깃 y ──
df['y'] = (df['first_close'] > df['first_open']).astype(int)

# ── 디버그 ──
print(df[['date', 'first_open', 'first_close']].head())
print("NaN first_open :", df['first_open'].isna().sum())
print("NaN first_close:", df['first_close'].isna().sum())
print("positive diff rows:", (df['y'] == 1).sum())

# ── feature set ──
feature_cols = [c for c in df.columns if c not in (
    'date', 'y', 'first_open', 'first_close')]

df = df[df['y'].notna()].copy()
df[feature_cols] = df[feature_cols].fillna(0)
print("Rows after cleaning:", len(df))

vc = df['y'].value_counts()
print("y distribution:", vc.to_dict())
if len(vc) < 2:
    sys.exit("❌ y 가 하나의 클래스만 포함 — 데이터/조인 재확인 필요")

# ───────────────────── 모델 학습 ───────────────────────────
X = df[feature_cols]
y = df['y']
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, shuffle=False)

model = LogisticRegression(max_iter=500, solver='lbfgs', class_weight='balanced')
model.fit(X_train, y_train)

print("\nFeature Coefficients:")
for feat, coef in zip(feature_cols, model.coef_[0]):
    print(f"  {feat:<14}: {coef:+.4f}")

pred = model.predict(X_test)
prob = model.predict_proba(X_test)[:, 1]
print("\n===== TEST REPORT =====")
print(classification_report(y_test, pred, digits=3))
print("AUC :", roc_auc_score(y_test, prob).round(3))

joblib.dump(model, "E:/Project/202410/www/pyFutures/first60_model.pkl")     # ← 새 줄