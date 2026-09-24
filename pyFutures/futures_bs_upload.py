import os
import re
import sys
from decimal import Decimal, ROUND_HALF_UP
from datetime import datetime, date, time
from typing import Optional, Tuple, List, Dict, Any, Set

import pandas as pd
import pymysql
import configparser


# ============================================================
# 고정 설정
# ============================================================
RUN_ID = 1
BASE_PATH = r"E:\Project\202410\data\_futures\buysell"
CONFIG_PATH = r"E:\Project\202410\www\boot\common\db\database_config.ini"

FEE_RATE = Decimal("0.00003")  # 0.003%
FILE_GLOB_EXT = (".xlsx", ".xls", ".xlsm")

# 종목명으로 자동 판정. 필요할 때만 50000 / 250000으로 강제.
FORCE_POINT_VALUE = None

# []이면 BASE_PATH의 날짜별 최신 파일을 모두 처리.
# 평소 하루만 처리하고 싶으면 예: ["2026-08-26"]
# 여러 날이면 예: ["2026-08-24", "2026-08-25", "2026-08-26"]
# 명령행 날짜가 있으면 이 값보다 명령행이 우선한다.
TARGET_DATES: List[str] = []

# 과거 날짜를 다시 등록할 때 그 이후 DB 거래일의 원본 파일이 누락되면
# 이월 포지션 연쇄가 깨질 수 있으므로 기본적으로 중단한다.
STRICT_DEPENDENCY_CHECK = True

# 배치 첫 날짜의 DB 이월상태를 과거 action 전체 replay와 대조한다.
# 오래된 DB에 잘못된 position snapshot이 남아 있는지 탐지하기 위한 안전장치.
VERIFY_FIRST_INITIAL_POSITION = True

# 새로 등록한 날짜의 DB trade/summary를 다시 읽어서 반드시 검증한다.
VERIFY_AFTER_WRITE = True

# True면 실제 계산/검증은 모두 수행하지만 마지막에 rollback한다.
# 처음 새 코드 확인할 때 한 번 True로 돌려 로그를 보고, 이상 없으면 False로 사용 가능.
DRY_RUN = False

DEBUG = True


# ============================================================
# 공통 유틸
# ============================================================
def parse_trade_date_from_filename(filename: str) -> Optional[date]:
    base = os.path.basename(filename)
    m = re.search(
        r"(20\d{2})[-_\.]?(0[1-9]|1[0-2])[-_\.]?([0-2]\d|3[01])",
        base,
    )
    if not m:
        return None
    return date(int(m.group(1)), int(m.group(2)), int(m.group(3)))


def parse_date_text(s: str) -> date:
    return datetime.strptime(s.strip(), "%Y-%m-%d").date()


def pick_engine(path: str) -> Optional[str]:
    ext = os.path.splitext(path)[1].lower()
    if ext in (".xlsx", ".xlsm"):
        return "openpyxl"
    if ext == ".xls":
        return "xlrd"
    return None


def find_col(df: pd.DataFrame, candidates: List[str]) -> Optional[str]:
    for c in candidates:
        if c in df.columns:
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
    if not s:
        return None

    for fmt in ("%H:%M:%S", "%H:%M"):
        try:
            return datetime.strptime(s, fmt).time()
        except Exception:
            pass

    try:
        parsed = pd.to_datetime(s, errors="coerce")
        if pd.isna(parsed):
            return None
        return parsed.time()
    except Exception:
        return None


def infer_point_value_from_name(name: str) -> int:
    if FORCE_POINT_VALUE in (50000, 250000):
        return int(FORCE_POINT_VALUE)

    s = str(name or "")
    if "미니" in s or "mini" in s.lower():
        return 50000
    return 250000


def money_round(x: Decimal) -> int:
    return int(x.quantize(Decimal("1"), rounding=ROUND_HALF_UP))


def d(v) -> Decimal:
    return Decimal(str(v))


def q4(v: Decimal) -> Decimal:
    return d(v).quantize(Decimal("0.0001"), rounding=ROUND_HALF_UP)


def normalize_db_text(value) -> str:
    """MySQL/MariaDB 문자열 컬럼이 bytes로 반환되는 환경까지 공통 처리."""
    if isinstance(value, (bytes, bytearray)):
        value = value.decode("utf-8", errors="ignore")
    return str(value or "").strip()


def normalize_db_action(action) -> str:
    return normalize_db_text(action).upper()


def flat_position(prev_trade_date: Optional[date] = None) -> Dict[str, Any]:
    return {
        "pos_side": None,
        "pos_qty": 0,
        "avg_price": Decimal("0"),
        "prev_trade_date": prev_trade_date,
    }


def normalize_position(pos: Optional[Dict[str, Any]], prev_trade_date=None) -> Dict[str, Any]:
    pos = pos or {}
    side = normalize_db_text(pos.get("pos_side")).upper()
    qty = int(pos.get("pos_qty") or 0)
    avg = d(pos.get("avg_price") or "0")
    prev = pos.get("prev_trade_date", prev_trade_date)

    if side not in ("LONG", "SHORT") or qty <= 0:
        return flat_position(prev)

    return {
        "pos_side": side,
        "pos_qty": qty,
        "avg_price": avg,
        "prev_trade_date": prev,
    }


def position_equal(a: Dict[str, Any], b: Dict[str, Any], avg_tol=Decimal("0.00000001")) -> bool:
    aa = normalize_position(a)
    bb = normalize_position(b)
    if aa["pos_side"] != bb["pos_side"]:
        return False
    if int(aa["pos_qty"]) != int(bb["pos_qty"]):
        return False
    if aa["pos_qty"] == 0:
        return True
    return abs(d(aa["avg_price"]) - d(bb["avg_price"])) <= avg_tol


def fmt_position(pos: Dict[str, Any]) -> str:
    p = normalize_position(pos)
    if p["pos_qty"] <= 0:
        return "FLAT"
    return f"{p['pos_side']} {p['pos_qty']} @ {p['avg_price']}"


