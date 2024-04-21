import pandas as pd
import pymysql
import configparser

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

try:
    with db.cursor() as cursor:
        sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
        # sql = "SELECT date FROM calendar a WHERE date = '20240411'"
        cursor.execute(sql)
        date = cursor.fetchone()[0].decode('utf-8')

    # Excel 파일 경로 설정
    file_name = f'{date}.xlsx'
    file_path = f'E:/Project/202410/data/_XrayTickExe/{file_name}'

    # pandas를 사용하여 Excel 파일 읽기
    df = pd.read_excel(file_path)
    
    with db.cursor() as cursor:
        del_sql = f"DELETE FROM `kiwoom_xray_tick_executions` WHERE date = '{date}'"
        cursor.execute(del_sql)

    with db.cursor() as cursor:
        # 'kiwoom_xray_tick_executions' 테이블에 데이터 삽입
        for index, row in df.iterrows():
            sql = '''
            INSERT INTO `kiwoom_xray_tick_executions` (`date`, `time`, `code`, `name`, `current_price`, `change_rate`, `volume`, `type`)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            '''

            code = str(row['코드']).zfill(6)  # 종목코드를 문자열로 변환하고, 6자리가 될 때까지 앞에 0을 채웁니다.
            cursor.execute(sql, (
                date,  # date
                row['시간'],         # time
                code,                # code
                row['종목명'],       # name
                row['현재가'],       # current_price
                row['등락률'],       # change_rate
                row['수량'],         # volume
                row['구분']          # type
            ))
        db.commit()
finally:
    db.close()