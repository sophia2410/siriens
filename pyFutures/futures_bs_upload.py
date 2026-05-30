import os
import re
from decimal import Decimal, ROUND_HALF_UP
from datetime import datetime, date, time
from typing import Optional, Tuple, List, Dict

import pandas as pd
import pymysql
import configparser
from tqdm import tqdm

# =========================
# 고정 설정
# =========================
RUN_ID = 1
BASE_PATH = r"E:\Project\202410\data\_futures\buysell"
CONFIG_PATH = r"E:\Project\202410\www\boot\common\db\database_config.ini"

FEE_RATE = Decimal("0.00003")  # 0.003%
FILE_GLOB_EXT = (".xlsx", ".xls", ".xlsm")

# 종목명으로 포인트가치 자동판정이 애매하면 강제:
FORCE_POINT_VALUE = None  # 50000 또는 250000 또는 None

# 디버그 로그(컬럼/side 값 확인용)
DEBUG = True


# =========================
# 유틸
# =========================
def parse_trade_date_from_filename(filename: str) -> Optional[date]:
    base = os.path.basename(filename)
    m = re.search(r"(20\d{2})[-_\.]?(0[1-9]|1[0-2])[-_\.]?([0-2]\d|3[01])", base)
    if not m:
        return None
    return date(int(m.group(1)), int(m.group(2)), int(m.group(3)))


def pick_engine(path: str) -> Optional[str]:
    ext = os.path.splitext(path)[1].lower()
    if ext in (".xlsx", ".xlsm"):
        return "openpyxl"
    if ext == ".xls":
        return "xlrd"
    return None


def find_col(df: pd.DataFrame, candidates: List[str]) -> Optional[str]:
    cols = list(df.columns)
    for c in candidates:
        if c in cols:
            return c
    return None


def to_time(val) -> Optional[time]:
    if pd.isna(val):
        return None
    if isinstance(val, datetime):
        return val.time()
    if isinstance(val, time):
        return val
    s = str(val).strip()
    try:
        return datetime.strptime(s.zfill(8) if len(s) == 7 else s, "%H:%M:%S").time()
    except Exception:
        try:
            return pd.to_datetime(s).time()
        except Exception:
            return None


def infer_point_value_from_name(name: str) -> int:
    if FORCE_POINT_VALUE in (50000, 250000):
        return int(FORCE_POINT_VALUE)
    s = (name or "")
    if ("미니" in s) or ("mini" in s.lower()):
        return 50000
    return 250000


def money_round(x: Decimal) -> int:
    return int(x.quantize(Decimal("1"), rounding=ROUND_HALF_UP))