# ============================================================
# 엑셀 읽기
# - 실제 체결시간이 있는 행만 사용
# - 체결량>0 / 체결가격 존재
# - 동일 초 체결은 엑셀 원래 행 순서 유지
# ============================================================
def read_fills_excel(filepath: str, trade_date: date) -> Tuple[pd.DataFrame, int]:
    engine = pick_engine(filepath)
    if engine is None:
        raise ValueError(f"지원하지 않는 엑셀 확장자: {filepath}")

    df = pd.read_excel(filepath, engine=engine).copy()
    df["_source_order"] = range(len(df))

    col_order_no = find_col(df, ["주문No.", "주문번호", "주문No", "order_no"])
    col_name = find_col(df, ["종목명", "종목", "name", "symbol"])
    col_side = find_col(df, ["구분", "매매구분", "매수/매도", "side", "action", "type"])
    col_qty = find_col(df, ["체결량", "체결수량", "수량", "qty", "quantity"])
    col_price = find_col(df, ["체결가격", "체결가", "가격", "price"])
    col_time = find_col(df, ["체결시간", "체결시각"])

    required = {
        "side": col_side,
        "qty": col_qty,
        "price": col_price,
        "체결시간": col_time,
    }
    missing = [k for k, v in required.items() if v is None]
    if missing:
        raise ValueError(
            f"[{os.path.basename(filepath)}] 필수 컬럼 누락: {missing} / 현재 컬럼={list(df.columns)}"
        )

    df[col_qty] = pd.to_numeric(df[col_qty], errors="coerce")
    df[col_price] = pd.to_numeric(df[col_price], errors="coerce")
    time_text = df[col_time].fillna("").astype(str).str.strip()

    mask = (
        (df[col_qty].fillna(0) > 0)
        & df[col_price].notna()
        & df[col_side].notna()
        & (time_text != "")
    )
    df = df[mask].copy()

    if df.empty:
        return pd.DataFrame(
            columns=["order_no", "name", "side_raw", "qty", "price", "bar_dt", "note_type"]
        ), int(FORCE_POINT_VALUE or 50000)

    parsed_times = df[col_time].apply(to_time)
    df["bar_dt"] = [
        datetime.combine(trade_date, t) if t is not None else pd.NaT
        for t in parsed_times
    ]
    df = df[df["bar_dt"].notna()].copy()

    df.sort_values(["bar_dt", "_source_order"], inplace=True, kind="stable")

    first_name = str(df[col_name].iloc[0]) if col_name and len(df) else ""
    point_value = infer_point_value_from_name(first_name)
    note_col = find_col(df, ["주문구분", "note", "memo"])

    df_std = pd.DataFrame({
        "order_no": df[col_order_no].fillna("").astype(str) if col_order_no else "",
        "name": df[col_name].fillna("").astype(str) if col_name else "",
        "side_raw": df[col_side].astype(str),
        "qty": df[col_qty].astype(int),
        "price": df[col_price].astype(float),
        "bar_dt": pd.to_datetime(df["bar_dt"]),
        "note_type": df[note_col].fillna("").astype(str) if note_col else "",
    })

    if DEBUG:
        uniq = sorted(set(df_std["side_raw"].astype(str).tolist()))
        print(f" - 실제 체결 rows={len(df_std)} / side={uniq[:30]}")

    return df_std, point_value


# ============================================================
# 체결 신호 / action
# - 새 DB row에는 4개 action만 기록
# ============================================================
WRITE_ACTIONS = ("OPEN_LONG", "OPEN_SHORT", "CLOSE_PART", "CLOSE_ALL")
LEGACY_CLOSE_ACTIONS = ("CLOSE_CARRY_LONG", "CLOSE_CARRY_SHORT")


def normalize_signal(side_raw: str) -> Optional[str]:
    s0 = str(side_raw or "").strip()
    s = s0.upper().replace(" ", "")

    if "OPEN_LONG" in s:
        return "OPEN_LONG"
    if "OPEN_SHORT" in s:
        return "OPEN_SHORT"
    if "CLOSE_ALL" in s:
        return "CLOSE_ALL"
    if "CLOSE_PART" in s:
        return "CLOSE_PART"

    # 과거 형식은 새로 저장할 때 일반 청산으로 통합
    if "CLOSE_CARRY_LONG" in s or "CLOSE_CARRY_SHORT" in s:
        return "CLOSE_PART"

    if "매수" in s0 and "매도" not in s0:
        return "BUY"
    if "매도" in s0:
        return "SELL"

    if s in ("BUY", "B", "LONG", "L"):
        return "BUY"
    if s in ("SELL", "S", "SHORT", "SH", "SS"):
        return "SELL"

    if s.startswith("BUY"):
        return "BUY"
    if s.startswith("SELL"):
        return "SELL"

    return None


# ============================================================
# 원본 체결 -> normalized DB trade
# 핵심 규칙
# 1) initial_pos를 그대로 이어받는다.
# 2) 같은 방향 추가진입은 전체 수량 가중평단 갱신.
# 3) 부분청산은 평단 유지.
# 4) 전량청산일 때만 평단 0.
# 5) 반대방향 체결이 기존수량보다 크면 청산 후 남는 수량으로 flip.
# 6) 이월 전용 action은 새로 만들지 않는다.
# ============================================================
def build_trade_records(
    df_std: pd.DataFrame,
    point_value: int,
    initial_pos: Optional[Dict[str, Any]] = None,
):
    p0 = normalize_position(initial_pos)
    pos_side = p0["pos_side"]
    pos_qty = int(p0["pos_qty"])
    avg_price = d(p0["avg_price"])

    realized_points = Decimal("0")
    trades: List[Dict[str, Any]] = []
    seq = 1

    def fee_of(price: Decimal, qty: int) -> int:
        return money_round(price * Decimal(point_value) * Decimal(qty) * FEE_RATE)

    def signed_qty() -> int:
        if pos_side == "LONG":
            return pos_qty
        if pos_side == "SHORT":
            return -pos_qty
        return 0

    def side_after() -> str:
        return pos_side if pos_side in ("LONG", "SHORT") and pos_qty > 0 else "NONE"

    def avg_after() -> Decimal:
        if pos_side in ("LONG", "SHORT") and pos_qty > 0:
            return avg_price
        return Decimal("0")

    def add_trade(action: str, bar_dt: datetime, price: Decimal, qty: int, note: Optional[str]):
        nonlocal seq
        if action not in WRITE_ACTIONS:
            raise RuntimeError(f"저장 불가 action: {action}")
        if qty <= 0:
            return
        if note and len(note) > 120:
            note = note[:120]

        trades.append({
            "seq": seq,
            "action": action,
            "bar_dt": bar_dt,
            "price": price,
            "qty": int(qty),
            "position_qty_after": signed_qty(),
            "position_side_after": side_after(),
            "avg_price_after": avg_after(),
            "fee_amount": fee_of(price, qty),
            "note": note,
        })
        seq += 1

    def open_same_side(open_side: str, bar_dt: datetime, price: Decimal, qty: int, note: Optional[str]):
        nonlocal pos_side, pos_qty, avg_price
        qty = int(qty)
        if qty <= 0:
            return

        if pos_side is None or pos_qty == 0:
            pos_side = open_side
            pos_qty = qty
            avg_price = price
        elif pos_side == open_side:
            new_qty = pos_qty + qty
            avg_price = (
                avg_price * Decimal(pos_qty)
                + price * Decimal(qty)
            ) / Decimal(new_qty)
            pos_qty = new_qty
        else:
            raise RuntimeError("반대 포지션이 남은 상태에서 신규진입 호출")

        add_trade(
            "OPEN_LONG" if open_side == "LONG" else "OPEN_SHORT",
            bar_dt,
            price,
            qty,
            note,
        )

    def close_current(
        bar_dt: datetime,
        price: Decimal,
        requested_qty: int,
        note: Optional[str],
        force_all: bool = False,
    ) -> int:
        nonlocal pos_side, pos_qty, avg_price, realized_points

        if pos_side not in ("LONG", "SHORT") or pos_qty <= 0:
            return 0

        before_qty = pos_qty
        close_qty = before_qty if force_all else min(int(requested_qty), before_qty)
        if close_qty <= 0:
            return 0

        if pos_side == "LONG":
            realized_points += (price - avg_price) * Decimal(close_qty)
        else:
            realized_points += (avg_price - price) * Decimal(close_qty)

        pos_qty -= close_qty
        if pos_qty == 0:
            pos_side = None
            avg_price = Decimal("0")

        action = "CLOSE_ALL" if close_qty == before_qty else "CLOSE_PART"
        add_trade(action, bar_dt, price, close_qty, note)
        return close_qty

    for r in df_std.itertuples(index=False):
        sig = normalize_signal(r.side_raw)
        if sig is None:
            raise RuntimeError(f"판정 불가 체결: {r.bar_dt} / {r.side_raw}")

        price = d(r.price)
        qty = int(r.qty)

        base_note = []
        if str(r.order_no).strip():
            base_note.append(f"order:{str(r.order_no).strip()}")
        if str(r.note_type).strip():
            base_note.append(f"type:{str(r.note_type).strip()}")
        note = ";".join(base_note) if base_note else None

        if sig in ("BUY", "SELL"):
            incoming_side = "LONG" if sig == "BUY" else "SHORT"

            if pos_side is None or pos_qty == 0:
                open_same_side(incoming_side, r.bar_dt, price, qty, note)
                continue

            if pos_side == incoming_side:
                open_same_side(incoming_side, r.bar_dt, price, qty, note)
                continue

            closed_qty = close_current(r.bar_dt, price, qty, note)
            remain_qty = qty - closed_qty
            if remain_qty > 0:
                flip_note = f"flip-open;{note}" if note else "flip-open"
                open_same_side(incoming_side, r.bar_dt, price, remain_qty, flip_note)
            continue

        if sig == "OPEN_LONG":
            incoming_side = "LONG"
        elif sig == "OPEN_SHORT":
            incoming_side = "SHORT"
        else:
            incoming_side = None

        if incoming_side is not None:
            if pos_side is not None and pos_side != incoming_side and pos_qty > 0:
                closed_qty = close_current(r.bar_dt, price, qty, note)
                remain_qty = qty - closed_qty
                if remain_qty > 0:
                    open_same_side(incoming_side, r.bar_dt, price, remain_qty, note)
            else:
                open_same_side(incoming_side, r.bar_dt, price, qty, note)
            continue

        if sig == "CLOSE_ALL":
            # 직접 action 입력에서 CLOSE_ALL은 실제 보유 전량을 청산.
            closed = close_current(r.bar_dt, price, qty, note, force_all=True)
            if closed <= 0:
                raise RuntimeError(f"무포지션 CLOSE_ALL: {r.bar_dt}")
            continue

        if sig == "CLOSE_PART":
            closed = close_current(r.bar_dt, price, qty, note, force_all=False)
            if closed <= 0:
                raise RuntimeError(f"무포지션 CLOSE_PART: {r.bar_dt}")
            continue

    fee_sum = sum(int(t["fee_amount"]) for t in trades)
    end_pos = normalize_position({
        "pos_side": pos_side,
        "pos_qty": pos_qty,
        "avg_price": avg_price,
    })
    return trades, realized_points, fee_sum, end_pos


