# 백테스트용 스냅샷 생성

import pymysql
import pandas as pd
import configparser
from datetime import datetime, timedelta

# 설정 로딩
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

# 이평선 계산 함수
def get_sma(cursor, table, dt):
    unit = table.split('_')[1]
    minutes = {
        '1min': 1, '5min': 5, '10min': 10,
        '15min': 15, '60min': 60, '1day': 1440
    }.get(unit, None)
    if not minutes:
        return (None, None, None)

    if unit == '1day':
        date_str = dt.strftime('%Y-%m-%d')
        cursor.execute(f"SELECT close FROM {table} WHERE date <= %s ORDER BY date DESC LIMIT 120", (date_str,))
    else:
        cursor.execute(f"SELECT close FROM {table} WHERE datetime <= %s ORDER BY datetime DESC LIMIT 120", (dt,))

    closes = [r['close'] for r in reversed(cursor.fetchall()) if r['close'] is not None]
    sma_5 = round(pd.Series(closes[-5:]).mean(), 3) if len(closes) >= 5 else None
    sma_20 = round(pd.Series(closes[-20:]).mean(), 3) if len(closes) >= 20 else None
    sma_120 = round(pd.Series(closes[-120:]).mean(), 3) if len(closes) >= 120 else None
    return (sma_5, sma_20, sma_120)

# 스냅샷 생성 함수
def process_snapshot_for(cursor, dt):
    cursor.execute("SELECT close FROM futures_1min WHERE datetime = %s", (dt,))
    base_row = cursor.fetchone()
    price = base_row['close'] if base_row else None

    if not price:
        return

    snapshot = {
        'datetime': dt,
        'date': dt.date(),
        'time': dt.time(),
        'price': float(price)
    }

    for tf in ['1min', '5min', '10min', '15min', '60min', '1day']:
        sma5, sma20, sma120 = get_sma(cursor, f"futures_{tf}", dt)
        for p, sma in zip([5, 20, 120], [sma5, sma20, sma120]):
            if sma is not None:
                dist_pct = round((float(price) - float(sma)) / float(sma) * 100, 3)
                dist_pt = round(float(price) - float(sma), 2)
                pos = 1 if float(price) > float(sma) else -1 if float(price) < float(sma) else 0
                snapshot[f'sma_{tf}_{p}'] = float(sma)
            else:
                dist_pct = None
                dist_pt = None
                pos = None
                snapshot[f'sma_{tf}_{p}'] = None
            snapshot[f'dist_pct_{tf}_{p}'] = dist_pct
            snapshot[f'dist_pt_{tf}_{p}'] = dist_pt
            snapshot[f'pos_{tf}_{p}'] = pos

    fields = ', '.join(snapshot.keys())
    placeholders = ', '.join(['%s'] * len(snapshot))
    cursor.execute("DELETE FROM futures_snapshot_momentum WHERE datetime = %s", (dt,))
    sql = f"INSERT INTO futures_snapshot_momentum ({fields}) VALUES ({placeholders})"
    cursor.execute(sql, list(snapshot.values()))

# 루프 실행 (기간 입력)
start = datetime(2025, 4, 30, 8, 45)
end = datetime(2025, 4, 30, 15, 45)

with db.cursor() as cursor:
    current = start
    while current <= end:
        try:
            process_snapshot_for(cursor, current)
            print(f"✅ {current} 처리 완료")
        except Exception as e:
            print(f"⚠️  {current} 처리 실패: {e}")
        current += timedelta(minutes=1)
    db.commit()

db.close()