# =========================
# 엑셀 읽기 (buysell/주문체결 둘 다 대응)
# =========================
def read_fills_excel(filepath: str, trade_date: date) -> Tuple[pd.DataFrame, int]:
    engine = pick_engine(filepath)
    if engine is None:
        raise ValueError("지원하지 않는 확장자")

    df = pd.read_excel(filepath, engine=engine)
    df = df.copy()

    # 가능한 컬럼 후보들(최대한 넓게)
    col_order_no = find_col(df, ["주문No.", "주문번호", "주문No", "order_no"])
    col_name     = find_col(df, ["종목명", "종목", "name", "symbol"])
    col_side     = find_col(df, ["구분", "매매구분", "매수/매도", "side", "action", "type"])
    col_qty      = find_col(df, ["체결량", "체결수량", "수량", "qty", "quantity"])
    col_price    = find_col(df, ["체결가격", "체결가", "가격", "price"])
    col_time     = find_col(df, ["체결시간", "체결시각", "시간", "time"])
    col_dt       = find_col(df, ["bar_dt", "datetime", "일시", "체결일시", "일자시간"])

    if col_side is None or col_qty is None or col_price is None:
        raise ValueError(f"[{os.path.basename(filepath)}] 필수 컬럼(side/qty/price)을 찾지 못했습니다. "
                         f"현재 컬럼: {list(df.columns)}")

    # qty/price 숫자화 + 체결만(>0)
    df[col_qty] = pd.to_numeric(df[col_qty], errors="coerce")
    df[col_price] = pd.to_numeric(df[col_price], errors="coerce")
    df = df[(df[col_qty].fillna(0) > 0) & (df[col_price].notna()) & (df[col_side].notna())]

    # bar_dt 만들기: datetime 컬럼이 있으면 그걸 우선, 없으면 파일명 날짜 + time 컬럼 결합
    if col_dt:
        df["bar_dt"] = pd.to_datetime(df[col_dt], errors="coerce")
        df = df[df["bar_dt"].notna()]
    else:
        if col_time is None:
            raise ValueError(f"[{os.path.basename(filepath)}] datetime/time 컬럼이 없어 bar_dt를 만들 수 없습니다.")
        t_series = df[col_time].apply(to_time)
        df["bar_dt"] = [datetime.combine(trade_date, t) if t else None for t in t_series]
        df = df[df["bar_dt"].notna()]

    # 정렬
    if col_order_no:
        df.sort_values(["bar_dt", col_order_no], inplace=True)
    else:
        df.sort_values(["bar_dt"], inplace=True)

    # 포인트가치
    first_name = str(df[col_name].iloc[0]) if (col_name and len(df) > 0) else ""
    point_value = infer_point_value_from_name(first_name)

    df_std = pd.DataFrame({
        "order_no": df[col_order_no].fillna("").astype(str) if col_order_no else "",
        "name": df[col_name].fillna("").astype(str) if col_name else "",
        "side_raw": df[col_side].astype(str),
        "qty": df[col_qty].astype(int),
        "price": df[col_price].astype(float),
        "bar_dt": df["bar_dt"],
        "note_type": df[find_col(df, ["주문구분", "note", "memo"])].fillna("").astype(str) if find_col(df, ["주문구분", "note", "memo"]) else "",
    })

    if DEBUG:
        uniq = sorted(set([str(x) for x in df_std["side_raw"].dropna().unique().tolist()]))[:30]
        print(f" - side_raw unique(sample): {uniq}")

    return df_std, point_value


# =========================
# side/action 판정 (BUY/SELL/매수/매도/OPEN_*/CLOSE_* 지원)
# =========================
def normalize_signal(side_raw: str) -> Optional[str]:
    """
    반환값:
      - "BUY" / "SELL"
      - "OPEN_LONG" / "OPEN_SHORT" / "CLOSE_PART" / "CLOSE_ALL"
      - None (판정불가)
    """
    s0 = (side_raw or "").strip()
    s = s0.upper().replace(" ", "")

    # 이미 action이 명확한 케이스
    if "OPEN_LONG" in s:
        return "OPEN_LONG"
    if "OPEN_SHORT" in s:
        return "OPEN_SHORT"
    if "CLOSE_ALL" in s:
        return "CLOSE_ALL"
    if "CLOSE_PART" in s:
        return "CLOSE_PART"

    # 한국어
    if ("매수" in s0) and ("매도" not in s0):
        return "BUY"
    if "매도" in s0:
        return "SELL"

    # 영문/약어
    if s in ("BUY", "B", "LONG", "L"):
        return "BUY"
    if s in ("SELL", "S", "SHORT", "SH", "SS"):
        return "SELL"

    # 접두 형태 (BUY@, SELL@ 등)
    if s.startswith("BUY"):
        return "BUY"
    if s.startswith("SELL"):
        return "SELL"

    return None


