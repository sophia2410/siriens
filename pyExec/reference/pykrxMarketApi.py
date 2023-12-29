import unittest
from pykrx import stock
import pandas as pd
import numpy as np
# pylint: disable-all
# flake8: noqa

from_date = "20230109"
print(type(from_date))
df = stock.get_market_ohlcv(from_date, "20230210", "010470")
print(df.head(3))