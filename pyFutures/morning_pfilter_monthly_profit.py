import pymysql, pandas as pd, configparser

# 1. DB 설정 불러오기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

cnx = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    autocommit=False
)

# ── 트레이드 로그 로드 ──────────────────────
df = pd.read_sql(
    """SELECT date, direction, entry_price, exit_price, pt_profit
       FROM morning_pfilter_trades
       WHERE date BETWEEN '2024-01-01' AND '2025-07-11'""",
    cnx, parse_dates=['date'])

# ── 가격차 → 금액(₩) 변환 ───────────────────
VALUE_PER_PT = 50_000      # Mini K200
CONTRACTS    = 3

# 가격차(포인트) × 승수 × 계약수
df['pnl_won'] = df['pt_profit'] * VALUE_PER_PT * CONTRACTS

# ── 월별 집계 ───────────────────────────────
df['month']   = df['date'].dt.to_period('M')
month_pnl     = df.groupby('month')['pnl_won'].sum().to_frame('월별_손익(₩)')
month_pnl['누적_손익'] = month_pnl['월별_손익(₩)'].cumsum()

print(month_pnl)