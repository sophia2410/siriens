import pymysql
import configparser
import pandas as pd

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
    # 1. Max date 가져오기
    with db.cursor() as cursor:
        sql = "SELECT max(date) date FROM calendar a WHERE date <= now()"
        # sql = "SELECT date FROM calendar a WHERE date = '2025-03-10'" 
        cursor.execute(sql)
        date = cursor.fetchone()[0].strftime('%Y-%m-%d')

        # 2024.11.10 추가
        # trade journal의 관심종목 스냅샷 저장하기 (일별 관심종목 기록 남기기 위함)

        snapshot_sql_delete = '''
        DELETE FROM `status_snapshot`
        WHERE `snapshot_date` = %s
        '''
        snapshot_sql_insert  = '''
        INSERT INTO `status_snapshot` (`snapshot_date`, `status`, `code`, `type`, `journal_date`)
        SELECT 
            %s,
            MIN(`status`) `status`,
            `code`,
            MIN(`type`) `type`,
            MAX(`journal_date`) `journal_date`
        FROM 
            `journal_feature`
        WHERE 
            `status` != 'normal'
        GROUP BY
            `code`
        ORDER BY
            `status`, `journal_date` desc
        '''

        # 해당 snapshot_date의 기존 데이터 삭제
        cursor.execute(snapshot_sql_delete, (date,))
        db.commit()

        # 새로운 데이터 삽입
        cursor.execute(snapshot_sql_insert, (date,))
        db.commit()

    # 2. Excel 파일 읽기
    file_name = f'{date}.xlsx'
    file_path = f'E:/Project/202410/data/_XrayTickExe/{file_name}'
    df = pd.read_excel(file_path)

    # 3. 기존 데이터 삭제
    with db.cursor() as cursor:
        del_sql = f"DELETE FROM `kiwoom_xray_tick_executions` WHERE date = '{date}'"
        cursor.execute(del_sql)
        db.commit()

    # 4. 새로운 데이터 삽입
    with db.cursor() as cursor:
        for index, row in df.iterrows():
            code = str(row['코드']).zfill(6)
            sql = '''
            INSERT INTO `kiwoom_xray_tick_executions` 
            (`date`, `time`, `code`, `name`, `current_price`, `change_rate`, `volume`, `type`)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            '''
            cursor.execute(sql, (
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

    # 5. 집계 테이블 테이블 삭제
    with db.cursor() as cursor:
        del_summary_sql = f"DELETE FROM `xraytick_summary` WHERE date = '{date}'"
        cursor.execute(del_summary_sql)
        db.commit()

    # 6. 집계 테이블 데이터 삽입
    with db.cursor() as cursor:
        summary_sql = '''
        INSERT INTO xraytick_summary (date, code, name, tot_cnt, tot_volume, tot_amt, avg_amt)
        SELECT date, code, name, COUNT(*) AS tot_cnt, SUM(volume) AS tot_volume, 
               SUM(current_price * volume) AS tot_amt, 
               ROUND(SUM(current_price * volume) / SUM(volume), 0) AS avg_amt
        FROM kiwoom_xray_tick_executions
        WHERE date = %s
        GROUP BY date, code, name
        '''
        cursor.execute(summary_sql, (date,))
        db.commit()


    # 2024.10.21 추가
    # xraytick_extracted_stocks 테이블 데이터 등록. 기간 처리는 (xraytick_batch_period.py 에서 함)
    # 7. comm_cd 데이터를 가져와서 처리
    with db.cursor() as cursor:
        comm_query = "SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'XR000' ORDER BY cd"
        cursor.execute(comm_query)
        comm_data = cursor.fetchall()

    # comm_cd 데이터를 기반으로 처리
    for comm_row in comm_data:
        cd, nm, nm_sub1 = comm_row
        nm = nm.decode('utf-8')
        nm_sub1 = nm_sub1.decode('utf-8')

        trade_days, occurrences, min_amt = map(int, nm.split(','))
        tot_amt_condition = min_amt * 100000000

        # stock 데이터를 가져오는 쿼리
        with db.cursor() as cursor:
            main_query = '''
            SELECT A.code, A.occurrence_days
            FROM (
                SELECT ks.code, COUNT(DISTINCT ks.date) AS occurrence_days
                FROM xraytick_summary ks
                JOIN (
                    SELECT date
                    FROM calendar
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
            '''
            cursor.execute(main_query, (date, trade_days, tot_amt_condition, occurrences, date, tot_amt_condition))
            result = cursor.fetchall()

        row_count = 0
        for row in result:
            code, occurrence_days = row
            # 기존 데이터 수 확인
            with db.cursor() as cursor:
                # Count existing records
                count_query = """
                    SELECT IFNULL(MAX(stock_count), 0) AS max_stock_count 
                    FROM xraytick_extracted_stocks 
                    WHERE comm_cd = %s 
                    AND extract_date BETWEEN DATE_SUB(%s, INTERVAL 1 MONTH) AND %s 
                    AND code = %s
                """
                cursor.execute(count_query, (cd, date, date, code))
                max_stock_count = cursor.fetchone()[0]
                stock_count = max_stock_count + 1 if max_stock_count > 0 else 1  # Add 1 to existing count

            # 데이터 삽입 또는 업데이트
            with db.cursor() as cursor:
                insert_query = '''
                INSERT INTO xraytick_extracted_stocks 
                (code, occurrence_days, comm_cd, extract_date, stock_count)
                VALUES (%s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE occurrence_days = VALUES(occurrence_days), 
                                        stock_count = VALUES(stock_count)
                '''
                cursor.execute(insert_query, (code, occurrence_days, cd, date, stock_count))
                db.commit()
                row_count += 1

        print(f"[INFO] Date: {date}, Condition: {cd} - {row_count} stocks saved.")

    # 2024.10.28 추가
    # journal_feature 에 연속매수 종목 추가해주기
    # 8. journal_feature 데이터 삽입 로직 추가
    insert_journal_feature_query = '''
    INSERT INTO journal_feature (journal_date, code, type, comment)
    SELECT xe.extract_date, xe.code, 'xraytick', COMPRESS(CONCAT(xe.extract_date, ' / 10억 10일간 5회이상 매수. 1일차'))
    FROM xraytick_extracted_stocks xe
    WHERE xe.extract_date = (
        SELECT MAX(date) 
        FROM calendar 
        WHERE date <= DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 0 DAY), '%Y-%m-%d')
    )
    AND xe.comm_cd = 'XR002' 
    AND xe.stock_count = 1 
    AND NOT EXISTS (
        SELECT 1 
        FROM journal_feature tj 
        WHERE tj.journal_date = xe.extract_date 
        AND tj.code = xe.code 
        AND tj.type = 'xraytick'
    )
    ORDER BY xe.extract_date ASC;
    '''

    # 기존에 사용한 cursor를 재사용합니다.
    cursor = db.cursor()
    cursor.execute(insert_journal_feature_query)
    db.commit()
    cursor.close()

finally:
    db.close()