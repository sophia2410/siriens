from flask import Blueprint, request, Response, jsonify
import pandas as pd
from datetime import timedelta
from db.database import get_db_connection
import json

candles_bp = Blueprint('candles', __name__)

table_map = {
    '1m': 'futures_1min',
    '5m': 'futures_5min',
    '15m': 'futures_15min',
    '60m': 'futures_60min',
    '1day': 'futures_1day',
    '1week': 'futures_1week'
}

select_base_fields = ['datetime', 'open', 'high', 'low', 'close', 'volume',
                      'sma_5', 'sma_20', 'sma_120']


@candles_bp.route('/api/candles')
def get_candles():
    date = request.args.get('date')
    tf = request.args.get('tf', '1m')
    sma_list = request.args.get('sma', '5,20,120')
    sma_periods = [int(x) for x in sma_list.split(',') if x.isdigit()]
    from_time = request.args.get('from_time')
    to_time = request.args.get('to_time')

    if tf not in table_map or not date:
        return jsonify({'error': 'Invalid parameters'})

    display_end = pd.to_datetime(date)
    conn = get_db_connection()
    cur = conn.cursor()

    if tf == '1week':
        rows, columns = get_weekly_data(cur, display_end)
    elif tf == '1day':
        rows, columns = get_daily_data(cur, display_end)
    else:
        rows, columns = get_intraday_data(cur, tf, display_end, date)

    if not rows:
        return jsonify([])

    df = prepare_dataframe(rows, columns)

    if tf not in ['1week', '1day'] and from_time and to_time:
        df = df[(df.index.time >= pd.to_datetime(from_time).time()) &
                (df.index.time <= pd.to_datetime(to_time).time())]

    df['date'] = df.index.date.astype(str)

    # ✅ 이평선 처리 (선택된 sma 리스트 기준)
    for w in sma_periods:
        col = f'sma_{w}'
        if col in df.columns:
            df[col] = df[col].astype(float).round(2)

    # ✅ NaN → None (JSON에서 null 로 나가게 함)
    df = df.where(pd.notnull(df), None)  # 여기가 핵심!
    df = df.astype(object)

    # ✅ datetime 포맷 (ISO8601)
    df.index = df.index.strftime('%Y-%m-%dT%H:%M:%SZ')
    df = df.where(pd.notnull(df), None).astype(object)

    # ✅ JSON 직렬화
    json_str = json.dumps(df.reset_index().to_dict(orient='records'), default=str)
    return Response(json_str, mimetype='application/json')


# ————————————————————————————————————————————————————————
# ⬇️ 분기별 로직 함수
# ————————————————————————————————————————————————————————

def get_weekly_data(cur, display_end):
    today = display_end
    monday = today - timedelta(days=today.weekday())
    last_friday = monday - timedelta(days=3)

    cur.execute("""
        SELECT date 
        FROM calendar 
        WHERE date <= %s 
        ORDER BY date DESC 
        LIMIT 180
    """, (last_friday.date(),))
    calendar_dates = [row[0] for row in cur.fetchall()]
    if not calendar_dates:
        return [], select_base_fields

    display_start = pd.to_datetime(calendar_dates[-1]).date()
    display_end = last_friday.date()

    query = """
        SELECT date AS datetime, open, high, low, close, volume,
               sma_5, sma_20, sma_120
        FROM futures_1week
        WHERE date BETWEEN %s AND %s
        ORDER BY date ASC
    """
    cur.execute(query, (display_start, display_end))
    week_rows = list(cur.fetchall())

    # 이번 주 일봉 집계
    cur.execute("""
        SELECT date AS datetime, open, high, low, close, volume,
               sma_5, sma_20, sma_120
        FROM futures_1day
        WHERE date BETWEEN %s AND %s
        ORDER BY date ASC
    """, (monday.date(), today.date()))
    daily_rows = cur.fetchall()

    if daily_rows:
        daily_df = pd.DataFrame(daily_rows, columns=select_base_fields)
        daily_df['datetime'] = pd.to_datetime(daily_df['datetime'])

        weekly = {
            'datetime': monday.date(),
            'open': daily_df.iloc[0]['open'],
            'high': daily_df['high'].max(),
            'low': daily_df['low'].min(),
            'close': daily_df.iloc[-1]['close'],
            'volume': daily_df['volume'].sum(),
            'sma_5': None,
            'sma_20': None,
            'sma_120': None
        }
        week_rows.append(tuple(weekly.values()))

    return week_rows, select_base_fields


def get_daily_data(cur, display_end):
    cur.execute("""
        SELECT date 
        FROM calendar 
        WHERE date <= %s 
        ORDER BY date DESC 
        LIMIT 28
    """, (display_end,))
    display_dates = cur.fetchall()
    if not display_dates:
        return [], select_base_fields

    display_start = display_dates[-1][0]
    query = """
        SELECT date AS datetime, open, high, low, close, volume,
               sma_5, sma_20, sma_120
        FROM futures_1day
        WHERE date BETWEEN %s AND %s
        ORDER BY date ASC
    """
    cur.execute(query, (display_start, display_end))
    return cur.fetchall(), select_base_fields


def get_intraday_data(cur, tf, display_end, date_str):
    table_name = table_map[tf]
    include_rsi = tf in ['5m', '15m', '60m']
    fields = select_base_fields + (['rsi_14'] if include_rsi else [])

    if tf == '60m':
        cur.execute("""
            SELECT date FROM calendar 
            WHERE date < %s 
            ORDER BY date DESC 
            LIMIT 1
        """, (display_end,))
        prev_date_row = cur.fetchone()
        if not prev_date_row:
            return [], fields

        display_start = pd.to_datetime(prev_date_row[0])
        display_end = pd.to_datetime(date_str) + timedelta(days=1)
    else:
        display_start = display_end.replace(hour=0, minute=0, second=0)
        display_end = display_start + timedelta(days=1)

    query = f"""
        SELECT {', '.join(fields)}
        FROM {table_name}
        WHERE datetime BETWEEN %s AND %s
        ORDER BY datetime ASC
    """
    cur.execute(query, (display_start, display_end))
    return cur.fetchall(), fields


def prepare_dataframe(rows, columns):
    decoded_rows = [(r[0].decode() if isinstance(r[0], bytes) else r[0], *r[1:]) for r in rows]
    df = pd.DataFrame(decoded_rows, columns=columns)
    df['datetime'] = pd.to_datetime(df['datetime'])
    df.set_index('datetime', inplace=True)
    df.sort_index(inplace=True)
    return df