# ============================================================
# DB 연결 / 스키마
# ============================================================
def connect_db():
    config = configparser.ConfigParser()
    config.read(CONFIG_PATH)

    return pymysql.connect(
        host=config.get("database", "host"),
        user=config.get("database", "user"),
        password=config.get("database", "password"),
        db=config.get("database", "db"),
        charset=config.get("database", "charset"),
        autocommit=False,
    )


def column_exists(cursor, table_name: str, column_name: str) -> bool:
    cursor.execute(
        """
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
        """,
        (table_name, column_name),
    )
    return int(cursor.fetchone()[0]) > 0


def ensure_trade_position_columns(cursor):
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

    if not column_exists(cursor, "futures_sim_trade", "avg_price_after"):
        cursor.execute(
            "ALTER TABLE futures_sim_trade "
            "ADD COLUMN avg_price_after DECIMAL(18,8) NULL AFTER position_side_after"
        )


def get_day_id(cursor, run_id: int, trade_date: date) -> Optional[int]:
    cursor.execute(
        "SELECT day_id FROM futures_sim_run_day WHERE run_id=%s AND trade_date=%s",
        (run_id, trade_date),
    )
    row = cursor.fetchone()
    return int(row[0]) if row else None


def upsert_day_row(cursor, run_id: int, trade_date: date) -> int:
    # day_comment는 절대 INSERT/UPDATE하지 않는다.
    cursor.execute(
        "INSERT IGNORE INTO futures_sim_run_day (run_id, trade_date) VALUES (%s,%s)",
        (run_id, trade_date),
    )
    day_id = get_day_id(cursor, run_id, trade_date)
    if day_id is None:
        raise RuntimeError(f"day_id 조회 실패: {trade_date}")
    return day_id


def get_prev_trading_date(cursor, trade_date: date) -> Optional[date]:
    cursor.execute(
        "SELECT MAX(date) FROM calendar WHERE date < %s",
        (trade_date,),
    )
    row = cursor.fetchone()
    return row[0] if row and row[0] else None


# ============================================================
# DB trade 읽기 / replay
# ============================================================
def fetch_db_trades_for_day(cursor, run_id: int, trade_date: date) -> List[Dict[str, Any]]:
    cursor.execute(
        """
        SELECT
            t.action,
            t.price,
            t.qty,
            t.fee_amount,
            t.seq,
            t.trade_id,
            t.position_qty_after,
            t.position_side_after,
            t.avg_price_after,
            t.note
        FROM futures_sim_run_day d
        JOIN futures_sim_trade t ON t.day_id=d.day_id
        WHERE d.run_id=%s
          AND d.trade_date=%s
        ORDER BY t.seq ASC, t.trade_id ASC
        """,
        (run_id, trade_date),
    )

    rows = []
    for row in cursor.fetchall():
        (
            action, price, qty, fee_amount, seq, trade_id,
            position_qty_after, position_side_after, avg_price_after, note,
        ) = row
        rows.append({
            "action": normalize_db_action(action),
            "price": d(price),
            "qty": int(qty),
            "fee_amount": int(fee_amount or 0),
            "seq": int(seq),
            "trade_id": int(trade_id),
            "position_qty_after": int(position_qty_after or 0),
            "position_side_after": normalize_db_text(position_side_after),
            "avg_price_after": None if avg_price_after is None else d(avg_price_after),
            "note": note,
        })
    return rows


def fetch_db_trades_before_date(cursor, run_id: int, trade_date: date) -> List[Dict[str, Any]]:
    cursor.execute(
        """
        SELECT t.action, t.price, t.qty, t.fee_amount
        FROM futures_sim_run_day d
        JOIN futures_sim_trade t ON t.day_id=d.day_id
        WHERE d.run_id=%s
          AND d.trade_date < %s
        ORDER BY d.trade_date ASC, t.seq ASC, t.trade_id ASC
        """,
        (run_id, trade_date),
    )

    return [
        {
            "action": normalize_db_action(action),
            "price": d(price),
            "qty": int(qty),
            "fee_amount": int(fee_amount or 0),
        }
        for action, price, qty, fee_amount in cursor.fetchall()
    ]


