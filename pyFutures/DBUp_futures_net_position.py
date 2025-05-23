import pandas as pd
import pymysql
import configparser

# 1. DB 설정 파일 경로
config_path = 'E:/Project/202410/www/boot/common/db/database_config.ini'

# 2. DB 연결 설정
config = configparser.ConfigParser()
config.read(config_path)
conn = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 3. CSV 파일 로드 (포지션 데이터)
# csv_path = "E:/Project/202410/data/_futures/InvestorTrades/krxdata_20231101_20250430.csv"
csv_path = "E:/Project/202410/data/_futures/InvestorTrades/krxdata_20250502_20250509.csv"

df = pd.read_csv(csv_path, encoding='cp949', usecols=['일자', '기관 합계', '개인', '외국인 합계'])
df.rename(columns={
    '일자': 'date',
    '기관 합계': 'net_institution',
    '개인': 'net_individual',
    '외국인 합계': 'net_foreign'
}, inplace=True)
df['date'] = pd.to_datetime(df['date']).dt.date
df = df.sort_values(by='date')  # ✅ 꼭 필요

# 4. 최근 선물 만기일 가져오기
with conn.cursor() as cursor:
    cursor.execute("""
        SELECT MAX(date) FROM calendar
        WHERE futures_expiry_yn = 'Y' AND date <= CURDATE()
    """)
    expiry_date = cursor.fetchone()[0]

# 5. 누적합 컬럼 추가를 위한 사전 정의
cum_foreign = 0
cum_institution = 0
cum_individual = 0

# 6. DB 업데이트
with conn.cursor() as cursor:
    for _, row in df.iterrows():
        date = row['date']
        nf = round(row['net_foreign'] / 100)
        ni = round(row['net_institution'] / 100)
        nd = round(row['net_individual'] / 100)

        # 누적합 계산 (선물 만기일 이후만)
        if expiry_date and date > expiry_date:
            cum_foreign += nf
            cum_institution += ni
            cum_individual += nd
        else:
            cum_foreign = 0
            cum_institution = 0
            cum_individual = 0

        sql = """
        UPDATE futures_1day SET
            net_foreign = %s,
            net_institution = %s,
            net_individual = %s,
            cum_net_foreign = %s,
            cum_net_institution = %s,
            cum_net_individual = %s
        WHERE date = %s
        """
        cursor.execute(sql, (
            nf, ni, nd,
            cum_foreign, cum_institution, cum_individual,
            date
        ))
    conn.commit()

conn.close()
print("✅ 순매수 + 누적 순매수 업데이트 완료")
