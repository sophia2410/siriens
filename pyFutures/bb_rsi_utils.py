import pymysql
import configparser
import numpy as np

def calculate_from_db(target_datetime, current_close):
    # 설정 로드
    cfg = configparser.ConfigParser()
    cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')

    # DB 연결
    db = pymysql.connect(
        host=cfg.get('database','host'),
        user=cfg.get('database','user'),
        password=cfg.get('database','password'),
        db=cfg.get('database','db'),
        charset=cfg.get('database','charset'),
        cursorclass=pymysql.cursors.DictCursor
    )
    cur = db.cursor()

    # 종가 및 RSI 14 조회 (최근 19개)
    sql = """
        SELECT close, rsi_14
        FROM futures_60min
        WHERE datetime < %s
        ORDER BY datetime DESC
        LIMIT 19
    """
    cur.execute(sql, (target_datetime,))
    rows = cur.fetchall()
    db.close()

    if len(rows) < 14:
        raise ValueError("RSI 계산을 위해 최소 14개 이상의 과거 데이터가 필요합니다.")

    # 최근부터 오래된 순으로 reverse
    rows.reverse()

    closes = [float(row['close']) for row in rows]
    rsi_last = float(rows[-1]['rsi_14'])

    # 볼린저밴드 계산용 종가 20개 구성
    bb_closes = closes[-19:] + [float(current_close)]
    bb_center = np.mean(bb_closes)
    bb_std = np.std(bb_closes, ddof=0)
    bb_upper = bb_center + 2 * bb_std
    bb_lower = bb_center - 2 * bb_std

    # RSI 역산 계산
    # step1: 마지막 RSI → RS → avg_gain / avg_loss 역산
    if rsi_last >= 100:
        rs_last = 9999
    elif rsi_last <= 0:
        rs_last = 0.0001
    else:
        rs_last = rsi_last / (100 - rsi_last)

    gains = []
    losses = []
    for i in range(14):
        delta = closes[i + 1] - closes[i]
        gains.append(max(delta, 0))
        losses.append(max(-delta, 0))

    avg_loss = np.mean(losses)
    avg_gain = rs_last * avg_loss

    # step2: 현재 변화 반영
    last_delta = current_close - closes[-1]
    last_gain = max(last_delta, 0)
    last_loss = max(-last_delta, 0)

    avg_gain = (avg_gain * 13 + last_gain) / 14
    avg_loss = (avg_loss * 13 + last_loss) / 14

    if avg_loss == 0:
        rsi = 100
    else:
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))

    return {
        'bb_center': round(bb_center, 3),
        'bb_upper': round(bb_upper, 3),
        'bb_lower': round(bb_lower, 3),
        'rsi': round(rsi, 3)
    }
