import os
import re
from decimal import Decimal, ROUND_HALF_UP
from datetime import datetime, date, time
from typing import Optional, Tuple, List, Dict, Any

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


def normalize_action(action) -> str:
    return str(action or "").strip().upper()


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

    wrong_dates = sorted({x.date() for x in df["bar_dt"] if x.date() != trade_date})
    if wrong_dates:
        raise ValueError(f"[{os.path.basename(filepath)}] 파일명 일자({trade_date})와 다른 체결일자가 있습니다: {wrong_dates}")

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
TRADE_ACTIONS = (
    "OPEN_LONG",
    "OPEN_SHORT",
    "CLOSE_PART",
    "CLOSE_ALL",
    "CLOSE_CARRY_LONG",
    "CLOSE_CARRY_SHORT",
)


def normalize_signal(side_raw: str) -> Optional[str]:
    """
    반환값:
      - "BUY" / "SELL"
      - TRADE_ACTIONS 항목
      - None (판정불가)
    """
    s0 = (side_raw or "").strip()
    s = s0.upper().replace(" ", "")

    # 이미 action이 명확한 케이스
    for action in TRADE_ACTIONS:
        if action in s:
            return action

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
def build_trade_records(df_std: pd.DataFrame, point_value: int, initial_pos: Optional[Dict[str, Any]] = None):
    """
    안정화 기준:
      1) 날짜가 넘어와 시작한 포지션 전체를 carry_qty로 본다.
      2) 청산은 항상 실제 체결수량 기준 min(qty, pos_qty)만 처리한다.
      3) CLOSE_CARRY_*도 완전청산일 수 있으므로 position_qty_after로 청산 여부를 판단한다.
      4) 같은 방향 추가진입은 평균단가(avg_price)를 갱신하고, 청산 손익은 평균단가 기준으로 계산한다.
    """
    initial_pos = initial_pos or {}
    pos_side = initial_pos.get("pos_side")  # "LONG" / "SHORT" / None
    pos_qty = int(initial_pos.get("pos_qty") or 0)
    avg_price = Decimal(str(initial_pos.get("avg_price") or "0"))

    # 방식 A: 오늘 시작 시 들고 온 포지션 전체를 이월 수량으로 본다.
    carry_side = pos_side
    carry_qty = pos_qty if pos_side is not None and pos_qty > 0 else 0

    realized_points = Decimal("0")
    trades: List[Dict] = []
    seq = 1

    def fee_of(price: Decimal, qty: int) -> int:
        return money_round(price * Decimal(point_value) * Decimal(qty) * FEE_RATE)

    def signed_position_qty() -> int:
        if pos_side == "LONG":
            return int(pos_qty)
        if pos_side == "SHORT":
            return -int(pos_qty)
        return 0

    def position_side_after() -> str:
        return pos_side if pos_side in ("LONG", "SHORT") and pos_qty > 0 else "NONE"

    def add_trade(action: str, bar_dt: datetime, price: Decimal, qty: int, fee: int, note: Optional[str]):
        nonlocal seq
        if qty <= 0:
            return
        if note and len(note) > 120:
            note = note[:120]
        trades.append({
            "seq": seq,
            "action": action,
            "bar_dt": bar_dt,
            "price": price,
            "qty": qty,
            "fee_amount": fee,
            "position_qty_after": signed_position_qty(),
            "position_side_after": position_side_after(),
            "note": note
        })
        seq += 1

    def add_note_extra(note: Optional[str], extra: str) -> str:
        return f"{extra};{note}" if note else extra

    def open_position(open_side: str, bar_dt: datetime, price: Decimal, qty: int, note: Optional[str], action: Optional[str] = None):
        nonlocal pos_side, pos_qty, avg_price
        if qty <= 0:
            return

        action = action or ("OPEN_LONG" if open_side == "LONG" else "OPEN_SHORT")

        if pos_qty == 0 or pos_side is None:
            pos_side = open_side
            pos_qty = qty
            avg_price = price
        elif pos_side == open_side:
            avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
            pos_qty += qty
        else:
            raise RuntimeError("open_position called with opposite side while position exists")

        add_trade(action, bar_dt, price, qty, fee_of(price, qty), note)

    def close_segment(action: str, bar_dt: datetime, price: Decimal, qty: int, note: Optional[str]):
        nonlocal pos_side, pos_qty, avg_price, realized_points
        if qty <= 0 or pos_qty <= 0 or pos_side is None:
            return 0

        close_qty = min(int(qty), int(pos_qty))

        # 손익은 lot 가격이 아니라 현재 평균단가 기준으로 계산한다.
        if pos_side == "LONG":
            realized_points += (price - avg_price) * Decimal(close_qty)
        else:
            realized_points += (avg_price - price) * Decimal(close_qty)

        pos_qty -= close_qty
        if pos_qty == 0:
            pos_side = None
            avg_price = Decimal("0")

        add_trade(action, bar_dt, price, close_qty, fee_of(price, close_qty), note)
        return close_qty

    def add_close_trades(bar_dt: datetime, price: Decimal, qty: int, note: Optional[str]):
        """
        현재 포지션에서 qty만큼만 청산한다.
        이월수량(carry_qty)이 남아 있으면 먼저 CLOSE_CARRY_*로 처리하고,
        남은 청산분은 CLOSE_PART/CLOSE_ALL로 처리한다.
        """
        nonlocal carry_qty
        if qty <= 0 or pos_qty <= 0 or pos_side is None:
            return 0

        requested_qty = int(qty)
        total_close_qty = min(requested_qty, int(pos_qty))
        remain = total_close_qty
        closed_total = 0

        # 오늘 시작 시 넘어온 포지션은 전체가 carry 대상이다.
        if remain > 0 and carry_qty > 0 and carry_side == pos_side:
            carry_close_qty = min(remain, int(carry_qty))
            carry_action = "CLOSE_CARRY_LONG" if pos_side == "LONG" else "CLOSE_CARRY_SHORT"
            carry_note = add_note_extra(note, f"carry_avg:{avg_price}")
            closed = close_segment(carry_action, bar_dt, price, carry_close_qty, carry_note)
            carry_qty -= closed
            remain -= closed
            closed_total += closed

        # carry가 아닌 당일 신규/추가 포지션 청산분
        if remain > 0 and pos_qty > 0 and pos_side is not None:
            regular_action = "CLOSE_ALL" if remain >= pos_qty else "CLOSE_PART"
            closed = close_segment(regular_action, bar_dt, price, remain, note)
            remain -= closed
            closed_total += closed

        return closed_total

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
        if sig in TRADE_ACTIONS:
            if sig.startswith("CLOSE"):
                add_close_trades(r.bar_dt, price, qty, note)
                continue

            if sig == "OPEN_LONG":
                if pos_side == "SHORT" and pos_qty > 0:
                    closed_qty = add_close_trades(r.bar_dt, price, qty, add_note_extra(note, "flip-close"))
                    qty -= closed_qty
                if qty > 0:
                    open_position("LONG", r.bar_dt, price, qty, note)
                continue

            if sig == "OPEN_SHORT":
                if pos_side == "LONG" and pos_qty > 0:
                    closed_qty = add_close_trades(r.bar_dt, price, qty, add_note_extra(note, "flip-close"))
                    qty -= closed_qty
                if qty > 0:
                    open_position("SHORT", r.bar_dt, price, qty, note)
                continue

        # --------- BUY/SELL 케이스 ---------
        if sig in ("BUY", "SELL"):
            incoming_side = "LONG" if sig == "BUY" else "SHORT"

            if pos_qty == 0 or pos_side is None:
                open_position(incoming_side, r.bar_dt, price, qty, note)
                continue

            if pos_side == incoming_side:
                open_position(incoming_side, r.bar_dt, price, qty, note)
                continue

            # 반대 방향 체결: 현재 포지션을 체결수량만큼 청산하고, 남으면 반대 포지션 신규 진입
            closed_qty = add_close_trades(r.bar_dt, price, qty, note)
            remain_qty = qty - closed_qty
            if remain_qty > 0:
                flip_note = add_note_extra(note, "flip-open")
                open_position(incoming_side, r.bar_dt, price, remain_qty, flip_note)

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


