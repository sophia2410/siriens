import pandas as pd

def calc_vwap_session_from_df(df: pd.DataFrame) -> pd.Series:
    df = df.sort_values("datetime").copy()
    tp = (df["high"].astype(float) + df["low"].astype(float) + df["close"].astype(float)) / 3.0  # HLC3
    vol = df["volume"].fillna(0).astype(float)

    pv = tp * vol
    cum_pv = pv.cumsum()
    cum_vol = vol.cumsum()

    vwap = cum_pv / cum_vol.replace(0, pd.NA)
    return vwap.fillna(tp)

def recalc_and_update_vwap_for_dates(cursor, table: str, dates):
    if not dates:
        return

    sel_sql = f"""
        SELECT datetime, high, low, close, volume
        FROM {table}
        WHERE date = %s
        ORDER BY datetime
    """
    upd_sql = f"UPDATE {table} SET vwap_session=%s WHERE datetime=%s"

    for d in dates:
        cursor.execute(sel_sql, (d,))
        rows = cursor.fetchall()
        if not rows:
            continue

        df_day = pd.DataFrame(rows, columns=["datetime", "high", "low", "close", "volume"])
        df_day["datetime"] = pd.to_datetime(df_day["datetime"])
        df_day["vwap_session"] = calc_vwap_session_from_df(df_day)

        payload = [
            (None if pd.isna(v) else round(float(v), 3), dt.to_pydatetime())
            for v, dt in zip(df_day["vwap_session"], df_day["datetime"])
        ]
        cursor.executemany(upd_sql, payload)