# =========================
# 체결 -> trade(action) 생성 + PnL/수수료
# =========================
def build_trade_records(df_std: pd.DataFrame, point_value: int):
    pos_side = None  # "LONG" / "SHORT" / None
    pos_qty = 0
    avg_price = Decimal("0")
    realized_points = Decimal("0")
    trades: List[Dict] = []
    seq = 1

    def fee_of(price: Decimal, qty: int) -> int:
        return money_round(price * Decimal(point_value) * Decimal(qty) * FEE_RATE)

    def add_trade(action: str, bar_dt: datetime, price: Decimal, qty: int, fee: int, note: Optional[str]):
        nonlocal seq
        if note and len(note) > 120:
            note = note[:120]
        trades.append({
            "seq": seq,
            "action": action,
            "bar_dt": bar_dt,
            "price": price,
            "qty": qty,
            "fee_amount": fee,
            "note": note
        })
        seq += 1

    for r in df_std.itertuples(index=False):
        sig = normalize_signal(r.side_raw)
        if sig is None:
            continue

        price = Decimal(str(r.price))
        qty = int(r.qty)

        base_note = []
        if str(r.order_no).strip():
            base_note.append(f"order:{str(r.order_no).strip()}")
        if str(r.note_type).strip():
            base_note.append(f"type:{str(r.note_type).strip()}")
        note = ";".join(base_note) if base_note else None

        # --------- action이 직접 들어온 케이스 ---------
        if sig in ("OPEN_LONG", "OPEN_SHORT", "CLOSE_PART", "CLOSE_ALL"):
            # 수수료는 이 row qty 기준
            fee = fee_of(price, qty)

            # CLOSE_*는 현재 포지션을 기준으로 처리 (포지션 없으면 스킵)
            if sig.startswith("CLOSE"):
                if pos_qty <= 0 or pos_side is None:
                    # 포지션 없는데 close가 나오면 스킵(데이터 이상)
                    continue

                close_qty = pos_qty if sig == "CLOSE_ALL" else min(qty, pos_qty)
                fee_close = fee_of(price, close_qty)

                add_trade(sig, r.bar_dt, price, close_qty, fee_close, note)

                if pos_side == "LONG":
                    realized_points += (price - avg_price) * Decimal(close_qty)
                else:
                    realized_points += (avg_price - price) * Decimal(close_qty)

                pos_qty -= close_qty
                if pos_qty == 0:
                    pos_side, avg_price = None, Decimal("0")

                continue

            # OPEN_LONG / OPEN_SHORT
            # 만약 반대포지션이 있으면 flip 처리 (qty 기준)
            if sig == "OPEN_LONG":
                if pos_side == "SHORT" and pos_qty > 0:
                    # 전량 청산 후 신규오픈으로 처리
                    close_qty = pos_qty
                    fee_close = fee_of(price, close_qty)
                    add_trade("CLOSE_ALL", r.bar_dt, price, close_qty, fee_close, (note + " | flip-close") if note else "flip-close")
                    realized_points += (avg_price - price) * Decimal(close_qty)
                    pos_side, pos_qty, avg_price = None, 0, Decimal("0")

                add_trade("OPEN_LONG", r.bar_dt, price, qty, fee, note)
                if pos_qty == 0:
                    pos_side, pos_qty, avg_price = "LONG", qty, price
                else:
                    avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                    pos_qty += qty
                continue

            if sig == "OPEN_SHORT":
                if pos_side == "LONG" and pos_qty > 0:
                    close_qty = pos_qty
                    fee_close = fee_of(price, close_qty)
                    add_trade("CLOSE_ALL", r.bar_dt, price, close_qty, fee_close, (note + " | flip-close") if note else "flip-close")
                    realized_points += (price - avg_price) * Decimal(close_qty)
                    pos_side, pos_qty, avg_price = None, 0, Decimal("0")

                add_trade("OPEN_SHORT", r.bar_dt, price, qty, fee, note)
                if pos_qty == 0:
                    pos_side, pos_qty, avg_price = "SHORT", qty, price
                else:
                    avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                    pos_qty += qty
                continue

        # --------- BUY/SELL 케이스 (기존 로직) ---------
        if sig in ("BUY", "SELL"):
            fee = fee_of(price, qty)

            if pos_qty == 0:
                if sig == "BUY":
                    add_trade("OPEN_LONG", r.bar_dt, price, qty, fee, note)
                    pos_side, pos_qty, avg_price = "LONG", qty, price
                else:
                    add_trade("OPEN_SHORT", r.bar_dt, price, qty, fee, note)
                    pos_side, pos_qty, avg_price = "SHORT", qty, price
                continue

            if pos_side == "LONG":
                if sig == "BUY":
                    add_trade("OPEN_LONG", r.bar_dt, price, qty, fee, note)
                    avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                    pos_qty += qty
                else:
                    close_qty = min(qty, pos_qty)
                    fee_close = fee_of(price, close_qty)
                    add_trade("CLOSE_ALL" if close_qty == pos_qty else "CLOSE_PART", r.bar_dt, price, close_qty, fee_close, note)
                    realized_points += (price - avg_price) * Decimal(close_qty)
                    pos_qty -= close_qty
                    if pos_qty == 0:
                        pos_side, avg_price = None, Decimal("0")

                    if qty > close_qty:
                        open_qty = qty - close_qty
                        fee_open = fee_of(price, open_qty)
                        add_trade("OPEN_SHORT", r.bar_dt, price, open_qty, fee_open, (note + " | flip-open") if note else "flip-open")
                        pos_side, pos_qty, avg_price = "SHORT", open_qty, price

            elif pos_side == "SHORT":
                if sig == "SELL":
                    add_trade("OPEN_SHORT", r.bar_dt, price, qty, fee, note)
                    avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                    pos_qty += qty
                else:
                    close_qty = min(qty, pos_qty)
                    fee_close = fee_of(price, close_qty)
                    add_trade("CLOSE_ALL" if close_qty == pos_qty else "CLOSE_PART", r.bar_dt, price, close_qty, fee_close, note)
                    realized_points += (avg_price - price) * Decimal(close_qty)
                    pos_qty -= close_qty
                    if pos_qty == 0:
                        pos_side, avg_price = None, Decimal("0")

                    if qty > close_qty:
                        open_qty = qty - close_qty
                        fee_open = fee_of(price, open_qty)
                        add_trade("OPEN_LONG", r.bar_dt, price, open_qty, fee_open, (note + " | flip-open") if note else "flip-open")
                        pos_side, pos_qty, avg_price = "LONG", open_qty, price

    fee_sum = sum(int(t["fee_amount"]) for t in trades)
    return trades, realized_points, fee_sum, (pos_side, pos_qty, avg_price)


