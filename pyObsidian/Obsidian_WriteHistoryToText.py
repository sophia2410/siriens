# 시장 정리 모아보기 :: stock_history 테이블 읽어서 텍스트 파일 작성
import pymysql
import configparser

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 커서 생성
cursor = db.cursor()

sql = "SELECT STR_TO_DATE(h.date, '%Y%m%d') date, STR_TO_DATE(h.report_date, '%Y%m%d') report_date, REPLACE(REPLACE(h.history,'[[',''),']]','') AS history FROM stock_history h order by id, h.report_date;" # 쿼리문
cursor.execute(sql) # 쿼리문 실행
results = cursor.fetchall() # 결과값을 리스트로 가져오기

# 데이터베이스 연결 종료
db.close()

# 일자별로 묶어서 시그널리포트 생성
signal_reports = {}
for date, report_date, history in results:

    history = history.decode('utf-8')
    
    # 튜플로 그룹화
    key = (date, report_date)
    if key not in signal_reports:
        signal_reports[key] = []
    signal_reports[key].append(history)

# 텍스트 파일로 쓰기
with open('signal_report.txt', 'w', encoding='utf-8') as f:
    for key in sorted(signal_reports):
        date, report_date = key
        
        f.write(f'★ {date} 시장 정리 (시그널리포트 {report_date})\n')
        for history in signal_reports[key]:
            f.write(f'{history}\n')
        f.write('\n')