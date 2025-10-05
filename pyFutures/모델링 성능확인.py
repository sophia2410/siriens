import pymysql, configparser
import pandas as pd
import matplotlib.pyplot as plt

# ── DB 연결 ─────────────────────────────────────
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

# ── SQL 실행 (morning_pfilter_trades에서 p_long + 수익 여부 가져오기) ──
sql = """
SELECT p_long, CASE WHEN ret > 0 THEN 1 ELSE 0 END AS label
FROM morning_pfilter_trades
WHERE p_long IS NOT NULL AND p_long BETWEEN 0 AND 1
ORDER BY p_long;
"""

with cnx.cursor() as cur:
    cur.execute(sql)
    rows = cur.fetchall()

df = pd.DataFrame(rows)

# ── p_bin 구간 나누기 (0.1 단위) ─────────────────
df['p_bin'] = pd.cut(df['p_long'], bins=[0.0,0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1.0])
grouped = df.groupby('p_bin')['label'].agg(['count', 'mean']).reset_index()
grouped.columns = ['p_bin', 'samples', 'actual_win_rate']
grouped['predicted_p'] = grouped['p_bin'].apply(lambda x: x.mid)

# ── 그래프 시각화 ──────────────────────────────
plt.figure(figsize=(8,6))
plt.plot(grouped['predicted_p'], grouped['actual_win_rate'], marker='o', label='Actual win rate')
plt.plot([0, 1], [0, 1], '--', color='gray', label='Perfect calibration')
plt.xlabel('Predicted probability (p_long)')
plt.ylabel('Actual win rate')
plt.title('📈 Calibration Curve: p_long vs Actual Win Rate')
plt.grid(True)
plt.legend()
plt.tight_layout()
plt.show()
