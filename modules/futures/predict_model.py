import sys, json, pymysql, joblib, configparser
import pandas as pd
import warnings
warnings.filterwarnings("ignore")

# 로컬 유틸 함수 import
sys.path.append("E:/Project/202410/www/pyFutures")  # 모듈 경로 등록
from bb_rsi_utils import calculate_from_db  # 함수 import

# log로 출력
with open("E:/Project/202410/www/modules/futures/arg_debug.txt", "a", encoding="utf-8") as f:
    f.write("[INPUT] sys.argv[1] = " + sys.argv[1] + "\n")

try:
    MODEL_PATH = 'E:/Project/202410/www/pyFutures/first60_model.pkl'
    model = joblib.load(MODEL_PATH)

    cfg = configparser.ConfigParser()
    cfg.read('E:/Project/202410/www/boot/common/db/database_config.ini')

    input_data = json.loads(sys.argv[1])
    open_price = float(input_data['open_price'])
    date       = input_data['date']

    # prev_close 구하기
    db = pymysql.connect(
        host=cfg.get('database','host'),
        user=cfg.get('database','user'),
        password=cfg.get('database','password'),
        db=cfg.get('database','db'),
        charset=cfg.get('database','charset'),
        cursorclass=pymysql.cursors.DictCursor
    )
    cur = db.cursor()
    cur.execute("""
        SELECT close FROM futures_60min
        WHERE date = (SELECT MAX(date) FROM futures_60min WHERE date < %s)
        ORDER BY datetime DESC LIMIT 1
    """, (date,))
    row = cur.fetchone()
    cur.close()
    db.close()

    if not row or not row['close']:
        print(json.dumps({"error": "전일 종가 조회 실패"}))
        sys.exit(0)

    prev_close = float(row['close'])

    # calculate_from_db로 BB/RSI 계산
    date_str = input_data['date']
    if len(date_str) == 10:
        datetime_str = date_str + " 08:45:00"
    else:
        datetime_str = date_str

    indicators = calculate_from_db(datetime_str, open_price)

    # gap 관련 변수 계산
    gap_abs = open_price - prev_close
    gap_pct = gap_abs / prev_close
    gap_pos = 1 if open_price > prev_close else 0

    X_vals = [
        indicators['bb_center'],
        indicators['bb_upper'],
        indicators['bb_lower'],
        indicators['rsi'],
        float(input_data['range_5m']),
        float(input_data['vol_5m']),
        float(input_data['up_5m']),
        float(input_data['ret_5m']),
        prev_close,
        gap_abs,
        gap_pct,
        gap_pos
    ]
    feature_cols = [
        'bb_center','bb_upper','bb_lower','rsi14',
        'range_5m','vol_5m','up_5m','ret_5m',
        'prev_close','gap_abs','gap_pct','gap_pos'
    ]

    X_df = pd.DataFrame([X_vals], columns=feature_cols)
    p = round(float(model.predict_proba(X_df)[0][1]), 4)
    direction = 'LONG' if p >= 0.70 else 'SHORT' if p <= 0.30 else 'SKIP'

    # 모델 입력 데이터 로그
    with open("E:/Project/202410/www/modules/futures/arg_debug.txt", "a", encoding="utf-8") as f:
        f.write("[FEATURE_COLS] " + json.dumps(feature_cols, ensure_ascii=False) + "\n")
        f.write("[X_VALS] "       + json.dumps(X_vals, ensure_ascii=False)       + "\n")
        f.write("[X_DF] "         + X_df.to_json(orient="records", force_ascii=False) + "\n")

    # 결과 출력
    result = json.dumps({"p": p, "direction": direction})
    print(result)
    sys.stdout.flush()

except Exception as e:
    error_result = json.dumps({"error": str(e)})
    print(error_result)
    sys.stdout.flush()
