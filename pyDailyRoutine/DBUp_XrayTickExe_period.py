import pymysql
import configparser
import pandas as pd
from datetime import datetime

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

try:
    with db.cursor() as cursor:
        # 2025-02-26 이후 calendar의 date 리스트 가져오기
        cursor.execute("""
            SELECT date FROM calendar 
            WHERE date > '2025-05-22' AND date <= CURDATE() AND cal_yn = 'Y'
            ORDER BY date ASC
        """)
        date_list = [row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()]

    for date in date_list:
        try:
            print(f"\n[INFO] Processing date: {date}")

            with db.cursor() as cursor:
                # snapshot delete/insert
                cursor.execute('DELETE FROM status_snapshot WHERE snapshot_date = %s', (date,))
                db.commit()

                cursor.execute('''
                    INSERT INTO status_snapshot (snapshot_date, status, code, type, journal_date)
                    SELECT 
                        %s,
                        MIN(status), code, MIN(type), MAX(journal_date)
                    FROM journal_feature
                    WHERE status != 'normal'
                    GROUP BY code
                ''', (date,))
                db.commit()

            # Excel 파일 읽기
            file_path = f'E:/Project/202410/data/_XrayTickExe/{date}.xlsx'
            df = pd.read_excel(file_path)

            # 기존 데이터 삭제
            with db.cursor() as cursor:
                cursor.execute("DELETE FROM kiwoom_xray_tick_executions WHERE date = %s", (date,))
                db.commit()

            # 데이터 삽입
            with db.cursor() as cursor:
                for _, row in df.iterrows():
                    code = str(row['코드']).zfill(6)
                    cursor.execute('''
                        INSERT INTO kiwoom_xray_tick_executions 
                        (date, time, code, name, current_price, change_rate, volume, type)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    ''', (
                        date,
                        row['시간'],
                        code,
                        row['종목명'],
                        row['현재가'],
                        row['등락률'],
                        row['수량'],
                        row['구분']
                    ))
                db.commit()

            # 집계 테이블 초기화 및 삽입
            with db.cursor() as cursor:
                cursor.execute("DELETE FROM xraytick_summary WHERE date = %s", (date,))
                db.commit()

                cursor.execute('''
                    INSERT INTO xraytick_summary (date, code, name, tot_cnt, tot_volume, tot_amt, avg_amt)
                    SELECT date, code, name, COUNT(*), SUM(volume), 
                           SUM(current_price * volume),
                           ROUND(SUM(current_price * volume) / SUM(volume), 0)
                    FROM kiwoom_xray_tick_executions
                    WHERE date = %s
                    GROUP BY date, code, name
                ''', (date,))
                db.commit()

            # comm_cd 처리
            with db.cursor() as cursor:
                cursor.execute("SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'XR000' ORDER BY cd")
                comm_data = cursor.fetchall()

            for comm_row in comm_data:
                cd, nm, nm_sub1 = comm_row
                nm = nm.decode('utf-8')
                nm_sub1 = nm_sub1.decode('utf-8')

                trade_days, occurrences, min_amt = map(int, nm.split(','))
                tot_amt_condition = min_amt * 100000000

                with db.cursor() as cursor:
                    cursor.execute('''
                        SELECT A.code, A.occurrence_days
                        FROM (
                            SELECT ks.code, COUNT(DISTINCT ks.date) AS occurrence_days
                            FROM xraytick_summary ks
                            JOIN (
                                SELECT date FROM calendar
                                WHERE date <= %s
                                ORDER BY date DESC
                                LIMIT %s
                            ) rd ON ks.date = rd.date
                            WHERE ks.tot_amt >= %s
                            GROUP BY ks.code
                            HAVING COUNT(DISTINCT ks.date) >= %s
                        ) A
                        JOIN xraytick_summary xs ON A.code = xs.code
                        WHERE xs.date = %s
                        AND xs.tot_amt >= %s
                    ''', (date, trade_days, tot_amt_condition, occurrences, date, tot_amt_condition))
                    result = cursor.fetchall()

                for code, occurrence_days in result:
                    with db.cursor() as cursor:
                        cursor.execute('''
                            SELECT IFNULL(MAX(stock_count), 0)
                            FROM xraytick_extracted_stocks
                            WHERE comm_cd = %s
                            AND extract_date BETWEEN DATE_SUB(%s, INTERVAL 1 MONTH) AND %s
                            AND code = %s
                        ''', (cd, date, date, code))
                        max_stock_count = cursor.fetchone()[0]
                        stock_count = max_stock_count + 1 if max_stock_count > 0 else 1

                    with db.cursor() as cursor:
                        cursor.execute('''
                            INSERT INTO xraytick_extracted_stocks 
                            (code, occurrence_days, comm_cd, extract_date, stock_count)
                            VALUES (%s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE occurrence_days = VALUES(occurrence_days), 
                                                    stock_count = VALUES(stock_count)
                        ''', (code, occurrence_days, cd, date, stock_count))
                        db.commit()

            # journal_feature 자동 등록
            with db.cursor() as cursor:
                cursor.execute('''
                    INSERT INTO journal_feature (journal_date, code, type, comment)
                    SELECT xe.extract_date, xe.code, 'xraytick', COMPRESS(CONCAT(xe.extract_date, ' / 10억 10일간 5회이상 매수. 1일차'))
                    FROM xraytick_extracted_stocks xe
                    WHERE xe.extract_date = (
                        SELECT MAX(date) FROM calendar WHERE date <= %s
                    )
                    AND xe.comm_cd = 'XR002'
                    AND xe.stock_count = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM journal_feature tj 
                        WHERE tj.journal_date = xe.extract_date AND tj.code = xe.code AND tj.type = 'xraytick'
                    )
                ''', (date,))
                db.commit()

            print(f"[SUCCESS] {date} 처리 완료")

        except Exception as e:
            print(f"[ERROR] {date} 처리 중 오류 발생: {e}")

finally:
    db.close()