def fetch_trades_before_date(cursor, run_id: int, trade_date: date) -> List[Dict]:
    cursor.execute(
        "SELECT t.action, t.price, t.qty "
        "FROM futures_sim_run_day d "
        "JOIN futures_sim_trade t ON t.day_id = d.day_id "
        "WHERE d.run_id=%s AND d.trade_date < %s "
        "ORDER BY d.trade_date ASC, t.seq ASC, t.trade_id ASC",
        (run_id, trade_date)
    )
    return [
        {"action": normalize_action(row[0]), "price": Decimal(str(row[1])), "qty": int(row[2])}
        for row in cursor.fetchall()
    ]


def fetch_previous_day_trades(cursor, run_id: int, trade_date: date) -> Tuple[Optional[date], List[Dict]]:
    cursor.execute(
        "SELECT d.day_id, d.trade_date "
        "FROM futures_sim_run_day d "
        "WHERE d.run_id=%s AND d.trade_date < %s "
        "AND EXISTS (SELECT 1 FROM futures_sim_trade t WHERE t.day_id = d.day_id) "
        "ORDER BY d.trade_date DESC LIMIT 1",
        (run_id, trade_date)
    )
    row = cursor.fetchone()
    if not row:
        return None, []

    prev_day_id = int(row[0])
    prev_trade_date = row[1]
    cursor.execute(
        "SELECT action, price, qty FROM futures_sim_trade "
        "WHERE day_id=%s ORDER BY seq ASC, trade_id ASC",
        (prev_day_id,)
    )
    trades = [
        {"action": normalize_action(row[0]), "price": Decimal(str(row[1])), "qty": int(row[2])}
        for row in cursor.fetchall()
    ]
    return prev_trade_date, trades