def replay_trade_actions(
    trades: List[Dict[str, Any]],
    initial_pos: Optional[Dict[str, Any]] = None,
    strict: bool = True,
) -> Tuple[Dict[str, Any], Decimal, int]:
    """DB normalized action 재생. 구버전 CLOSE_CARRY_*는 일반 청산으로 읽기 호환."""
    p0 = normalize_position(initial_pos)
    pos_side = p0["pos_side"]
    pos_qty = int(p0["pos_qty"])
    avg_price = d(p0["avg_price"])

    realized = Decimal("0")
    fee_total = 0

    def close_qty_at(price: Decimal, qty: int, force_all: bool = False) -> int:
        nonlocal pos_side, pos_qty, avg_price, realized
        if pos_side not in ("LONG", "SHORT") or pos_qty <= 0:
            if strict:
                raise RuntimeError("DB replay 중 무포지션 청산 발생")
            return 0

        q = pos_qty if force_all else min(int(qty), pos_qty)
        if q <= 0:
            return 0

        if pos_side == "LONG":
            realized += (price - avg_price) * Decimal(q)
        else:
            realized += (avg_price - price) * Decimal(q)

        pos_qty -= q
        if pos_qty == 0:
            pos_side = None
            avg_price = Decimal("0")
        return q

    def incoming(open_side: str, price: Decimal, qty: int):
        nonlocal pos_side, pos_qty, avg_price
        q = int(qty)
        if q <= 0:
            return

        if pos_side is not None and pos_side != open_side and pos_qty > 0:
            closed = close_qty_at(price, q, force_all=False)
            q -= closed
            if q <= 0:
                return

        if pos_side is None or pos_qty == 0:
            pos_side = open_side
            pos_qty = q
            avg_price = price
            return

        if pos_side == open_side:
            new_qty = pos_qty + q
            avg_price = (
                avg_price * Decimal(pos_qty)
                + price * Decimal(q)
            ) / Decimal(new_qty)
            pos_qty = new_qty
            return

        raise RuntimeError("DB replay 중 포지션 상태 오류")

    for t in trades:
        action = normalize_db_action(t.get("action"))
        price = d(t.get("price"))
        qty = int(t.get("qty") or 0)
        fee_total += int(t.get("fee_amount") or 0)

        if action == "OPEN_LONG":
            incoming("LONG", price, qty)
        elif action == "OPEN_SHORT":
            incoming("SHORT", price, qty)
        elif action == "CLOSE_ALL":
            close_qty_at(price, qty, force_all=True)
        elif action in ("CLOSE_PART", "CLOSE_CARRY_LONG", "CLOSE_CARRY_SHORT"):
            close_qty_at(price, qty, force_all=False)
        else:
            raise RuntimeError(f"지원하지 않는 기존 DB action: {action}")

    state = normalize_position({
        "pos_side": pos_side,
        "pos_qty": pos_qty,
        "avg_price": avg_price,
    })
    return state, realized, fee_total


# ============================================================
# 이전 포지션 복원
# - 평소에는 직전 trade snapshot 1건을 사용
# - snapshot이 없는 구버전은 최근 신뢰 anchor(snapshot 또는 CLOSE_ALL) 이후만 replay
# - 오래된 이월 데이터 때문에 전체 history를 FLAT부터 재생하지 않는다
# - 배치 첫 날짜는 optional 검증으로 snapshot과 anchor replay를 대조
# ============================================================
def fetch_latest_snapshot_before_date(cursor, run_id: int, trade_date: date):
    cursor.execute(
        """
        SELECT
            d.trade_date,
            t.position_qty_after,
            t.position_side_after,
            t.avg_price_after,
            t.trade_id
        FROM futures_sim_run_day d
        JOIN futures_sim_trade t ON t.day_id=d.day_id
        WHERE d.run_id=%s
          AND d.trade_date < %s
        ORDER BY d.trade_date DESC, t.seq DESC, t.trade_id DESC
        LIMIT 1
        """,
        (run_id, trade_date),
    )
    return cursor.fetchone()


def fetch_replay_anchor_before_date(cursor, run_id: int, trade_date: date):
    """
    trade_date 이전에서 "신뢰 가능한 시작점(anchor)"을 찾는다.

    우선순위/조건:
      1) avg_price_after IS NOT NULL인 신규 snapshot row
      2) 구버전 row라도 action=CLOSE_ALL이면 그 직후 상태는 FLAT으로 확정 가능

    오래된 DB 전체를 무조건 FLAT부터 replay하면, 이월 포지션으로 시작한 과거 날짜의
    첫 청산 때문에 '무포지션 청산' 오류가 날 수 있다. 따라서 가장 최근의 확실한
    anchor 이후만 replay한다.
    """
    cursor.execute(
        """
        SELECT
            d.trade_date,
            t.seq,
            t.trade_id,
            t.action,
            t.position_qty_after,
            t.position_side_after,
            t.avg_price_after
        FROM futures_sim_run_day d
        JOIN futures_sim_trade t ON t.day_id=d.day_id
        WHERE d.run_id=%s
          AND d.trade_date < %s
          AND (
                t.avg_price_after IS NOT NULL
                OR t.action='CLOSE_ALL'
              )
        ORDER BY d.trade_date DESC, t.seq DESC, t.trade_id DESC
        LIMIT 1
        """,
        (run_id, trade_date),
    )
    return cursor.fetchone()


def fetch_db_trades_after_anchor_before_date(
    cursor,
    run_id: int,
    anchor_date: date,
    anchor_seq: int,
    anchor_trade_id: int,
    trade_date: date,
) -> List[Dict[str, Any]]:
    """anchor row '다음'부터 trade_date 직전까지 action을 시간순으로 읽는다."""
    cursor.execute(
        """
        SELECT t.action, t.price, t.qty, t.fee_amount
        FROM futures_sim_run_day d
        JOIN futures_sim_trade t ON t.day_id=d.day_id
        WHERE d.run_id=%s
          AND d.trade_date < %s
          AND (
                d.trade_date > %s
                OR (
                    d.trade_date = %s
                    AND (
                        t.seq > %s
                        OR (t.seq = %s AND t.trade_id > %s)
                    )
                )
              )
        ORDER BY d.trade_date ASC, t.seq ASC, t.trade_id ASC
        """,
        (
            run_id,
            trade_date,
            anchor_date,
            anchor_date,
            anchor_seq,
            anchor_seq,
            anchor_trade_id,
        ),
    )
    return [
        {
            "action": normalize_db_action(action),
            "price": d(price),
            "qty": int(qty),
            "fee_amount": int(fee_amount or 0),
        }
        for action, price, qty, fee_amount in cursor.fetchall()
    ]


