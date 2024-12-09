import pymysql
import configparser

# MySQL database db credentials
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

def update_hot_stocks(start_date, end_date):
    try:
        with db.cursor() as cursor:
            # 1. 조건에 맞는 종목 조회
            select_query = """
                SELECT mes.code, mes.event_id, mes.date, mes.close_rate, mes.trade_amount, kg.group_name
                FROM market_event_stocks mes
                JOIN market_events me ON mes.event_id = me.event_id
                LEFT JOIN keyword_groups kg ON me.keyword_group_id = kg.group_id
                WHERE mes.date BETWEEN %s AND %s
                AND ((mes.trade_amount > 1000 AND mes.close_rate > 10) OR (mes.trade_amount > 300 AND mes.close_rate > 29.5))
            """
            cursor.execute(select_query, (start_date, end_date))
            hot_stocks = cursor.fetchall()

            # 2. 핫 종목 업데이트 및 hot_stock_count 계산
            update_query = """
                UPDATE market_event_stocks AS mes
                SET mes.is_hot_stock = 'Y',
                    mes.hot_stock_count = (
                        SELECT IFNULL(MAX(inner_mes.hot_stock_count), 0) + 1
                        FROM market_event_stocks inner_mes
                        WHERE inner_mes.code = mes.code
                        AND inner_mes.date BETWEEN DATE_SUB(mes.date, INTERVAL 1 MONTH) AND mes.date
                        AND inner_mes.is_hot_stock = 'Y'
                    )
                WHERE mes.event_id = %s AND mes.code = %s AND mes.date = %s
            """

            # 3. 각 핫 종목에 대해 업데이트 수행
            for stock in hot_stocks:
                code = stock[0]
                event_id = stock[1]
                date = stock[2]
                close_rate = stock[3]
                trade_amount = stock[4]
                group_name = stock[5]

                # hot_stock_count 업데이트
                cursor.execute(update_query, (event_id, code, date))
            
            # 변경 사항 커밋
            db.commit()

            # 4. 핫 종목을 journal_feature 테이블에 저장
            for stock in hot_stocks:
                code = stock[0]
                event_id = stock[1]
                date = stock[2]
                close_rate = stock[3]
                trade_amount = stock[4]
                # trade_amount를 천 단위 콤마로 포맷
                formatted_trade_amount = f"{trade_amount:,.0f}"
                group_name = stock[5].decode('utf-8')

                # hot_stock_count를 확인하여 로직 처리
                check_hot_stock_count_query = """
                    SELECT hot_stock_count
                    FROM market_event_stocks
                    WHERE event_id = %s AND code = %s AND date = %s
                """
                cursor.execute(check_hot_stock_count_query, (event_id, code, date))
                hot_stock_count = cursor.fetchone()[0]

                # 새로운 comment 생성
                new_comment = f"<strong>{date} ({hot_stock_count}) / {group_name} / {close_rate}% / {formatted_trade_amount}억</strong>"


                if hot_stock_count == 1:
                    # hot_stock_count가 1인 경우, 새로운 데이터 그대로 삽입
                    insert_query = """
                        INSERT INTO journal_feature (journal_date, code, type, comment)
                        VALUES (%s, %s, 'hot', COMPRESS(%s))
                    """
                    cursor.execute(insert_query, (date, code, new_comment))
                elif hot_stock_count > 1:
                    # hot_stock_count가 2 이상인 경우 기존 comment를 가져와 합침
                    select_comment_query = """
                        SELECT UNCOMPRESS(comment) 
                        FROM journal_feature 
                        WHERE code = %s AND type = 'hot' 
                        ORDER BY journal_date DESC 
                        LIMIT 1
                    """
                    cursor.execute(select_comment_query, (code,))
                    previous_comment = cursor.fetchone()

                    if previous_comment and previous_comment[0]:
                        previous_comment = previous_comment[0].decode('utf-8')
                        combined_comment = f"{new_comment}<p>&nbsp;</p>{previous_comment}"
                    else:
                        combined_comment = new_comment

                    # 기존 데이터 삭제
                    delete_query = """
                        DELETE FROM journal_feature 
                        WHERE code = %s AND type = 'hot'
                    """
                    cursor.execute(delete_query, (code,))

                    # 새로운 데이터 삽입
                    insert_query = """
                        INSERT INTO journal_feature (journal_date, code, type, comment)
                        VALUES (%s, %s, 'hot', COMPRESS(%s))
                    """
                    cursor.execute(insert_query, (date, code, combined_comment))

            # 변경 사항 커밋
            db.commit()
            print(f"총 {len(hot_stocks)}개의 핫 종목이 업데이트되었습니다.")
    
    except Exception as e:
        print(f"오류 발생: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    start_date = '2024-08-12'
    end_date = '2024-10-28'  # 종료 날짜를 설정합니다
    update_hot_stocks(start_date, end_date)
