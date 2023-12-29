
import pandas as pd
from bs4 import BeautifulSoup
import pymysql, calendar, json
import requests
import configparser
from datetime import datetime
from threading import Timer
import time
import pandas as pd
import numpy as np
from pykrx import stock


# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)


df = stock.get_index_price_change("20231213", "20231213", "KOSDAQ")

print(df.head())
