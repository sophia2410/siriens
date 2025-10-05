import pymysql
from datetime import timedelta
import configparser

def generate_missing_features(source_table, feature_table):
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

    with db.cursor() as cursor:
        cursor.execute(f"""
            SELECT DISTINCT date FROM {source_table}
            WHERE date NOT IN (SELECT DISTINCT date FROM {feature_table})
            ORDER BY date DESC
            LIMIT 1
        """)
        result = cursor.fetchone()
        missing_dates = [result['date']] if result else []

    print(f"📅 {source_table} → 생성 필요 일자 수: {len(missing_dates)}")

    for d in missing_dates:
        with db.cursor() as cursor:
            cursor.execute(f"SELECT * FROM {source_table} WHERE date = %s ORDER BY time ASC", (d,))
            candles = cursor.fetchall()

            cursor.execute(f"SELECT * FROM {source_table} WHERE date > %s ORDER BY date, time ASC LIMIT 1", (d,))
            next_open = cursor.fetchone()

            cursor.execute(f"SELECT * FROM {source_table} WHERE date > %s ORDER BY date DESC, time DESC LIMIT 1", (d,))
            next_close = cursor.fetchone()

        if not (candles and next_open and next_close):
            continue

        last = candles[-1]
        try:
            day_open = float(candles[0]['open'])
            day_close = float(candles[-1]['close'])
            day_high = max(float(c['high']) for c in candles)
            day_low = min(float(c['low']) for c in candles)

            gap = round(float(next_open['open']) - day_close, 2)
            gap_dir = "상승" if gap > 0 else "하락"
            next_dir = "상승" if float(next_close['close']) > float(next_open['open']) else "하락"

            bb_upper = float(last['bb_upper'])
            bb_lower = float(last['bb_lower'])
            bb_center = float(last['bb_center'])
            close = float(last['close'])
            ema20 = float(last['ema_20'])
            ema60 = float(last['ema_60'])

            bb_width = bb_upper - bb_lower
            bb_position = (close - bb_lower) / bb_width if bb_width > 0 else 0
            bb_level = (
                "상단 위" if bb_position > 1.0 else
                "상단 근처" if bb_position >= 0.85 else
                "중심선 위" if bb_position >= 0.6 else
                "중심선 근처" if bb_position >= 0.4 else
                "중심선 아래" if bb_position >= 0.15 else
                "하단 근처" if bb_position >= 0.0 else
                "하단 아래"
            )
            bb_slope = "상승" if bb_center > ema60 else "하락" if bb_center < ema60 else "수평"

            ema_cross = "정배열" if ema20 > ema60 else "역배열"
            ema_gap = ema20 - ema60
            ema_bb_gap = ema20 - bb_center

            index_vs_ema20 = round(close - ema20, 3)
            index_vs_ema60 = round(close - ema60, 3)

            rsi = float(last['rsi_14'])
            rsi_range = "과열" if rsi >= 70 else "강세" if rsi >= 50 else "중립" if rsi >= 30 else "과매도"

            macd = float(last.get('macd', 0))
            signal = float(last.get('macd_signal', 0))
            hist = float(last.get('macd_hist', 0))

            macd_position = "양수" if macd > 0 else "음수"
            macd_vs_signal = (
                "상승교차" if macd > signal else
                "하락교차" if macd < signal else
                "동일"
            )
            macd_hist_sign = "양봉" if hist > 0 else "음봉"

            macd_hist_change = None
            if len(candles) >= 2:
                prev_hist = float(candles[-2].get('macd_hist', 0))
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
                cursor.execute(f"""
                    REPLACE INTO {feature_table} (
                        date, bb_width, bb_slope, bb_position, bb_level,
                        ema_cross, ema_gap, ema_bb_gap, index_vs_ema20, index_vs_ema60,
                        rsi_14, rsi_range,
                        macd, macd_hist, macd_hist_sign, macd_position, macd_vs_signal, macd_hist_change,
                        candle_type, body_pct, upper_tail_pct, lower_tail_pct,
                        volume, gap, gap_dir, next_close_dir
                    ) VALUES (
                        %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s,
                        %s, %s,
                        %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s,
                        %s, %s, %s, %s
                    )
                """, (
                    d, bb_width, bb_slope, bb_position, bb_level,
                    ema_cross, ema_gap, ema_bb_gap, index_vs_ema20, index_vs_ema60,
                    rsi, rsi_range,
                    macd, hist, macd_hist_sign, macd_position, macd_vs_signal, macd_hist_change,
                    candle_type, body_pct, upper_tail_pct, lower_tail_pct,
                    volume, gap, gap_dir, next_dir
                ))
                db.commit()

            print(f"✅ [{source_table}] {d} 처리 완료")

        except Exception as e:
            print(f"❌ [{source_table}] {d} 처리 중 오류: {e}")

    db.close()
    print("🎉 전체 완료")

# 사용 예시:
# generate_missing_features("futures_15min", "futures_bb_rsi_features_15m")
# generate_missing_features("futures_60min", "futures_bb_rsi_features_60m")
