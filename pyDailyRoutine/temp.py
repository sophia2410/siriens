from datetime import datetime, timedelta
import pandas as pd
from db.database import Database
from api.utils import get_current_futures_code, setup_logger

class StrategyExecutor:
    _has_position = False

    def __init__(self, kiwoom=None, dry_run=False):
        self.kiwoom = kiwoom
        self.dry_run = dry_run
        self.logger = setup_logger("strategy_executor")
        self.db = Database.get_instance()
        self.account = "SIMULATED" if dry_run or kiwoom is None else kiwoom.dynamicCall("GetLoginInfo(QString)", "ACCNO").strip().split(';')[0]
        self._prepared_strategies = {}

    def prepare_strategy(self, strategy_name: str, entry_datetime: datetime, code: str = None, strategy_key: str = None) -> str:
        if strategy_name != "A":
            self.logger.warning(f"[미지원 전략] {strategy_name} → 준비 스킵")
            return

        if not code:
            if self.kiwoom:
                code = get_current_futures_code(self.kiwoom)
            else:
                self.logger.error("code가 지정되지 않았고, Kiwoom 객체도 없습니다.")
                return

        df = self._get_adjusted_tick_range(code, entry_datetime)
        if df.empty:
            self.logger.warning("진입 판단용 캔들 없음 → 전략 스킵")
            return

        direction = self._decide_direction(df)
        key = strategy_key or entry_datetime.strftime('%Y-%m-%d %H:%M:%S')
        self._prepared_strategies[key] = {
            "code": code,
            "direction": direction,
            "strategy": strategy_name
        }

        self.logger.info(f"[전략 준비] {strategy_name}, 방향: {direction.upper()}, 실행 예정: {entry_datetime}")
        return key}, 실행 예정: {entry_datetime}")

    def feed_tick(self, code, tick_time, price, dry_run=False, strategy_key=None):
        if self.replay_state.get("done"):
            return

        tick_dt = datetime.strptime(tick_time, "%H%M%S")
        entry_dt = self.replay_state["entry_time"]

        if not self.replay_state["has_entered"] and tick_dt >= entry_dt:
            key = strategy_key or entry_dt.strftime('%Y-%m-%d %H:%M:%S')
            info = self._prepared_strategies.get(key)
            if not info:
                self.logger.warning(f"[진입 실패] 전략 키 없음: {key}")
                return

            direction = info["direction"]
            self.logger.info(f"[REPLAY 진입] {direction.upper()} @ {price:.2f} (틱: {tick_time})")
            self.replay_state.update({
                "has_entered": True,
                "entry_price": price,
                "direction": direction,
                "entry_tick_time": tick_time
            })
            return

        if self.replay_state["has_entered"]:
            entry_price = self.replay_state["entry_price"]
            direction = self.replay_state["direction"]

            stop_price = entry_price - 0.5 if direction == "buy" else entry_price + 0.5
            trigger = False
            result_type = ""

            if (direction == "buy" and price <= stop_price) or (direction == "sell" and price >= stop_price):
                result_type = "stoploss"
                trigger = True

            elif tick_dt >= entry_dt + timedelta(minutes=1):
                price_0900 = entry_price
                upper_limit = price_0900 + 0.15
                lower_limit = price_0900 - 0.15
                if (direction == "buy" and price <= lower_limit) or (direction == "sell" and price >= upper_limit):
                    result_type = "rapid_close"
                    trigger = True

            elif tick_dt >= entry_dt + timedelta(minutes=2):
                result_type = "close"
                trigger = True

            if trigger:
                pnl = (price - entry_price) if direction == "buy" else (entry_price - price)
                self.logger.info(f"[REPLAY 청산] {result_type.upper()} → 진입: {entry_price:.2f}, 청산: {price:.2f}, PnL={pnl:.2f}")
                self.replay_state["done"] = True
                if not dry_run:
                    self._save_trade_result(code, "A", direction, entry_price, price, entry_dt, tick_time, result_type)

    def init_replay_state(self, entry_time: datetime):
        self.replay_state = {
            "entry_time": entry_time,
            "has_entered": False,
            "entry_price": None,
            "direction": None,
            "entry_tick_time": None,
            "done": False
        }

    def _save_trade_result(self, code, strategy, direction, entry_price, exit_price, entry_datetime, exit_time, result_type='close'):
        pnl = (exit_price - entry_price) if direction == 'buy' else (entry_price - exit_price)
        query = """
            INSERT INTO futures_trade_result (
                code, strategy, direction,
                entry_price, exit_price,
                pnl, entry_time, exit_time, result_type, created_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
        """
        params = (
            code, strategy, direction,
            entry_price, exit_price,
            pnl, entry_datetime, exit_time, result_type
        )
        self.db.execute_query(query, params)
        self.logger.info(f"💾 [거래결과 저장] {entry_price:.2f} → {exit_price:.2f}, PnL={pnl:.2f}, Type={result_type}")

    def _get_adjusted_tick_range(self, code, entry_datetime: datetime):
        from_time = entry_datetime - timedelta(minutes=5)
        to_time = entry_datetime - timedelta(seconds=1)
        from_str = from_time.strftime("%H%M%S")
        to_str = to_time.strftime("%H%M%S")
        date_str = entry_datetime.strftime("%Y-%m-%d")

        query = """
            SELECT time, price FROM futures_realtime_tick
            WHERE code = %s AND date = %s
              AND time BETWEEN %s AND %s
            ORDER BY time
        """
        cursor = self.db.execute_query(query, (code, date_str, from_str, to_str))
        rows = cursor.fetchall()
        df = pd.DataFrame(rows, columns=['time', 'price'])

        if df.empty:
            self.logger.warning(f"⚠️ [보정실패] {from_str}~{to_str} 틱 없음 → 판단 불가")
        else:
            self.logger.info(f"[보정성공] 시가={df.iloc[0]['price']}, 종가={df.iloc[-1]['price']} (틱 수: {len(df)})")

        return df

    def _decide_direction(self, df):
        open_price = df.iloc[0]['price']
        close_price = df.iloc[-1]['price']
        return "buy" if close_price > open_price else "sell"
