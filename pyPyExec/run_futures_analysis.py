# 파일명: run_futures_analysis.py
import pymysql
import pandas as pd
from datetime import datetime, timedelta, date
import configparser

# 설정 파일 경로
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

def get_minute_data(db, date, start, end):
    with db.cursor() as cursor:
        cursor.execute("""
            SELECT time, open, high, low, close, volume
            FROM futures_1min
            WHERE date = %s AND time BETWEEN %s AND %s
            ORDER BY time
        """, (date, start, end))
        return pd.DataFrame(cursor.fetchall())

def candle_dir(open_, close_):
    return 'U' if close_ > open_ else 'D'

def update_analysis(db, date):
    df = get_minute_data(db, date, '08:45:00', '09:01:00')
    if df.empty or len(df) < 17:
        return f"{date} 데이터 부족"

    today_open = df.iloc[0]['open']
    prev_close = get_yesterday_close(db, date)
    if prev_close is None:
        return f"{date} 전일 종가 없음"
    gap_percent = round((today_open - prev_close) / prev_close * 100, 2)
    gap_type = 'U' if gap_percent > 0.1 else 'D' if gap_percent < -0.1 else 'N'

    pattern = []
    for i in range(3):
        start = i * 5
        open_ = df.iloc[start]['open']
        close_ = df.iloc[start + 4]['close']
        direction = candle_dir(open_, close_)
        pattern.append('양' if direction == 'U' else '음')
    pattern_str = ''.join(pattern)

    diff_0845_0859_pt = round(df.iloc[14]['close'] - df.iloc[0]['open'], 2)
    tick_range_0845_0859 = round(df.iloc[:15]['high'].max() - df.iloc[:15]['low'].min(), 2)
    vol_0845_0900 = int(df.iloc[:16]['volume'].sum())

    open_0900, close_0900 = df.iloc[15]['open'], df.iloc[15]['close']
    candle_0900_dir = candle_dir(open_0900, close_0900)
    diff_0900_pt = round(close_0900 - open_0900, 2)
    tick_range_0900 = round(df.iloc[15]['high'] - df.iloc[15]['low'], 2)

    last5_dir = 'U' if pattern[2] == '양' else 'D'
    open_0901, close_0901 = df.iloc[16]['open'], df.iloc[16]['close']
    candle_0901_dir = candle_dir(open_0901, close_0901)

    match_last5_and_0900 = int(candle_0900_dir == last5_dir)
    match_last5_and_0901 = int(candle_0901_dir == last5_dir)

    high_range = df.iloc[:15]['high'].max()
    low_range = df.iloc[:15]['low'].min()
    breakout = int(
        df.iloc[15]['high'] > high_range or df.iloc[15]['low'] < low_range or
        df.iloc[16]['high'] > high_range or df.iloc[16]['low'] < low_range
    )

    entry_direction = 'buy' if df.iloc[14]['close'] > df.iloc[14]['open'] else 'sell'

    with db.cursor() as cursor:
        cursor.execute("""
            UPDATE futures_1day SET
                gap_percent = %s,
                gap_type = %s,
                pattern_0845_0859 = %s,
                diff_0845_0859_pt = %s,
                tick_range_0845_0859 = %s,
                vol_0845_0900 = %s,
                candle_0900_dir = %s,
                diff_0900_pt = %s,
                tick_range_0900 = %s,
                match_last5_and_0900 = %s,
                match_last5_and_0901 = %s,
                is_morning_breakout = %s,
                entry_direction_a = %s
            WHERE date = %s
        """, (
            gap_percent, gap_type, pattern_str,
            diff_0845_0859_pt, tick_range_0845_0859, vol_0845_0900,
            candle_0900_dir, diff_0900_pt, tick_range_0900,
            match_last5_and_0900, match_last5_and_0901,
            breakout, entry_direction, date
        ))
        db.commit()
    return f"{date} 분석 완료"

def run_batch():
    db = pymysql.connect(**load_config())
    start = date(2024, 1, 2)
    end = date(2025, 4, 17)
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
