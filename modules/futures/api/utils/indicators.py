# utils/indicators.py
import pandas as pd

def add_moving_averages(df: pd.DataFrame, windows=(5, 20, 120), price_col='close'):
    for w in windows:
        df[f'sma_{w}'] = df[price_col].rolling(window=w).mean()
    return df