def calc_position_from_trades(trades: List[Dict]) -> Dict[str, Any]:
    pos_side = None
    pos_qty = 0
    avg_price = Decimal("0")

    for t in trades:
        action = t["action"]
        price = Decimal(str(t["price"]))
        qty = int(t["qty"])

        if action == "OPEN_LONG":
            if pos_side == "SHORT" and pos_qty > 0:
                close_qty = min(qty, pos_qty)
                pos_qty -= close_qty
                if pos_qty == 0:
                    pos_side, avg_price = None, Decimal("0")
                qty -= close_qty
                if qty <= 0:
                    continue

            if pos_side == "LONG" and pos_qty > 0:
                avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                pos_qty += qty
            else:
                pos_side, pos_qty, avg_price = "LONG", qty, price
            continue

        if action == "OPEN_SHORT":
            if pos_side == "LONG" and pos_qty > 0:
                close_qty = min(qty, pos_qty)
                pos_qty -= close_qty
                if pos_qty == 0:
                    pos_side, avg_price = None, Decimal("0")
                qty -= close_qty
                if qty <= 0:
                    continue

            if pos_side == "SHORT" and pos_qty > 0:
                avg_price = (avg_price * Decimal(pos_qty) + price * Decimal(qty)) / Decimal(pos_qty + qty)
                pos_qty += qty
            else:
                pos_side, pos_qty, avg_price = "SHORT", qty, price
            continue

        if action in ("CLOSE_PART", "CLOSE_ALL", "CLOSE_CARRY_LONG", "CLOSE_CARRY_SHORT"):
            if pos_qty <= 0:
                continue
            close_qty = min(qty, pos_qty)
            pos_qty -= close_qty
            if pos_qty == 0:
                pos_side, avg_price = None, Decimal("0")

    return {"pos_side": pos_side, "pos_qty": pos_qty, "avg_price": avg_price}