# =========================
# DB
# =========================
def connect_db():
    config = configparser.ConfigParser()
    config.read(CONFIG_PATH)

    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset'),
        autocommit=False
    )


def get_day_id(cursor, run_id: int, trade_date: date) -> Optional[int]:
    cursor.execute(
        "SELECT day_id FROM futures_sim_run_day WHERE run_id=%s AND trade_date=%s",
        (run_id, trade_date)
    )
    row = cursor.fetchone()
    return int(row[0]) if row else None


def upsert_day_row(cursor, run_id: int, trade_date: date) -> int:
    cursor.execute(
        "INSERT IGNORE INTO futures_sim_run_day (run_id, trade_date) VALUES (%s,%s)",
        (run_id, trade_date)
    )
    day_id = get_day_id(cursor, run_id, trade_date)
    if day_id is None:
        raise RuntimeError("day_id 조회 실패")
    return day_id

def delete_existing_trades(cursor, day_id: int):
    cursor.execute("DELETE FROM futures_sim_trade WHERE day_id=%s", (day_id,))


def insert_trades(cursor, day_id: int, trades: List[Dict]):
    sql = """
    INSERT INTO futures_sim_trade
    (day_id, seq, action, bar_dt, price, qty, fee_amount, note)
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
    """
    for t in trades:
        cursor.execute(sql, (
            day_id,
            t["seq"],
            t["action"],
            t["bar_dt"],
            str(t["price"]),
            int(t["qty"]),
            int(t["fee_amount"]),
            t["note"]
        ))