def replay_position_before_date(cursor, run_id: int, trade_date: date) -> Dict[str, Any]:
    """
    과거 포지션 복원용 fallback.

    핵심은 DB 전체를 FLAT부터 replay하지 않는 것이다.
    가장 최근의 신뢰 가능한 snapshot/CLOSE_ALL을 anchor로 잡고 그 이후만 재생한다.
    """
    anchor = fetch_replay_anchor_before_date(cursor, run_id, trade_date)

    if anchor:
        (
            anchor_date,
            anchor_seq,
            anchor_trade_id,
            anchor_action,
            signed_qty,
            side_raw,
            avg_after,
        ) = anchor

        action = normalize_db_action(anchor_action)

        if avg_after is not None:
            signed_qty = int(signed_qty or 0)
            side = normalize_db_text(side_raw).upper()

            if signed_qty == 0 or side == "NONE":
                initial = flat_position(anchor_date)
            else:
                qty = abs(signed_qty)
                if side not in ("LONG", "SHORT"):
                    side = "LONG" if signed_qty > 0 else "SHORT"
                initial = normalize_position({
                    "pos_side": side,
                    "pos_qty": qty,
                    "avg_price": d(avg_after),
                    "prev_trade_date": anchor_date,
                })
        elif action == "CLOSE_ALL":
            # CLOSE_ALL 직후는 과거 시작 포지션을 몰라도 FLAT이 확정된다.
            initial = flat_position(anchor_date)
        else:
            raise RuntimeError("DB replay anchor 판정 오류")

        rows = fetch_db_trades_after_anchor_before_date(
            cursor,
            run_id,
            anchor_date,
            int(anchor_seq),
            int(anchor_trade_id),
            trade_date,
        )

        state, _, _ = replay_trade_actions(rows, initial, strict=True)
        state["prev_trade_date"] = anchor_date

        # 실제 직전 거래일을 prev_trade_date로 표시한다.
        cursor.execute(
            """
            SELECT MAX(d.trade_date)
            FROM futures_sim_run_day d
            WHERE d.run_id=%s
              AND d.trade_date < %s
              AND EXISTS (
                  SELECT 1 FROM futures_sim_trade t WHERE t.day_id=d.day_id
              )
            """,
            (run_id, trade_date),
        )
        last_row = cursor.fetchone()
        if last_row and last_row[0]:
            state["prev_trade_date"] = last_row[0]

        if DEBUG:
            print(
                f" - history replay anchor: date={anchor_date} seq={anchor_seq} "
                f"trade_id={anchor_trade_id} action={action} / "
                f"rows_after={len(rows)} / end={fmt_position(state)}"
            )
        return state

    # anchor가 전혀 없으면 정말 오래된 데이터다.
    # 이 경우 첫 기록이 OPEN이면 FLAT 시작 replay가 가능하지만,
    # 첫 기록부터 청산이면 시작 평단을 알 방법이 없으므로 조용히 추정하지 않고 중단한다.
    rows = fetch_db_trades_before_date(cursor, run_id, trade_date)
    if not rows:
        return flat_position(None)

    first_action = normalize_db_action(rows[0].get("action"))
    if first_action not in ("OPEN_LONG", "OPEN_SHORT"):
        raise RuntimeError(
            "DB 이월 포지션 복원 기준점을 찾지 못했습니다. "
            f"{trade_date} 이전 과거 데이터가 이월 청산부터 시작하거나 snapshot이 없습니다. "
            "직전 구간을 새 업로더로 한 번 재등록해 avg_price_after snapshot을 만든 뒤 다시 실행해주세요."
        )

    state, _, _ = replay_trade_actions(rows, flat_position(), strict=True)

    cursor.execute(
        """
        SELECT MAX(d.trade_date)
        FROM futures_sim_run_day d
        WHERE d.run_id=%s
          AND d.trade_date < %s
          AND EXISTS (
              SELECT 1 FROM futures_sim_trade t WHERE t.day_id=d.day_id
          )
        """,
        (run_id, trade_date),
    )
    last_row = cursor.fetchone()
    state["prev_trade_date"] = last_row[0] if last_row and last_row[0] else None
    return state


def fetch_previous_position(
    cursor,
    run_id: int,
    trade_date: date,
    verify_snapshot: bool = False,
) -> Dict[str, Any]:
    row = fetch_latest_snapshot_before_date(cursor, run_id, trade_date)
    if not row:
        return flat_position(None)

    prev_trade_date, signed_qty, side_raw, avg_after, trade_id = row

    if avg_after is None:
        # 구버전 / PHP 수동등록 row 등 snapshot 없는 데이터
        replayed = replay_position_before_date(cursor, run_id, trade_date)
        if DEBUG:
            print(
                f" - 이전 포지션: snapshot 없음(trade_id={trade_id}) -> history replay / "
                f"{fmt_position(replayed)}"
            )
        return replayed

    signed_qty = int(signed_qty or 0)
    side = normalize_db_text(side_raw).upper()

    if signed_qty == 0 or side == "NONE":
        snap = flat_position(prev_trade_date)
    else:
        qty = abs(signed_qty)
        if side not in ("LONG", "SHORT"):
            side = "LONG" if signed_qty > 0 else "SHORT"
        snap = normalize_position({
            "pos_side": side,
            "pos_qty": qty,
            "avg_price": d(avg_after),
            "prev_trade_date": prev_trade_date,
        })

    if verify_snapshot:
        replayed = replay_position_before_date(cursor, run_id, trade_date)
        if not position_equal(snap, replayed):
            raise RuntimeError(
                "배치 첫 날짜의 DB 이월 포지션 검증 실패.\n"
                f"snapshot={fmt_position(snap)} / prev={snap.get('prev_trade_date')}\n"
                f"history ={fmt_position(replayed)} / prev={replayed.get('prev_trade_date')}\n"
                "기존 DB의 position snapshot 또는 과거 action 이력이 서로 일치하지 않습니다."
            )
        if DEBUG:
            print(f" - DB 이월상태 검증 OK: {fmt_position(snap)}")

    return snap


def get_day_end_position(cursor, run_id: int, trade_date: date) -> Dict[str, Any]:
    initial = fetch_previous_position(cursor, run_id, trade_date, verify_snapshot=False)
    rows = fetch_db_trades_for_day(cursor, run_id, trade_date)
    if not rows:
        end_pos = normalize_position(initial)
        end_pos["prev_trade_date"] = trade_date
        return end_pos

    state, _, _ = replay_trade_actions(rows, initial, strict=True)
    state["prev_trade_date"] = trade_date
    return state


# ============================================================
# INSERT / UPDATE
# ============================================================
def delete_existing_trades(cursor, day_id: int):
    cursor.execute("DELETE FROM futures_sim_trade WHERE day_id=%s", (day_id,))


def insert_trades(cursor, day_id: int, trades: List[Dict[str, Any]]):
    sql = """
    INSERT INTO futures_sim_trade
    (
        day_id, seq, action, bar_dt, price, qty,
        position_qty_after, position_side_after, avg_price_after,
        fee_amount, note
    )
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """

    for t in trades:
        avg_after = t.get("avg_price_after")
        cursor.execute(
            sql,
            (
                day_id,
                int(t["seq"]),
                t["action"],
                t["bar_dt"],
                str(t["price"]),
                int(t["qty"]),
                int(t.get("position_qty_after", 0)),
                t.get("position_side_after", "NONE"),
                str(avg_after) if avg_after is not None else None,
                int(t["fee_amount"]),
                t.get("note"),
            ),
        )


def append_trades(cursor, day_id: int, trades: List[Dict[str, Any]]):
    if not trades:
        return

    cursor.execute(
        "SELECT COALESCE(MAX(seq),0) FROM futures_sim_trade WHERE day_id=%s",
        (day_id,),
    )
    max_seq = int(cursor.fetchone()[0] or 0)

    sql = """
    INSERT INTO futures_sim_trade
    (
        day_id, seq, action, bar_dt, price, qty,
        position_qty_after, position_side_after, avg_price_after,
        fee_amount, note
    )
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """

    for t in trades:
        avg_after = t.get("avg_price_after")
        cursor.execute(
            sql,
            (
                day_id,
                max_seq + int(t["seq"]),
                t["action"],
                t["bar_dt"],
                str(t["price"]),
                int(t["qty"]),
                int(t.get("position_qty_after", 0)),
                t.get("position_side_after", "NONE"),
                str(avg_after) if avg_after is not None else None,
                int(t["fee_amount"]),
                t.get("note"),
            ),
        )