def fetch_previous_position(cursor, run_id: int, trade_date: date) -> Dict[str, Any]:
    prev_trade_date, prev_day_trades = fetch_previous_day_trades(cursor, run_id, trade_date)
    if not prev_day_trades:
        return {"pos_side": None, "pos_qty": 0, "avg_price": Decimal("0"), "prev_trade_date": prev_trade_date}

    # 마지막 action명이 아니라, 실제 누적 포지션 재계산 결과로 미청산 여부를 판단한다.
    # CLOSE_CARRY_*로 끝나도 position이 0이면 청산 완료다.
    pos = calc_position_from_trades(fetch_trades_before_date(cursor, run_id, trade_date))
    pos["prev_trade_date"] = prev_trade_date

    if int(pos["pos_qty"]) <= 0:
        return {"pos_side": None, "pos_qty": 0, "avg_price": Decimal("0"), "prev_trade_date": prev_trade_date}

    return pos


def column_exists(cursor, table_name: str, column_name: str) -> bool:
    cursor.execute(
        """
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
        """,
        (table_name, column_name)
    )
    return int(cursor.fetchone()[0]) > 0


def ensure_trade_position_columns(cursor):
    """
    position_qty_after / position_side_after 컬럼이 없으면 추가한다.
    - position_qty_after: 롱은 양수, 숏은 음수, 무포지션은 0
    - position_side_after: LONG / SHORT / NONE
    """
    if not column_exists(cursor, "futures_sim_trade", "position_qty_after"):
        cursor.execute(
            "ALTER TABLE futures_sim_trade "
            "ADD COLUMN position_qty_after INT NOT NULL DEFAULT 0 AFTER qty"
        )

    if not column_exists(cursor, "futures_sim_trade", "position_side_after"):
        cursor.execute(
            "ALTER TABLE futures_sim_trade "
            "ADD COLUMN position_side_after VARCHAR(10) NULL AFTER position_qty_after"
        )


def insert_trades(cursor, day_id: int, trades: List[Dict]):
    ensure_trade_position_columns(cursor)

    sql = """
    INSERT INTO futures_sim_trade
    (day_id, seq, action, bar_dt, price, qty, position_qty_after, position_side_after, fee_amount, note)
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    for t in trades:
        cursor.execute(sql, (
            day_id,
            t["seq"],
            t["action"],
            t["bar_dt"],
            str(t["price"]),
            int(t["qty"]),
            int(t.get("position_qty_after", 0)),
            t.get("position_side_after", "NONE"),
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


def append_trades(cursor, day_id: int, trades: List[Dict]):
    """
    야간장처럼 기존 day_id 뒤에 추가로 붙이는 용도.
    build_trade_records()가 만든 seq(1,2,3...)를 기존 max(seq) 뒤로 재번호 부여한다.
    """
    if not trades:
        return

    ensure_trade_position_columns(cursor)

    cursor.execute("SELECT COALESCE(MAX(seq), 0) FROM futures_sim_trade WHERE day_id=%s", (day_id,))
    max_seq = int(cursor.fetchone()[0] or 0)

    sql = """
    INSERT INTO futures_sim_trade
    (day_id, seq, action, bar_dt, price, qty, position_qty_after, position_side_after, fee_amount, note)
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    for t in trades:
        cursor.execute(sql, (
            day_id,
            max_seq + int(t["seq"]),
            t["action"],
            t["bar_dt"],
            str(t["price"]),
            int(t["qty"]),
            int(t.get("position_qty_after", 0)),
            t.get("position_side_after", "NONE"),
            int(t["fee_amount"]),
            t["note"]
        ))


