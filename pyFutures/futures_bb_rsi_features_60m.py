import pymysql
from datetime import timedelta
import configparser

# 설정 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# DB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor
)

# 거래일 목록 가져오기
with db.cursor() as cursor:
    cursor.execute("SELECT DISTINCT date FROM futures_bb_rsi_60m ORDER BY date")
    all_dates = [row['date'] for row in cursor.fetchall()]

# 거래일 쌍 구성 (오늘, 다음 거래일)
dates_to_run = list(zip(all_dates[:-1], all_dates[1:]))

print(f"총 처리 대상 날짜 쌍: {len(dates_to_run)}")

for d, next_d in dates_to_run:
    with db.cursor() as cursor:
        cursor.execute("SELECT * FROM futures_bb_rsi_60m WHERE date = %s ORDER BY time ASC", (d,))
        candles = cursor.fetchall()

        cursor.execute("SELECT * FROM futures_bb_rsi_60m WHERE date = %s ORDER BY time ASC LIMIT 1", (next_d,))
        next_open = cursor.fetchone()

        cursor.execute("SELECT * FROM futures_bb_rsi_60m WHERE date = %s ORDER BY time DESC LIMIT 1", (next_d,))
        next_close = cursor.fetchone()

    if not (candles and next_open and next_close):
        continue

    last = candles[-1]
    day_open = candles[0]['open']
    day_close = candles[-1]['close']
    day_high = max(c['high'] for c in candles)
    day_low = min(c['low'] for c in candles)

    try:
        gap = round(next_open['open'] - day_close, 2)
        gap_dir = "상승" if gap > 0 else "하락"
        next_dir = "상승" if next_close['close'] > next_open['open'] else "하락"

        bb_width = last['bb_upper'] - last['bb_lower']
        bb_position = (last['close'] - last['bb_lower']) / bb_width if bb_width > 0 else 0
        bb_level = (
            "상단 위"       if bb_position > 1.0 else
            "상단 근처"     if bb_position >= 0.85 else
            "중심선 위"     if bb_position >= 0.6 else
            "중심선 근처"   if bb_position >= 0.4 else
            "중심선 아래"   if bb_position >= 0.15 else
            "하단 근처"     if bb_position >= 0.0 else
            "하단 아래"
        )
        bb_slope = "상승" if last['bb_center'] > last['ema60'] else "하락" if last['bb_center'] < last['ema60'] else "수평"

        ema_cross = "정배열" if last['ema20'] > last['ema60'] else "역배열"
        ema_gap = last['ema20'] - last['ema60']
        ema_bb_gap = last['ema20'] - last['bb_center']

        rsi = last['rsi14']
        rsi_range = "과열" if rsi >= 70 else "강세" if rsi >= 50 else "중립" if rsi >= 30 else "과매도"

        macd = last.get('macd')
        signal = last.get('macd_signal')
        hist = last.get('macd_hist')

        macd_position = "양수" if macd > 0 else "음수"
        macd_vs_signal = (
            "상승교차" if macd > signal else
            "하락교차" if macd < signal else
            "동일"
        )
        macd_hist_sign = "양봉" if hist > 0 else "음봉"

        # 직전 macd_hist가 존재하는 경우 변화 판단
        macd_hist_change = None
        if len(candles) >= 2:
            prev = candles[-2]
            prev_hist = prev.get('macd_hist')
            if prev_hist is not None:
                if hist > prev_hist:
                    macd_hist_change = "증가"
                elif hist < prev_hist:
                    macd_hist_change = "감소"
                else:
                    macd_hist_change = "변화없음"

        body = abs(day_close - day_open)
        total = day_high - day_low
        if total == 0:
            print(f"⚠️ {d} - 고가와 저가 동일 → 생략")
            continue

        body_pct = round(body / total, 3)
        upper_tail_pct = round((day_high - max(day_open, day_close)) / total, 3)
        lower_tail_pct = round((min(day_open, day_close) - day_low) / total, 3)

        volume = last['volume']
        candle_type = "양봉" if day_close > day_open else "음봉" if day_close < day_open else "도지"

        with db.cursor() as cursor:
            cursor.execute("""
                REPLACE INTO futures_bb_rsi_features_60m (
                    date, bb_width, bb_slope, bb_position, bb_level,
                    ema_cross, ema_gap, ema_bb_gap,
                    rsi_14, rsi_range,
                    macd, macd_hist, macd_hist_sign, macd_position, macd_vs_signal, macd_hist_change,
                    candle_type, body_pct, upper_tail_pct, lower_tail_pct,
                    volume, gap, gap_dir, next_close_dir
                ) VALUES (
                    %s, %s, %s, %s, %s,
                    %s, %s, %s,
                    %s, %s,
                    %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s,
                    %s, %s, %s, %s
                )
            """, (
                d, bb_width, bb_slope, bb_position, bb_level,
                ema_cross, ema_gap, ema_bb_gap,
                rsi, rsi_range,
                macd, hist, macd_hist_sign, macd_position, macd_vs_signal, macd_hist_change,
                candle_type, body_pct, upper_tail_pct, lower_tail_pct,
                volume, gap, gap_dir, next_dir
            ))
            db.commit()

        print(f"✅ {d} 처리 완료")

    except Exception as e:
        print(f"❌ {d} 처리 중 오류 발생: {e}")

db.close()
print("🎉 전체 완료")