def update_day_summary(
    cursor,
    day_id: int,
    pnl_points: Decimal,
    point_value: int,
    fee_total: int,
):
    # day_comment는 절대 수정하지 않는다.
    pnl_points = q4(pnl_points)
    pnl_amount = money_round(pnl_points * Decimal(point_value))
    pnl_amount_net = pnl_amount - int(fee_total)

    cursor.execute(
        """
        UPDATE futures_sim_run_day
        SET pnl_points=%s,
            pnl_amount=%s,
            fee_total=%s,
            pnl_amount_net=%s
        WHERE day_id=%s
        """,
        (str(pnl_points), pnl_amount, int(fee_total), pnl_amount_net, day_id),
    )
    return pnl_amount, pnl_amount_net


def recalculate_day_summary_from_db(
    cursor,
    run_id: int,
    trade_date: date,
    point_value: int,
) -> Tuple[Dict[str, Any], Decimal, int, int, int]:
    """
    해당 날짜 summary를 반드시 '전일 종료포지션 + 당일 DB trade'로 다시 계산한다.
    당일만 FLAT에서 재계산하지 않는다.
    """
    day_id = get_day_id(cursor, run_id, trade_date)
    if day_id is None:
        return flat_position(), Decimal("0"), 0, 0, 0

    initial = fetch_previous_position(cursor, run_id, trade_date, verify_snapshot=False)
    rows = fetch_db_trades_for_day(cursor, run_id, trade_date)

    if rows:
        end_pos, pnl_points, fee_total = replay_trade_actions(rows, initial, strict=True)
    else:
        end_pos, pnl_points, fee_total = normalize_position(initial), Decimal("0"), 0

    pnl_amount, pnl_net = update_day_summary(
        cursor, day_id, pnl_points, point_value, fee_total
    )
    return end_pos, q4(pnl_points), fee_total, pnl_amount, pnl_net


def count_trades(cursor, day_id: int) -> int:
    cursor.execute("SELECT COUNT(*) FROM futures_sim_trade WHERE day_id=%s", (day_id,))
    return int(cursor.fetchone()[0])


# ============================================================
# 새로 쓴 trade snapshot 검증
# ============================================================
def verify_day_snapshots(
    cursor,
    run_id: int,
    trade_date: date,
    initial_pos: Dict[str, Any],
):
    rows = fetch_db_trades_for_day(cursor, run_id, trade_date)
    if not rows:
        return

    p = normalize_position(initial_pos)
    pos_side = p["pos_side"]
    pos_qty = int(p["pos_qty"])
    avg_price = d(p["avg_price"])

    def close(qty: int, force_all: bool):
        nonlocal pos_side, pos_qty, avg_price
        if pos_side not in ("LONG", "SHORT") or pos_qty <= 0:
            raise RuntimeError(f"{trade_date} snapshot 검증 중 무포지션 청산")
        q = pos_qty if force_all else min(int(qty), pos_qty)
        pos_qty -= q
        if pos_qty == 0:
            pos_side = None
            avg_price = Decimal("0")

    def incoming(side: str, price: Decimal, qty: int):
        nonlocal pos_side, pos_qty, avg_price
        q = int(qty)
        if pos_side is not None and pos_side != side and pos_qty > 0:
            c = min(q, pos_qty)
            close(c, False)
            q -= c
            if q <= 0:
                return
        if pos_side is None or pos_qty == 0:
            pos_side, pos_qty, avg_price = side, q, price
        else:
            new_qty = pos_qty + q
            avg_price = (
                avg_price * Decimal(pos_qty) + price * Decimal(q)
            ) / Decimal(new_qty)
            pos_qty = new_qty

    for r in rows:
        action = r["action"]
        price = d(r["price"])
        qty = int(r["qty"])

        if action == "OPEN_LONG":
            incoming("LONG", price, qty)
        elif action == "OPEN_SHORT":
            incoming("SHORT", price, qty)
        elif action == "CLOSE_ALL":
            close(qty, True)
        elif action in ("CLOSE_PART", "CLOSE_CARRY_LONG", "CLOSE_CARRY_SHORT"):
            close(qty, False)
        else:
            raise RuntimeError(f"snapshot 검증 불가 action: {action}")

        actual_signed = int(r.get("position_qty_after") or 0)
        expected_signed = pos_qty if pos_side == "LONG" else (-pos_qty if pos_side == "SHORT" else 0)
        actual_side = normalize_db_text(r.get("position_side_after")).upper()
        expected_side = pos_side if pos_side in ("LONG", "SHORT") else "NONE"
        actual_avg = r.get("avg_price_after")
        expected_avg = avg_price if pos_qty > 0 else Decimal("0")

        if actual_signed != expected_signed or actual_side != expected_side:
            raise RuntimeError(
                f"{trade_date} seq={r['seq']} 포지션 snapshot 불일치: "
                f"DB=({actual_side},{actual_signed}) / expected=({expected_side},{expected_signed})"
            )

        if actual_avg is None or abs(d(actual_avg) - expected_avg) > Decimal("0.00000001"):
            raise RuntimeError(
                f"{trade_date} seq={r['seq']} 평단 snapshot 불일치: "
                f"DB={actual_avg} / expected={expected_avg}"
            )


# ============================================================
# 야간장
# - source 거래일 파일의 18:00 이후 실제 체결은 직전 거래일 장부에 append
# - calendar의 직전 거래일을 사용
# - 재실행 시 같은 source_trade_date의 기존 night_append를 먼저 삭제
# - 야간 반영 후 전일 summary를 전체 재계산
# ============================================================
def split_night_and_regular(df_std: pd.DataFrame) -> Tuple[pd.DataFrame, pd.DataFrame]:
    if df_std.empty:
        return df_std.copy(), df_std.copy()

    night_mask = df_std["bar_dt"].dt.time >= time(18, 0)
    return df_std[night_mask].copy(), df_std[~night_mask].copy()


def remap_night_datetime(night_df: pd.DataFrame, prev_trade_date: date) -> pd.DataFrame:
    out = night_df.copy()
    out["bar_dt"] = out["bar_dt"].apply(
        lambda x: datetime.combine(prev_trade_date, x.time())
    )
    return out


def delete_previous_night_append(
    cursor,
    prev_day_id: int,
    source_trade_date: date,
) -> int:
    marker = f"night_append:{source_trade_date}%"
    cursor.execute(
        """
        DELETE FROM futures_sim_trade
        WHERE day_id=%s
          AND note LIKE %s
        """,
        (prev_day_id, marker),
    )
    return int(cursor.rowcount or 0)


