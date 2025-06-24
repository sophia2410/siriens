import pandas as pd
import pymysql
import configparser
import sys
import io
# UTF-8로 출력하도록 설정
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

print("Python 스크립트가 정상적으로 실행되었습니다.")
sys.stdout.flush()  # 출력 버퍼를 즉시 비워 PHP에서 결과가 바로 확인되도록 함

# PHP에서 일자를 인자로 받기
selected_date = sys.argv[1]

# 설정 파일 읽기 및 데이터베이스 연결
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    database=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# Excel 파일 읽기 (PHP에서 받은 일자 반영)
excel_file = f'E:/Project/202410/data/_MarketEvent/market_event_stocks_{selected_date}.xlsx'
df = pd.read_excel(excel_file)
df['종목코드'] = df['종목코드'].apply(lambda x: '{:06d}'.format(x))
print(df)
sys.stdout.flush()  # 출력 버퍼를 즉시 비워 PHP에서 결과가 바로 확인되도록 함

cursor = db.cursor()

# 테이블을 비우기
cursor.execute("DELETE FROM market_event_upload WHERE date = %s", (selected_date))

# 엑셀 데이터를 테이블에 삽입
for index, row in df.iterrows():
    code = row['종목코드']  # 엑셀의 코드 컬럼 이름
    name = row['종목명']  # 엑셀의 이름 컬럼 이름
    cursor.execute("INSERT INTO market_event_upload (date, code, name) VALUES (%s, %s, %s)", (selected_date, code, name))

db.commit()
cursor.close()
db.close()
