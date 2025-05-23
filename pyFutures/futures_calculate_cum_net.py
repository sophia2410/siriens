import pymysql
import configparser
import pandas as pd

# 설정 로딩
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    cursorclass=pymysql.cursors.DictCursor
)

# 만기일 리스트 조회
with db.cursor() as cursor:
    cursor.execute("SELECT date FROM calendar WHERE futures_expiry_yn = 'Y' AND date >= '2023-09-14' ORDER BY date")
    cursor.execute("SELECT date FROM calendar WHERE futures_expiry_yn = 'Y' AND date >= '2025-03-13' ORDER BY date")
    expiry_dates = [row['date'] for row in cursor.fetchall()]

# 누적 갱신
with db.cursor() as cursor:
    for i in range(len(expiry_dates) - 1):
        start = expiry_dates[i] + pd.Timedelta(days=1)
        end = expiry_dates[i+1]
        
        cursor.execute("""
            SELECT date, net_foreign, net_institution, net_individual
            FROM futures_1day
            WHERE date BETWEEN %s AND %s
            ORDER BY date
        """, (start, end))
        rows = cursor.fetchall()

        cum_f, cum_i, cum_p = 0, 0, 0
        for row in rows:
            cum_f += row['net_foreign'] or 0
            cum_i += row['net_institution'] or 0
            cum_p += row['net_individual'] or 0
            cursor.execute("""
                UPDATE futures_1day
                SET cum_net_foreign = %s,
                    cum_net_institution = %s,
                    cum_net_individual = %s
                WHERE date = %s
            """, (cum_f, cum_i, cum_p, row['date']))
    db.commit()

db.close()
print("✅ 누적 순매수 백필 완료")