def process_night_rows(
    cursor,
    run_id: int,
    source_trade_date: date,
    night_df: pd.DataFrame,
    point_value: int,
) -> Optional[date]:
    prev_trade_date = get_prev_trading_date(cursor, source_trade_date)
    if prev_trade_date is None:
        if night_df.empty:
            return None
        raise RuntimeError(
            f"{source_trade_date} 야간장 대상 직전 거래일을 calendar에서 찾지 못했습니다."
        )

    prev_day_id = get_day_id(cursor, run_id, prev_trade_date)

    # 전일 day가 없고 이번 파일에도 야간 체결이 없으면 할 일 없음.
    if prev_day_id is None and night_df.empty:
        return prev_trade_date

    if prev_day_id is None:
        prev_day_id = upsert_day_row(cursor, run_id, prev_trade_date)

    # 같은 source 날짜에서 이전에 붙인 야간행은 항상 제거.
    deleted_night = delete_previous_night_append(
        cursor,
        prev_day_id,
        source_trade_date,
    )

    # 야간 체결도 없고 과거 stale 야간행도 없었다면 전일을 건드릴 이유가 없다.
    # 이 경우 불필요한 과거 포지션 replay/summary 재계산도 하지 않는다.
    if night_df.empty and deleted_night == 0:
        if DEBUG:
            print(
                f" - 야간장: source={source_trade_date} / 신규 야간체결 없음 / "
                "기존 append 없음 -> 전일 변경 없음"
            )
        return prev_trade_date

    # 야간 신규등록 또는 stale 야간행 삭제가 실제로 있었을 때만
    # 삭제 후 전일 정규/기존 체결 기준 종료포지션을 복원한다.
    night_initial_pos = get_day_end_position(cursor, run_id, prev_trade_date)

    if not night_df.empty:
        mapped = remap_night_datetime(night_df, prev_trade_date)
        night_trades, _, _, night_end_pos = build_trade_records(
            mapped,
            point_value,
            night_initial_pos,
        )

        marker = f"night_append:{source_trade_date}"
        for t in night_trades:
            old_note = t.get("note")
            t["note"] = (
                f"{marker};{old_note}" if old_note else marker
            )[:120]

        append_trades(cursor, prev_day_id, night_trades)

        if DEBUG:
            print(
                f" - 야간장: source={source_trade_date} -> day={prev_trade_date} / "
                f"initial={fmt_position(night_initial_pos)} / rows={len(mapped)} / "
                f"end={fmt_position(night_end_pos)}"
            )

    # delta 더하기 금지. 항상 전체 전일 summary 재계산.
    recalculate_day_summary_from_db(
        cursor,
        run_id,
        prev_trade_date,
        point_value,
    )

    return prev_trade_date


# ============================================================
# 파일 스캔 / 대상 선택 / 의존성 검사
# ============================================================
def scan_latest_files_by_date(base_path: str) -> Dict[date, str]:
    candidates: Dict[date, List[str]] = {}

    for fn in os.listdir(base_path):
        if fn.startswith("~$"):
            continue
        if not fn.lower().endswith(FILE_GLOB_EXT):
            continue

        fp = os.path.join(base_path, fn)
        trade_date = parse_trade_date_from_filename(fn)
        if not trade_date:
            continue

        candidates.setdefault(trade_date, []).append(fp)

    chosen: Dict[date, str] = {}
    for trade_date, paths in candidates.items():
        paths.sort(key=lambda x: os.path.getmtime(x), reverse=True)
        chosen[trade_date] = paths[0]

    return dict(sorted(chosen.items(), key=lambda kv: kv[0]))


def resolve_target_dates() -> List[date]:
    # CLI: python futures_excel_upload_final.py 2026-08-25 2026-08-26
    cli = [x for x in sys.argv[1:] if re.fullmatch(r"\d{4}-\d{2}-\d{2}", x)]
    raw = cli if cli else TARGET_DATES
    return [parse_date_text(x) for x in raw]


def choose_source_files(all_files: Dict[date, str], target_dates: List[date]) -> Dict[date, str]:
    if not target_dates:
        return all_files

    missing = [x for x in target_dates if x not in all_files]
    if missing:
        raise RuntimeError(
            "요청한 날짜의 엑셀을 BASE_PATH에서 찾지 못했습니다: "
            + ", ".join(str(x) for x in missing)
        )

    return {x: all_files[x] for x in sorted(set(target_dates))}


def validate_dependency_coverage(
    cursor,
    run_id: int,
    source_dates: List[date],
):
    if not STRICT_DEPENDENCY_CHECK or not source_dates:
        return

    first_date = min(source_dates)
    last_date = max(source_dates)

    # 과거 재등록 시 '첫 날짜~현재 DB 마지막 거래일'까지 이미 DB에 체결이 있는 날짜가
    # source에 빠져 있으면 이후 이월 포지션이 낡은 상태로 남을 수 있다.
    cursor.execute(
        """
        SELECT DISTINCT d.trade_date
        FROM futures_sim_run_day d
        WHERE d.run_id=%s
          AND d.trade_date >= %s
          AND EXISTS (
              SELECT 1 FROM futures_sim_trade t WHERE t.day_id=d.day_id
          )
        ORDER BY d.trade_date ASC
        """,
        (run_id, first_date),
    )

    db_dates = [row[0] for row in cursor.fetchall()]
    source_set = set(source_dates)

    # source 마지막 이후 DB 날짜가 있으면 과거만 부분 수정하는 상황이므로 역시 위험.
    missing = [x for x in db_dates if x not in source_set]

    if missing:
        show = ", ".join(str(x) for x in missing[:20])
        more = "" if len(missing) <= 20 else f" 외 {len(missing)-20}개"
        raise RuntimeError(
            "이월 포지션 연쇄 재계산 안전검사 실패.\n"
            f"이번 처리 범위: {first_date} ~ {last_date}\n"
            f"{first_date} 이후 DB에 체결이 있으나 이번 source에 없는 일자: {show}{more}\n"
            "과거 날짜를 고치면 그 이후 거래일도 다시 계산해야 합니다. "
            "해당 원본 엑셀을 함께 처리하거나, 정말 의도한 부분등록일 때만 "
            "STRICT_DEPENDENCY_CHECK=False로 변경하세요."
        )


# ============================================================
# day summary DB 읽기 / 검증
# ============================================================
def fetch_day_summary(cursor, run_id: int, trade_date: date):
    cursor.execute(
        """
        SELECT day_id, pnl_points, pnl_amount, fee_total, pnl_amount_net
        FROM futures_sim_run_day
        WHERE run_id=%s AND trade_date=%s
        """,
        (run_id, trade_date),
    )
    return cursor.fetchone()


def verify_day_summary(
    cursor,
    run_id: int,
    trade_date: date,
    point_value: int,
):
    row = fetch_day_summary(cursor, run_id, trade_date)
    if not row:
        raise RuntimeError(f"{trade_date} day summary 없음")

    day_id, db_points, db_amount, db_fee, db_net = row
    initial = fetch_previous_position(cursor, run_id, trade_date, verify_snapshot=False)
    trades = fetch_db_trades_for_day(cursor, run_id, trade_date)

    if trades:
        _, calc_points, calc_fee = replay_trade_actions(trades, initial, strict=True)
    else:
        calc_points, calc_fee = Decimal("0"), 0

    calc_points = q4(calc_points)
    calc_amount = money_round(calc_points * Decimal(point_value))
    calc_net = calc_amount - int(calc_fee)

    if (
        q4(d(db_points or 0)) != calc_points
        or int(db_amount or 0) != calc_amount
        or int(db_fee or 0) != int(calc_fee)
        or int(db_net or 0) != calc_net
    ):
        raise RuntimeError(
            f"{trade_date} summary 검증 실패: "
            f"DB=({db_points},{db_amount},{db_fee},{db_net}) / "
            f"CALC=({calc_points},{calc_amount},{calc_fee},{calc_net})"
        )

    if DEBUG:
        print(
            f" [VERIFY SUMMARY OK] {trade_date} "
            f"pnl={calc_points} amount={calc_amount:,} fee={calc_fee:,} net={calc_net:,}"
        )


