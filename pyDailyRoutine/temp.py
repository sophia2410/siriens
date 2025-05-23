import pymysql
import configparser
from datetime import datetime, timedelta

# 1. DB 설정 불러오기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

conn = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset'),
    autocommit=False
)

def insert_daily_amount_rank_history(from_date: str, to_date: str, top_n: int = 100):
    try:
        with conn.cursor() as curs:
            # 2. 일자 목록 추출
            sql_dates = f"""
                SELECT DISTINCT date
                FROM daily_price
                WHERE date BETWEEN '{from_date}' AND '{to_date}'
                ORDER BY date
            """
            curs.execute(sql_dates)
            all_dates = curs.fetchall()

            for (trade_date,) in all_dates:
                print(f"[{trade_date}] 처리 중...")

                # 3. 기존 데이터 삭제
                delete_sql = f"DELETE FROM daily_amount_rank WHERE date = '{trade_date}'"
                curs.execute(delete_sql)

                # 4. 상위 거래대금 종목 삽입
                insert_sql = f"""
                    INSERT INTO daily_amount_rank (date, code, rank, amount)
                    SELECT
                        A.date,
                        A.code,
                        @r := @r + 1 AS rank,
                        A.amount
                    FROM (
                        SELECT date, code, amount
                        FROM daily_price
                        WHERE date = '{trade_date}'
                          AND amount IS NOT NULL AND amount > 0
                        ORDER BY amount DESC
                        LIMIT {top_n}
                    ) A, (SELECT @r := 0) r
                """
                curs.execute(insert_sql)
                print(f"  → 상위 {top_n}개 저장 완료")

            conn.commit()
            print(" ✅ 전체 완료. 데이터 커밋되었습니다.")

    except Exception as e:
        print("❌ 오류 발생:", str(e))
        import traceback
        print(traceback.format_exc())
        conn.rollback()

    finally:
        conn.close()

# 5. 실행 구간
if __name__ == "__main__":
    # insert_daily_amount_rank_history('2010-01-01', datetime.today().strftime('%Y-%m-%d'), top_n=100)
    insert_daily_amount_rank_history('2021-01-01', '2025-05-22', top_n=100)