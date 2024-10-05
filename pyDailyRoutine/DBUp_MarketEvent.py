import pandas as pd
import pymysql
import configparser

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

# Excel 파일 읽기
excel_file = 'E:/Project/202410/data/_MarketEvent/market_event.xlsx'
df = pd.read_excel(excel_file)

# NaN 값을 빈 문자열로 변환
df = df.fillna('')

# 마지막으로 유효한 값을 기억하는 변수를 초기화
last_date = None
last_event_id = None

# 키워드 처리 함수
def handle_keywords(cursor, keywords):
    if not keywords:
        return None

    # print(f"Generated group_name: {keywords}")
    keyword_list = list(set(keywords.split('#')))
    keyword_ids = []

    for keyword in keyword_list:
        keyword = keyword.strip()
        if not keyword:
            continue

        cursor.execute("SELECT keyword_id FROM keyword WHERE keyword = %s", (keyword,))
        result = cursor.fetchone()
        if result:
            keyword_ids.append(result[0])
        else:
            cursor.execute("INSERT INTO keyword (keyword) VALUES (%s)", (keyword,))
            keyword_ids.append(cursor.lastrowid)
    
    keyword_ids.sort()
    keyword_ids_str = ','.join(map(str, keyword_ids))

    cursor.execute("""
        SELECT group_id 
        FROM (
            SELECT group_id, GROUP_CONCAT(keyword_id ORDER BY keyword_id ASC) as ids
            FROM keyword_group_mappings
            GROUP BY group_id
        ) AS sub
        WHERE ids = %s
    """, (keyword_ids_str,))
    result = cursor.fetchone()
    if result:
        return result[0]
    else:
        # group_name = ' '.join([f"#{kw.strip('#')}" for kw in keyword_list])
        group_name = keywords

        cursor.execute("INSERT INTO keyword_groups (group_name) VALUES (%s)", (group_name,))
        group_id = cursor.lastrowid

        for keyword_id in keyword_ids:
            cursor.execute("INSERT INTO keyword_group_mappings (group_id, keyword_id, create_dtime) VALUES (%s, %s, NOW())", (group_id, keyword_id))
        
        # print(f"Created new keyword group: {group_name} with ID: {group_id}")
        return group_id

# 이슈 삽입 및 업데이트 함수 (섹터 제외)
def insert_or_update_event(cursor, date, issue, first_occurrence, link, theme, hot_theme, group_id):
    # print(f"Inserting/updating issue: {issue} for date: {date}, theme: {theme}")

    cursor.execute("""
        SELECT event_id FROM market_events 
        WHERE date = %s AND keyword_group_id = %s AND issue = %s AND theme = %s
    """, (date, group_id, issue, theme))
    result = cursor.fetchone()
    
    if result:
        # print(f"Existing event found with ID: {result[0]}")
        return result[0]
    else:
        cursor.execute("""
            INSERT INTO market_events (date, issue, first_occurrence, link, theme, hot_theme, keyword_group_id, status, create_dtime) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, 'registered', NOW())
        """, (date, issue, first_occurrence, link, theme, hot_theme, group_id))
        event_id = cursor.lastrowid
        # print(f"Inserted new event with ID: {event_id}")
        return event_id

# 주식 데이터 처리 함수 (섹터 추가)
def handle_stocks(cursor, event_id, stocks, date):
    # print(f"Handling stocks for event ID: {event_id} on date: {date}")
    for stock in stocks:
        name = stock['name']
        stock_comment = stock['comment']
        is_leader = '1' if stock.get('is_leader') else '0'
        is_watchlist = '1' if stock.get('is_watchlist') else '0'

        # 주식 코드를 stock 테이블에서 가져옴
        cursor.execute("SELECT code FROM stock WHERE name = %s AND last_yn = 'Y'", (name,))
        result = cursor.fetchone()
        if not result:
            print(f"Stock code not found for name: {name}")
            continue

        code = result[0].decode('utf-8')

        cursor.execute("SELECT high_rate, close_rate, volume, amount FROM v_daily_price WHERE code = %s AND date = %s", (code, date))
        price_data = cursor.fetchone()
        high_rate, close_rate, volume, trade_amount = price_data if price_data else (None, None, None, None)

        # 쿼리 및 파라미터를 출력하여 디버깅
        query = """
            INSERT INTO market_event_stocks 
            (event_id, code, name, high_rate, close_rate, volume, trade_amount, stock_comment, is_leader, is_watchlist, date, create_dtime) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
        """
        params = (event_id, code, name, high_rate, close_rate, volume, trade_amount, stock_comment, is_leader, is_watchlist, date)
        # print(f"Executing query: {query} with params: {params}")
        
        try:
            cursor.execute(query, params)
            # print(f"Inserted stock: {name} ({code}) with comment: {stock_comment}")
        except pymysql.MySQLError as e:
            print(f"Error inserting stock: {name} ({code}) with error: {e}")
            raise

# 트랜잭션 실행 함수
def process_events(db, df):
    cursor = db.cursor()
    db.autocommit(False)
    
    last_date = None
    last_event_id = None

    try:
        for index, row in df.iterrows():
            # 날짜가 비어있으면 이전 값을 사용
            date = row['날짜'] if row['날짜'] else last_date
            # print(f"Processing row {index + 1}, Date: {date}")

            # 키워드가 있는 경우에만 새로운 그룹으로 처리, 테마는 비어있어도 상관없음
            if row['키워드']:
                keywords = row['키워드']
                theme = row['테마']
                issue = row['이슈']
                first_occurrence = 'Y' if row['신규이슈'] == 1 else 'N'
                hot_theme = 'Y' if row['핫테마'] == 1 else 'N'
                
                # 새로운 그룹에 대해 event_id 생성
                group_id = handle_keywords(cursor, keywords)
                event_id = insert_or_update_event(cursor, date, issue, first_occurrence, '', theme, hot_theme, group_id)
                last_event_id = event_id
            else:
                # 이전 event_id 사용
                event_id = last_event_id
                # print(f"Using previous event ID: {event_id}")

            # 주식 데이터를 처리
            stocks = [
                {
                    'name': row.get(f'종목명'), 
                    'comment': row.get(f'종목 코멘트'), 
                    'is_leader': row.get(f'주도주여부'),
                    'is_watchlist': row.get(f'관심종목여부')  # 관심종목여부 추가
                }
            ]
            handle_stocks(cursor, event_id, stocks, date)

            # 날짜 갱신
            last_date = date

        db.commit()
    except Exception as e:
        db.rollback()
        print(f"오류 발생: {e}")
    finally:
        cursor.close()

process_events(db, df)
db.close()