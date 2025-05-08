
# 파일명: run_futures_analysis_late_open_v2.py
import pymysql
import pandas as pd
from datetime import datetime, timedelta, date
import configparser

CONFIG_PATH = 'E:/Project/202410/www/boot/common/db/database_config.ini'

def load_config():
    config = configparser.ConfigParser()
    config.read(CONFIG_PATH)
    return {
        'host': config.get('database', 'host'),
        'user': config.get('database', 'user'),
        'password': config.get('database', 'password'),
        'db': config.get('database', 'db'),
        'charset': config.get('database', 'charset'),
        'cursorclass': pymysql.cursors.DictCursor
    }

def get_yesterday_close(db, date):
    with db.cursor() as cursor:
        cursor.execute("""
            SELECT close FROM futures_1day 
            WHERE date = (SELECT MAX(date) FROM futures_1day WHERE date < %s)
        """, (date,))
        row = cursor.fetchone()
        return row['close'] if row else None

def get_minute_data_range(db, date, from_time, to_time):
    with db.cursor() as cursor:
        cursor.execute("""
            SELECT time, open, high, low, close, volume
            FROM futures_1min
            WHERE date = %s AND time BETWEEN %s AND %s
            ORDER BY time
        """, (date, from_time, to_time))
        return pd.DataFrame(cursor.fetchall())

def get_exact_candle(df, from_time, to_time):
    sub_df = df[(df['time'] >= from_time) & (df['time'] <= to_time)]
    if sub_df.empty:
        return None
    return {
        'open': sub_df.iloc[0]['open'],
        'close': sub_df.iloc[-1]['close'],
        'high': sub_df['high'].max(),
        'low': sub_df['low'].min(),
        'volume': sub_df['volume'].sum()
    }

def candle_dir(open_, close_):
    return 'U' if close_ > open_ else 'D'

def update_analysis(db, date):
    with db.cursor() as cursor:
        cursor.execute("SELECT futures_start_time FROM calendar WHERE date = %s", (date,))
        result = cursor.fetchone()
        start_time = result['futures_start_time'] if result and result['futures_start_time'] else '08:45:00'
        base_time = datetime.strptime(f"{date} {start_time}", "%Y-%m-%d %H:%M:%S")

    # 분석에 필요한 시점 계산
    t1_start = base_time
    t2_start = base_time + timedelta(minutes=5)
    t3_start = base_time + timedelta(minutes=10)
    t0900 = base_time + timedelta(minutes=15)
    t0901 = base_time + timedelta(minutes=16)

    # 데이터 조회 범위
    from_time = t1_start.strftime("%H:%M:%S")
    to_time = t0901.strftime("%H:%M:%S")
    df = get_minute_data_range(db, date, from_time, to_time)
    if df.empty or len(df) < 17:
        return f"{date} 데이터 부족 (시작={start_time})"

    prev_close = get_yesterday_close(db, date)
    if prev_close is None:
        return f"{date} 전일 종가 없음"
    today_open = df.iloc[0]['open']
    gap_percent = round((today_open - prev_close) / prev_close * 100, 2)
    gap_type = 'U' if gap_percent > 0.1 else 'D' if gap_percent < -0.1 else 'N'

    # 3개 5분봉 패턴
    c1 = get_exact_candle(df, t1_start.strftime("%H:%M:%S"), (t1_start + timedelta(minutes=4, seconds=59)).strftime("%H:%M:%S"))
    c2 = get_exact_candle(df, t2_start.strftime("%H:%M:%S"), (t2_start + timedelta(minutes=4, seconds=59)).strftime("%H:%M:%S"))
    c3 = get_exact_candle(df, t3_start.strftime("%H:%M:%S"), (t3_start + timedelta(minutes=4, seconds=59)).strftime("%H:%M:%S"))
    if not all([c1, c2, c3]):
        return f"{date} 5분봉 부족"

    pattern = [candle_dir(c['open'], c['close']) for c in [c1, c2, c3]]
    pattern_str = ''.join(['양' if d == 'U' else '음' for d in pattern])
    tick_range_0845_0859 = round(max(c1['high'], c2['high'], c3['high']) - min(c1['low'], c2['low'], c3['low']), 2)
    vol_0845_0859 = c1['volume'] + c2['volume'] + c3['volume']

    # 첫 15분봉 시가, 종가, 차이
    open_0845_0859 = c1['open']  # 첫 5분봉 시가
    close_0845_0859 = c3['close']  # 세번째 5분봉 종가
    diff_0845_0859_pt = round(close_0845_0859 - open_0845_0859, 2)

    # 09:00, 09:01 1분봉
    c_0900 = get_exact_candle(df, t0900.strftime("%H:%M:%S"), t0900.strftime("%H:%M:%S"))
    c_0901 = get_exact_candle(df, t0901.strftime("%H:%M:%S"), t0901.strftime("%H:%M:%S"))
    if not c_0900 or not c_0901:
        return f"{date} 1분봉 부족"

    candle_0900_dir = candle_dir(c_0900['open'], c_0900['close'])
    diff_0900_pt = round(c_0900['close'] - c_0900['open'], 2)
    tick_range_0900 = round(c_0900['high'] - c_0900['low'], 2)

    last5_dir = pattern[2]
    candle_0901_dir = candle_dir(c_0901['open'], c_0901['close'])
    match_last5_and_0900 = int(candle_0900_dir == last5_dir)
    match_last5_and_0901 = int(candle_0901_dir == last5_dir)

    max_high = max(c1['high'], c2['high'], c3['high'])
    min_low = min(c1['low'], c2['low'], c3['low'])
    breakout = int(c_0900['high'] > max_high or c_0900['low'] < min_low or c_0901['high'] > max_high or c_0901['low'] < min_low)

    entry_direction = 'buy' if c3['close'] > c3['open'] else 'sell'

    with db.cursor() as cursor:
        cursor.execute("""
            INSERT INTO futures_analysis
              ( date, 
                gap_percent,
                gap_type,
                pattern_0845_0859,
                tick_range_0845_0859,
                vol_0845_0859,
                candle_0900_dir,
                diff_0900_pt,
                tick_range_0900,
                match_last5_and_0900,
                match_last5_and_0901,
                is_morning_breakout,
                entry_direction_a,
                open_0845_0859,
                close_0845_0859,
                diff_0845_0859_pt )
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """, (
            date, 
            gap_percent, gap_type, pattern_str,
            tick_range_0845_0859, vol_0845_0859,
            candle_0900_dir, diff_0900_pt, tick_range_0900,
            match_last5_and_0900, match_last5_and_0901,
            breakout, entry_direction, 
            open_0845_0859, close_0845_0859, diff_0845_0859_pt
        ))
        db.commit()
    return f"{date} 분석 완료"

def run_batch():
    db = pymysql.connect(**load_config())
    start = date(2023, 10, 1)
    end = date(2025, 4, 30)
    days = pd.bdate_range(start=start, end=end).strftime("%Y-%m-%d").tolist()
    for d in days:
        try:
            result = update_analysis(db, d)
            print(result)
        except Exception as e:
            print(f"{d} 오류 발생: {e}")
    db.close()

if __name__ == "__main__":
    run_batch()