def update_day_summary(cursor, day_id: int, pnl_points: Decimal, point_value: int, fee_total: int):
    pnl_amount = money_round(pnl_points * Decimal(point_value))
    pnl_amount_net = pnl_amount - int(fee_total)

    cursor.execute(
        "UPDATE futures_sim_run_day "
        "SET pnl_points=%s, pnl_amount=%s, fee_total=%s, pnl_amount_net=%s "
        "WHERE day_id=%s",
        (str(pnl_points.quantize(Decimal("0.0001"))), pnl_amount, fee_total, pnl_amount_net, day_id)
    )

def count_trades(cursor, day_id: int) -> int:
    cursor.execute("SELECT COUNT(*) FROM futures_sim_trade WHERE day_id=%s", (day_id,))
    return int(cursor.fetchone()[0])


# =========================
# 파일 스캔: 날짜별 최신 1개 선택
# =========================
def scan_latest_files_by_date(base_path: str) -> Dict[date, str]:
    candidates: Dict[date, List[str]] = {}

    for fn in os.listdir(base_path):
        if fn.startswith("~$"):
            continue
        if not fn.lower().endswith(FILE_GLOB_EXT):
            continue

        fp = os.path.join(base_path, fn)
        d = parse_trade_date_from_filename(fp)
        if not d:
            continue
        candidates.setdefault(d, []).append(fp)

    chosen: Dict[date, str] = {}
    for d, fps in candidates.items():
        fps.sort(key=lambda x: os.path.getmtime(x), reverse=True)
        chosen[d] = fps[0]
    return dict(sorted(chosen.items(), key=lambda kv: kv[0]))


# =========================
# 메인: 무조건 삭제 후 재등록
# =========================
def main():
    files_by_date = scan_latest_files_by_date(BASE_PATH)
    if not files_by_date:
        print(f"[ERR] 업로드할 파일이 없습니다: {BASE_PATH}")
        return

    print(f"[INFO] 대상 날짜: {len(files_by_date)}개 (날짜별 최신 파일 1개 선택)")
    print(f"[INFO] RUN_ID 고정: {RUN_ID}")

    db = connect_db()
    try:
        with db.cursor() as cursor:
            for trade_date, filepath in files_by_date.items():
                base = os.path.basename(filepath)
                print(f"\n===== {trade_date} / {base} =====")

                try:
                    # 1) 파싱/계산 먼저 (여기서 실패하면 DB 건드리지 않음)
                    df_std, point_value = read_fills_excel(filepath, trade_date)

                    if DEBUG:
                        print(f" - parsed rows: {len(df_std)}")

                    # 2) day_id 확보 + 기존 trade 삭제
                    day_id = upsert_day_row(cursor, RUN_ID, trade_date)
                    delete_existing_trades(cursor, day_id)

                    if df_std.empty:
                        # 체결 없으면 trade는 비워두고 day는 0으로
                        update_day_summary(cursor, day_id, Decimal("0"), point_value if point_value else 50000, 0)
                        db.commit()
                        print(" - df_std empty: 기존 trade 삭제 + day 0 갱신")
                        continue

                    trades, pnl_points, fee_total, pos_info = build_trade_records(df_std, point_value)

                    if DEBUG:
                        print(f" - trades built: {len(trades)}")

                    # 3) trade insert + day summary update
                    insert_trades(cursor, day_id, trades)

                    update_day_summary(cursor, day_id, pnl_points, point_value, fee_total)
                    db.commit()

                    inserted = count_trades(cursor, day_id)
                    print(f" - 재등록 완료: inserted_trades={inserted} / point_value={point_value}")
                    print(f" - pnl_points={pnl_points} / fee_total={fee_total}")

                except PermissionError as e:
                    db.rollback()
                    print(f"[ERR] 파일 접근 불가(엑셀 열려있을 가능성): {base} -> {e}")
                except Exception as e:
                    db.rollback()
                    print(f"[ERR] {base} 처리 실패 -> {e}")

    finally:
        db.close()


if __name__ == "__main__":
    main()
