import yfinance as yf
from pykrx import stock
# trading_value = stock.get_index_price_change('20231218', '20231218', "KOSPI")

# print(type(trading_value))
# print(trading_value)

df = stock.get_market_ohlcv("20220720", "20220720", "005930")
print(df)