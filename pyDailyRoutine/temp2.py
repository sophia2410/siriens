import pandas as pd
from datetime import datetime, timedelta
from db.database import Database
from api.kiwoom.kiwoom import Kiwoom
from api.utils import get_current_futures_code, setup_logger

class StrategyExecutor:

    _has_position = False  # class-level 변수

    @classmethod
    def set_position_state(cls, has_position: bool):
        cls._has_position = has_position

    @classmethod
    def has_position(cls) -> bool:
        return cls._has_position

    def __init__(self, kiwoom=None, dry_run=False):
        self.kiwoom = kiwoom
        self.dry_run = dry_run
        self.logger = setup_logger("strategy_executor")
        self.db = Database.get_instance()
        self.account = "SIMULATED" if dry_run or kiwoom is None else \
            kiwoom.dynamicCall("GetLoginInfo(QString)", "ACCNO").strip().split(';')[0]
        self._prepared_strategies = {}

    def init_replay_state(self, entry_time: datetime):
        self.replay_state = {
            "entry_time": entry_time,
            "has_entered": False,
            "entry_price": None,
            "direction": None,
            "entry_tick_time": None,
            "done": False
        }

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
    def prepare_strategy(self, strategy_name: str, entry_datetime: datetime, code: str = None, strategy_key: str = None):
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

    def execute_order(self, strategy_name: str, entry_datetime: datetime):
        if strategy_name == "A":
            self._run_strategy_a(entry_datetime)
        else:
            self.logger.warning(f"[미지원 전략] {strategy_name} → 실행 스킵")

    def _run_strategy_a(self, entry_datetime: datetime):
        now = datetime.now()
        delta_ms = int((now - entry_datetime).total_seconds() * 1000)

        info = self._prepared_strategies.pop(entry_datetime, None)
        if not info:
            self.logger.warning(f"[실행 누락] 준비된 전략이 없습니다 → {entry_datetime}")
            return

        code = info["code"]
        direction = info["direction"]
        strategy = info["strategy"]

        entry_df = self._get_nearest_tick(code, entry_datetime)
        if entry_df.empty:
            self.logger.warning("진입 시점 틱 없음 (가장 가까운 틱도 없음)")
            return

        entry_price = entry_df.iloc[0]['price']
        entry_time = entry_df.iloc[0]['time']

        self.logger.info(f"📌 시스템 시각(now): {now.strftime('%H:%M:%S.%f')[:-3]}")
        self.logger.info(f"📌 목표 진입 시각: {entry_datetime.strftime('%H:%M:%S')}")
        self.logger.info(f"📌 진입 틱 시각: {entry_time}, 가격: {entry_price:.2f}")
        self.logger.info(f"📌 주문 시각 오차: {delta_ms}ms")

        self._send_order(code, direction, entry_price)
        self.kiwoom.request_futures_balance()

        # 진입 후 일정 시간 대기 후 포지션 확인
        QTimer.singleShot(3000, self._check_position_after_entry)  # 3초 후 호출

        def _check_position_after_entry(self):
            if not StrategyExecutor.has_position():
                self.logger.warning("⚠️ 진입 주문 후 포지션 확인되지 않음 → 미체결 상태 가능")
            else:
                self.logger.info("✅ 포지션 체결 확인됨")

        # 손절 조건 감시 (09:00:00~09:00:59)
        stop_loss_price = entry_price - 0.5 if direction == "buy" else entry_price + 0.5
        stop_df = self._get_ticks(code, entry_datetime, entry_datetime + timedelta(minutes=1))
        for _, row in stop_df.iterrows():
            price = row['price']
            if (direction == 'buy' and price <= stop_loss_price) or \
               (direction == 'sell' and price >= stop_loss_price):
                self.logger.warning(f"[손절 발생] {price:.2f} → {direction.upper()} @ {entry_price:.2f}")
                self._send_order(code, 'sell' if direction == 'buy' else 'buy', price)
                self._save_trade_result(code, strategy, direction, entry_price, price,
                                        entry_datetime, row['time'], 'stoploss')
                return

        # 09:00 종가 조회 (청산 기준가)
        close_0900_df = self._get_ticks(code, entry_datetime + timedelta(minutes=1) - timedelta(seconds=1),
                                        entry_datetime + timedelta(minutes=1))
        price_0900 = close_0900_df.iloc[-1]['price'] if not close_0900_df.empty else entry_price
        upper_limit = price_0900 + 0.15
        lower_limit = price_0900 - 0.15

        # 09:01 틱 감시 (3틱 급변 시 즉시 청산)
        close_start = entry_datetime + timedelta(minutes=1)
        close_end = close_start + timedelta(seconds=59)
        close_df = self._get_ticks(code, close_start, close_end)

        for _, row in close_df.iterrows():
            price = row['price']
            if (direction == 'buy' and price <= lower_limit) or \
               (direction == 'sell' and price >= upper_limit):
                self.logger.info(f"[3틱 급변 청산] 09:00={price_0900:.2f}, 현재={price:.2f}")
                self._send_order(code, 'sell' if direction == 'buy' else 'buy', price)
                self._save_trade_result(code, strategy, direction, entry_price, price,
                                        entry_datetime, row['time'], 'rapid_close')
                return

        # 일반 청산 (09:01 종가)
        if not close_df.empty:
            exit_price = close_df.iloc[-1]['price']
            self._send_order(code, 'sell' if direction == 'buy' else 'buy', exit_price)
            self._save_trade_result(code, strategy, direction, entry_price, exit_price,
                                    entry_datetime, close_df.iloc[-1]['time'], 'close')
            self.logger.info(f"[청산 완료] {direction.upper()} @ {entry_price:.2f} → {exit_price:.2f}")
        else:
            self.logger.warning("[청산 실패] 09:01 틱 없음 → 미청산 상태")

    def _save_trade_result(self, code, strategy, direction, entry_price, exit_price,
                           entry_datetime, exit_time, result_type='close'):
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

    def _get_nearest_tick(self, code, target_time: datetime):
        date_str = target_time.strftime("%Y-%m-%d")
        time_str = target_time.strftime("%H%M%S")

        query = """
            SELECT time, price FROM futures_realtime_tick
            WHERE code = %s AND date = %s
              AND time >= %s
            ORDER BY time
            LIMIT 1
        """
        cursor = self.db.execute_query(query, (code, date_str, time_str))
        rows = cursor.fetchall()
        return pd.DataFrame(rows, columns=['time', 'price'])

    def _get_ticks(self, code, from_time: datetime, to_time: datetime):
        query = """
            SELECT time, price FROM futures_realtime_tick
            WHERE code = %s AND date = %s
              AND time BETWEEN %s AND %s
            ORDER BY time
        """
        from_str = from_time.strftime("%H%M%S")
        to_str = to_time.strftime("%H%M%S")
        date_str = from_time.strftime("%Y-%m-%d")

        cursor = self.db.execute_query(query, (code, date_str, from_str, to_str))
        rows = cursor.fetchall()
        return pd.DataFrame(rows, columns=['time', 'price'])

    def _decide_direction(self, df):
        open_price = df.iloc[0]['price']
        close_price = df.iloc[-1]['price']
        return "buy" if close_price > open_price else "sell"

    def _send_order(self, code, direction, price):
        order_type = 1 if direction == 'buy' else 2
        self.logger.info(f"[주문전송] {direction.upper()} @ {price:.2f}")
        self.kiwoom.send_order(
            rqname="futures_order",
            screen_no="9000",
            acc_no=self.account,
            order_type=order_type,
            code=code,
            qty=1,
            price=price,
            hoga_gb="00",
            org_order_no=""
        )