def add_day_summary_delta(cursor, day_id: int, pnl_points_delta: Decimal, point_value: int, fee_total_delta: int):
    """
    전일에 야간장 trade를 append했을 때 기존 전일 요약값에 야간장 손익/수수료만 더한다.
    """
    cursor.execute(
        "SELECT COALESCE(pnl_points,0), COALESCE(fee_total,0) "
        "FROM futures_sim_run_day WHERE day_id=%s",
        (day_id,)
    )
    row = cursor.fetchone()
    old_pnl_points = Decimal(str(row[0] or "0")) if row else Decimal("0")
    old_fee_total = int(row[1] or 0) if row else 0

    new_pnl_points = old_pnl_points + Decimal(str(pnl_points_delta))
    new_fee_total = old_fee_total + int(fee_total_delta)
    new_pnl_amount = money_round(new_pnl_points * Decimal(point_value))
    new_pnl_amount_net = new_pnl_amount - new_fee_total

    cursor.execute(
        "UPDATE futures_sim_run_day "
        "SET pnl_points=%s, pnl_amount=%s, fee_total=%s, pnl_amount_net=%s "
        "WHERE day_id=%s",
        (str(new_pnl_points.quantize(Decimal("0.0001"))), new_pnl_amount, new_fee_total, new_pnl_amount_net, day_id)
    )