# ============================================================
# 메인
# ============================================================
def main():
    print(f"[RUNNING FILE] {os.path.abspath(__file__)}")

    all_files = scan_latest_files_by_date(BASE_PATH)
    target_dates = resolve_target_dates()
    files_by_date = choose_source_files(all_files, target_dates)

    if not files_by_date:
        print(f"[ERR] 업로드할 파일이 없습니다: {BASE_PATH}")
        return

    source_dates = list(files_by_date.keys())
    print(
        f"[INFO] 대상 날짜 {len(source_dates)}개: "
        f"{source_dates[0]} ~ {source_dates[-1]}"
    )
    print(f"[INFO] RUN_ID={RUN_ID}")
    print("[INFO] 현재 일자는 기존 trade 삭제 후 재등록")
    print("[INFO] 배치 첫 날짜의 시작 포지션은 DB에서 복원")
    print("[INFO] 이후 날짜도 직전 처리결과가 반영된 DB 상태를 다시 읽어 사용")
    print("[INFO] day_comment는 절대 수정하지 않음")

    db = connect_db()

    # 마지막 검증에서 사용할 point value. 날짜별로 저장.
    point_value_by_date: Dict[date, int] = {}
    affected_dates: Set[date] = set()

    try:
        # DDL은 implicit commit 가능성이 있으므로 데이터 트랜잭션 전에 끝낸다.
        with db.cursor() as cursor:
            ensure_trade_position_columns(cursor)
        db.commit()

        with db.cursor() as cursor:
            validate_dependency_coverage(cursor, RUN_ID, source_dates)

            first_source_date = source_dates[0]

            for trade_date, filepath in files_by_date.items():
                base = os.path.basename(filepath)
                print()
                print("=" * 76)
                print(f"{trade_date} / {base}")
                print("=" * 76)

                df_std, point_value = read_fills_excel(filepath, trade_date)
                point_value_by_date[trade_date] = point_value

                night_df, regular_df = split_night_and_regular(df_std)

                # 1) source 거래일의 야간 체결을 직전 거래일 장부에 반영.
                prev_affected = process_night_rows(
                    cursor,
                    RUN_ID,
                    trade_date,
                    night_df,
                    point_value,
                )
                if prev_affected is not None:
                    affected_dates.add(prev_affected)
                    point_value_by_date.setdefault(prev_affected, point_value)

                # 2) 현재 정규장 시작 포지션.
                # 배치 첫날은 반드시 DB history와 snapshot을 대조해 검증한다.
                initial_pos = fetch_previous_position(
                    cursor,
                    RUN_ID,
                    trade_date,
                    verify_snapshot=(
                        VERIFY_FIRST_INITIAL_POSITION
                        and trade_date == first_source_date
                    ),
                )

                print(
                    f" - 시작 포지션(DB): prev={initial_pos.get('prev_trade_date')} / "
                    f"{fmt_position(initial_pos)}"
                )

                # 3) 현재 날짜만 삭제 후 재등록.
                day_id = upsert_day_row(cursor, RUN_ID, trade_date)
                delete_existing_trades(cursor, day_id)

                if regular_df.empty:
                    update_day_summary(
                        cursor,
                        day_id,
                        Decimal("0"),
                        point_value,
                        0,
                    )
                    affected_dates.add(trade_date)
                    print(" - 정규장 실제 체결 없음: trade=0 / day summary=0")
                    continue

                trades, expected_pnl, expected_fee, expected_end = build_trade_records(
                    regular_df,
                    point_value,
                    initial_pos,
                )

                insert_trades(cursor, day_id, trades)

                # 중요: summary는 build 결과만 믿고 끝내지 않고,
                # 실제 DB에 저장된 trade를 '전일 포지션 포함'해서 다시 replay한다.
                db_end, db_pnl, db_fee, pnl_amount, pnl_net = recalculate_day_summary_from_db(
                    cursor,
                    RUN_ID,
                    trade_date,
                    point_value,
                )

                # build 계산과 DB replay가 다르면 저장하지 않고 전체 rollback.
                if q4(expected_pnl) != q4(db_pnl) or int(expected_fee) != int(db_fee):
                    raise RuntimeError(
                        f"{trade_date} 계산 이중검증 실패: "
                        f"BUILD pnl={q4(expected_pnl)} fee={expected_fee} / "
                        f"DB_REPLAY pnl={q4(db_pnl)} fee={db_fee}"
                    )

                if not position_equal(expected_end, db_end):
                    raise RuntimeError(
                        f"{trade_date} 종료포지션 이중검증 실패: "
                        f"BUILD={fmt_position(expected_end)} / DB={fmt_position(db_end)}"
                    )

                if VERIFY_AFTER_WRITE:
                    verify_day_snapshots(cursor, RUN_ID, trade_date, initial_pos)
                    verify_day_summary(cursor, RUN_ID, trade_date, point_value)

                inserted = count_trades(cursor, day_id)
                affected_dates.add(trade_date)

                print(f" - trades={inserted}")
                print(f" - 종료 포지션: {fmt_position(db_end)}")
                print(
                    f" - pnl_points={q4(db_pnl)} / pnl_amount={pnl_amount:,} "
                    f"/ fee={db_fee:,} / net={pnl_net:,}"
                )

            # ----------------------------------------------------
            # 마지막 정합성 정리
            # 뒤 날짜의 야간장이 앞 날짜에 append될 수 있으므로,
            # 모든 처리가 끝난 뒤 영향받은 날짜 summary를 최종 재계산/검증한다.
            # ----------------------------------------------------
            print()
            print("[FINAL AUDIT] 영향받은 날짜 최종 재계산/검증")

            for audit_date in sorted(affected_dates):
                pv = point_value_by_date.get(audit_date, int(FORCE_POINT_VALUE or 50000))
                recalculate_day_summary_from_db(
                    cursor,
                    RUN_ID,
                    audit_date,
                    pv,
                )
                if VERIFY_AFTER_WRITE:
                    verify_day_summary(cursor, RUN_ID, audit_date, pv)

            # 모든 날짜 + 야간 append + 검증 성공시에만 반영.
            if DRY_RUN:
                db.rollback()
            else:
                db.commit()

        print()
        if DRY_RUN:
            print("[DRY-RUN OK] 계산/검증 완료 - DB 변경사항은 ROLLBACK")
        else:
            print("[OK] 전체 업로드 / 이월 / 야간장 / 손익 검증 완료")

    except PermissionError as e:
        db.rollback()
        print(f"[ERR] 파일 접근 불가: {e}")
        raise

    except Exception as e:
        db.rollback()
        print()
        print(f"[ERR] 전체 작업 ROLLBACK: {e}")
        raise

    finally:
        db.close()


if __name__ == "__main__":
    main()
