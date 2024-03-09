import pandas as pd
import pymysql
import os
import glob
import configparser
from datetime import datetime

# MySQL database db credentials
# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL 데이터베이스에 연결합니다. 연결 정보는 자신의 환경에 맞게 수정하세요.
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# Get the latest date from the MySQL database
with db.cursor() as cursor:
    sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
    cursor.execute(sql)
    date = cursor.fetchone()[0].decode('utf-8')

# Directory containing the Excel files
folder_path = 'E:/Project/202410/data/_순간체결_종목'

# Find Excel files that match the latest date
pattern = f"{folder_path}/{date}_*.xlsx"
matching_files = glob.glob(pattern)

# Process each matching Excel file
for file_path in matching_files:
    # Extract code from the filename
    filename = os.path.basename(file_path)
    _, code = filename.replace('.xlsx', '').split('_')

    with db.cursor() as cursor:
        exists_query = "SELECT COUNT(1) FROM kiwoom_realtime WHERE date = %s AND code = %s"
        if cursor.execute(exists_query, (date, code)).fetchone()[0] != 0:
            print(f"{date} 일자의 {code} 데이터는 이미 존재합니다.")
            continue;

    # Read the Excel file into a pandas DataFrame
    df = pd.read_excel(file_path)

    # Insert data into the database
    with db.cursor() as cursor:
        for index, row in df.iterrows():
            sql = '''
            INSERT INTO `kiwoom_realtime_add` (`date`, `code`, `time`, `price`, `diff`, `rate`, `volume`, `trade_amount`, `acc_volume`, `acc_trade_amount`, `create_dtime`)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
            '''
            cursor.execute(sql, (
                date,  # date from the filename
                code,  # code from the filename
                row['시간'],  # time from the Excel file
                row['체결가'],  # price from the Excel file
                row['전일대비'],  # diff from the Excel file
                row['등락률'],  # rate from the Excel file
                row['순간체결량'],  # volume from the Excel file
                row['순간대금(천)'] * 1000,  # trade_amount from the Excel file, converted to full amount
                row['누적거래량'],  # acc_volume from the Excel file
                row['누적거래량'] * row['체결가'],  # acc_trade_amount calculated from the Excel file
            ))

# Close the database connection
db.close()
