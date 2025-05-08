# routes/candles.py
from flask import Blueprint, Response
import pandas as pd
from datetime import timedelta
from db.database import get_db_connection
from utils.indicators import add_moving_averages
import json

candles_bp = Blueprint('candles', __name__)

@candles_bp.route('/api/candles')
def get_candles():
    from flask import request, jsonify
    import pandas as pd
    from datetime import timedelta
    from db.database import get_db_connection
    from utils.indicators import add_moving_averages

    date = request.args.get('date')
    tf = request.args.get('tf', '1m')
    sma_list = request.args.get('sma', '5,20,120')
    sma_periods = [int(x) for x in sma_list.split(',') if x.isdigit()]
    from_time = request.args.get('from_time')  # 예: '08:45:00'
    to_time = request.args.get('to_time')      # 예: '09:01:00'

    conn = get_db_connection()
    cur = conn.cursor()

    display_end = pd.to_datetime(date)
    max_window = max(sma_periods)

    lookback_days = {
        '1m': 10,
        '5m': 10,
        '15m': 20,
        '60m': 50,
        '1day': 300
    }.get(tf, 5)

    if tf == '1day':    # 거래일 기준 display용 최근 40개
        cur.execute("""
            SELECT date 
            FROM calendar 
            WHERE date <= %s 
            ORDER BY date DESC 
            LIMIT 28
        """, (display_end,))
        display_dates = cur.fetchall()

        if len(display_dates) < 28:
            return jsonify([])

        display_start = display_dates[-1][0]
        query_start = display_end - pd.Timedelta(days=lookback_days)

        query = """
            SELECT date AS datetime, open, high, low, close, volume
            FROM futures_1day
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        """
        cur.execute(query, (query_start, display_end))
        rows = cur.fetchall()

        if not rows:
            return jsonify([])

        decoded_rows = [(r[0].decode() if isinstance(r[0], bytes) else r[0], *r[1:]) for r in rows]
        df = pd.DataFrame(decoded_rows, columns=['datetime', 'open', 'high', 'low', 'close', 'volume'])
        df['datetime'] = pd.to_datetime(df['datetime'])
        df.set_index('datetime', inplace=True)
        df.sort_index(inplace=True)
    else:
        display_start = display_end.replace(hour=0, minute=0, second=0)
        query_start = display_start - pd.Timedelta(days=lookback_days)
        display_end = display_start + timedelta(days=1)

        if tf == '1m':
            query = """
                SELECT datetime, open, high, low, close, volume
                FROM futures_1min
                WHERE datetime BETWEEN %s AND %s
                ORDER BY datetime ASC
            """
            cur.execute(query, (query_start, display_end))
            rows = cur.fetchall()

            decoded_rows = [(r[0].decode() if isinstance(r[0], bytes) else r[0], *r[1:]) for r in rows]
            df = pd.DataFrame(decoded_rows, columns=['datetime', 'open', 'high', 'low', 'close', 'volume'])
            df['datetime'] = pd.to_datetime(df['datetime'])
            df.set_index('datetime', inplace=True)
            df.sort_index(inplace=True)

        else:
            # 1분봉 raw data로 불러온 후 Pandas 집계
            query = """
                SELECT datetime, open, high, low, close, volume
                FROM futures_1min
                WHERE datetime BETWEEN %s AND %s
                ORDER BY datetime ASC
            """
            cur.execute(query, (query_start, display_end))
            rows = cur.fetchall()

            decoded_rows = [(r[0].decode() if isinstance(r[0], bytes) else r[0], *r[1:]) for r in rows]
            df = pd.DataFrame(decoded_rows, columns=['datetime', 'open', 'high', 'low', 'close', 'volume'])
            df['datetime'] = pd.to_datetime(df['datetime'])
            df.set_index('datetime', inplace=True)
            df.sort_index(inplace=True)

            # 🕐 시간 단위 매핑
            rule_map = {
                '5m': '5T',
                '15m': '15T',
                '60m': '60T'
            }
            rule = rule_map.get(tf, '5T')

            first_time = df.index[0]
            offset = pd.Timedelta(minutes=first_time.minute)
            df = df.resample(rule, offset=offset).agg({
                'open': 'first',
                'high': 'max',
                'low': 'min',
                'close': 'last',
                'volume': 'sum'
            }).dropna()

    # 이평선 계산
    df = add_moving_averages(df, windows=sma_periods)

    # datetime -> timestamp

    # ✅ 결과 제한
    if tf == '1day':
        df = df.last('40D')  # 최근 40일만
    else:
        df = df.loc[(df.index >= display_start) & (df.index < display_end)]

    # ✅ 시간 필터 (분봉 only)
    if tf != '1day' and from_time and to_time:
        df = df.between_time(from_time, to_time)

    # ✅ date 컬럼 추가
    df['date'] = df.index.date.astype(str)

    # 소수점 정리
    for w in sma_periods:
        df[f'sma_{w}'] = df[f'sma_{w}'].round(2)

    # JSON 응답
    df.index = df.index.strftime('%Y-%m-%dT%H:%M:%SZ')
    df = df.where(pd.notnull(df), None)

    
    df = df.astype(object)  # numpy 타입 강제 제거

    json_str = json.dumps(df.reset_index().to_dict(orient='records'), default=str)
    return Response(json_str, mimetype='application/json')