def split_night_and_regular(df_std: pd.DataFrame) -> Tuple[pd.DataFrame, pd.DataFrame]:
    """
    파일명 날짜 기준 18:00 이후 체결만 야간장으로 분리한다.
    나머지는 기존 정규장 로직에 그대로 넘긴다.
    """
    if df_std.empty:
        return df_std.copy(), df_std.copy()

    night_mask = df_std["bar_dt"].dt.time >= time(18, 0)
    night_df = df_std[night_mask].copy()
    regular_df = df_std[~night_mask].copy()
    return night_df, regular_df


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
# 메인: 파일 일자순으로 해당 일자만 삭제 후 재등록
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
            ensure_trade_position_columns(cursor)
            db.commit()

            # 원본 흐름 유지: 날짜별 처리 결과를 다음 날짜 initial_pos로 넘긴다.
            carry_pos = None

            for trade_date, filepath in files_by_date.items():
                base = os.path.basename(filepath)
                print(f"\n===== {trade_date} / {base} =====")

                try:
                    # 1) 파싱/계산 먼저 (여기서 실패하면 DB 건드리지 않음)
                    df_std, point_value = read_fills_excel(filepath, trade_date)

                    if DEBUG:
                        print(f" - parsed rows: {len(df_std)}")

                    # ---------------------------------------------------------
                    # 야간장 예외처리
                    # - build_trade_records()는 건드리지 않는다.
                    # - 야간장만 전일 day_id 뒤에 append한다.
                    # - 이후 정규장은 regular_df 전체를 원본 흐름으로 딱 한 번만 처리한다.
                    # ---------------------------------------------------------
                    night_df, regular_df = split_night_and_regular(df_std)

                    if not night_df.empty:
                        # 야간장 initial_pos는 원본의 날짜 연결 상태를 우선 사용한다.
                        # 전체 배치 중이면 carry_pos가 직전 처리일의 최종 포지션이다.
                        # 단독 실행 등으로 carry_pos가 없으면 DB에서 조회한다.
                        if carry_pos is not None:
                            night_initial_pos = carry_pos
                            night_pos_source = "batch_memory"
                        else:
                            night_initial_pos = fetch_previous_position(cursor, RUN_ID, trade_date)
                            night_pos_source = "db"

                        prev_trade_date = night_initial_pos.get("prev_trade_date")
                        if prev_trade_date is None:
                            raise RuntimeError("야간장 append 대상 전일(prev_trade_date)을 찾지 못했습니다.")

                        if DEBUG:
                            print(
                                f" [NIGHT initial_pos:{night_pos_source}] append_date={prev_trade_date} "
                                f"side={night_initial_pos['pos_side']} qty={night_initial_pos['pos_qty']} "
                                f"avg={night_initial_pos['avg_price']} rows={len(night_df)}"
                            )
                            print(" [NIGHT rows]")
                            print(night_df[["bar_dt", "side_raw", "qty", "price"]].to_string(index=False))

                        prev_day_id = upsert_day_row(cursor, RUN_ID, prev_trade_date)

                        night_trades, night_pnl_points, night_fee_total, night_pos_info = build_trade_records(
                            night_df,
                            point_value,
                            night_initial_pos
                        )

                        # 추적용 note. build_trade_records() 내부 로직은 변경하지 않는다.
                        for t in night_trades:
                            marker = f"night_append:{trade_date}"
                            t["note"] = f"{marker};{t['note']}" if t.get("note") else marker

                        if DEBUG:
                            print(f" [NIGHT built actions] {[t['action'] for t in night_trades]}")

                        append_trades(cursor, prev_day_id, night_trades)
                        add_day_summary_delta(cursor, prev_day_id, night_pnl_points, point_value, night_fee_total)
                        db.commit()

                        # 야간장 처리 결과만 다음 정규장 initial_pos로 반영한다.
                        # 정규장 전체는 아래 원본 흐름에서 한 번만 build_trade_records()를 탄다.
                        night_pos_side, night_pos_qty, night_avg_price = night_pos_info
                        carry_pos = {
                            "pos_side": night_pos_side,
                            "pos_qty": night_pos_qty,
                            "avg_price": night_avg_price,
                            "prev_trade_date": prev_trade_date,
                        }

                        print(f" - 야간장 append 완료: append_date={prev_trade_date}, rows={len(night_df)}, trades={len(night_trades)}")

                    # 여기부터는 원본 로직이 처리할 데이터만 남긴다.
                    # 야간장이 없으면 df_std는 원본과 동일하다.
                    # 야간장이 있으면 regular_df만 남겨 기존 정규장 흐름으로 처리한다.
                    df_std = regular_df

                    # 2) 현재 일자 삭제 전에 직전 등록일 포지션 확보
                    # 원본 흐름 유지: carry_pos가 있으면 batch_memory를 사용한다.
                    if carry_pos is not None:
                        initial_pos = carry_pos
                        pos_source = "batch_memory"
                    else:
                        initial_pos = fetch_previous_position(cursor, RUN_ID, trade_date)
                        pos_source = "db"

                    if DEBUG:
                        print(
                            f" - prev position({pos_source}): date={initial_pos.get('prev_trade_date')} side={initial_pos['pos_side']} "
                            f"qty={initial_pos['pos_qty']} avg={initial_pos['avg_price']}"
                        )

                    # 3) day_id 확보 + 현재 일자의 기존 trade만 삭제
                    day_id = upsert_day_row(cursor, RUN_ID, trade_date)
                    delete_existing_trades(cursor, day_id)

                    if df_std.empty:
                        # 체결 없으면 trade는 비워두고 day는 0으로
                        update_day_summary(cursor, day_id, Decimal("0"), point_value if point_value else 50000, 0)
                        db.commit()
                        carry_pos = {
                            "pos_side": initial_pos["pos_side"],
                            "pos_qty": initial_pos["pos_qty"],
                            "avg_price": initial_pos["avg_price"],
                            "prev_trade_date": trade_date,
                        }
                        print(" - df_std empty: 기존 trade 삭제 + day 0 갱신")
                        continue

                    # 정규장 전체를 여기서 딱 한 번만 처리한다.
                    # 08:49 CLOSE_CARRY 후 08:53 OPEN_LONG, 09:35 SELL 같은 흐름이
                    # 한 번의 build_trade_records() 안에서 순차 처리되어야 한다.
                    trades, pnl_points, fee_total, pos_info = build_trade_records(df_std, point_value, initial_pos)
                    pos_side, pos_qty, avg_price = pos_info
                    carry_pos = {
                        "pos_side": pos_side,
                        "pos_qty": pos_qty,
                        "avg_price": avg_price,
                        "prev_trade_date": trade_date,
                    }

                    if DEBUG:
                        print(f" - trades built: {len(trades)}")
                        carry_count = sum(1 for t in trades if t["action"] in ("CLOSE_CARRY_LONG", "CLOSE_CARRY_SHORT"))
                        print(f" - carry close trades: {carry_count}")
                        print(f" - actions: {[t['action'] for t in trades]}")

                    # 4) trade insert + day summary update
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
