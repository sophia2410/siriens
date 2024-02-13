# 실제 사용 버전. 2024.02.08

import sys
import datetime
import asyncio
from telegram import Bot
import pymysql.cursors
import configparser

# asyncio 정책 변경 (Windows 환경에서)
if sys.platform == 'win32':
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# 텔레그램 설정
bot_token = config.get('telegram', 'token', fallback='YOUR_FALLBACK_TOKEN')
chat_id = config.get('telegram', 'chat_id', fallback='YOUR_FALLBACK_CHAT_ID')
bot = Bot(token=bot_token)

# 비동기 함수 정의
async def send_alert(bot, chat_id, message):
    await bot.send_message(chat_id=chat_id, text=message)
    
async def check_db_and_alert():
    while True:
        db = pymysql.connect(
            host=config.get('database', 'host'),
            user=config.get('database', 'user'),
            password=config.get('database', 'password'),
            db=config.get('database', 'db'),
            charset=config.get('database', 'charset'),
            cursorclass=pymysql.cursors.DictCursor
        )
        
        try:
            with db.cursor() as cursor:
                now = datetime.datetime.now()
                send_date = now.strftime('%Y%m%d')
                send_minute = now.strftime('%H%M')

                # 3분 내에 20건 이상 발생한 종목 조회
                cursor.execute('''
                SELECT r.date, s.code, s.name, MAX(r.minute) AS minute, count(*) AS total_count, SUM(r.minute_cnt) AS acc_minute_cnt,
                       CASE WHEN h.code IS NULL THEN 'Y' ELSE '' END AS first_alert
                FROM kiwoom_realtime_minute r
                INNER JOIN kiwoom_stock s 
                ON s.code = r.code
                LEFT OUTER JOIN (SELECT DISTINCT code FROM telegram_message_history WHERE date = %s) h
                ON h.code = r.code
                WHERE r.create_dtime >= NOW() - INTERVAL 3 MINUTE
                GROUP BY s.code, s.name
                HAVING acc_minute_cnt >= 20;
                ''', (send_date,))
                results = cursor.fetchall()
                
                for result in results:
                    date  = result['date'].decode('utf-8')
                    minute= result['minute'].decode('utf-8')
                    code  = result['code'].decode('utf-8')
                    name  = result['name'].decode('utf-8')
                    first_alert = result['first_alert'].decode('utf-8')

                    if first_alert :
                        message_name = '★ '+ name
                    else:
                        message_name = name

                    h = minute[:2]
                    m = minute[2:]
                    message_minute = f'{h}:{m}'
                    
                    message = f"{message_minute} [{message_name}] {result['acc_minute_cnt']} 회 (3분)"
                    print(f"알림 전송: {message}")
                    await send_alert(bot, chat_id, message)
                    
                    # 메시지 이력 등록
                    cursor.execute('''
                    INSERT INTO telegram_message_history (date, minute, code, name, message, first_alert, message_fg)
                    VALUES (%s, %s, %s, %s, %s, %s, %s);
                    ''', (date, send_minute, code, name, message, first_alert, '1'))
                    db.commit()

                # 1분에 7회 이상 거래가 발생한 종목 조회
                cursor.execute('''
                SELECT r.date, r.minute, s.code, s.name, r.minute_cnt,
                       CASE WHEN h.code IS NULL THEN 'Y' ELSE '' END AS first_alert
                FROM kiwoom_realtime_minute r
                LEFT OUTER JOIN (SELECT DISTINCT code FROM telegram_message_history WHERE date = %s) h
                ON h.code = r.code
                INNER JOIN kiwoom_stock s 
                ON s.code = r.code 
                AND s.MARKET_FG IN ('KOSPI', 'KOSDAQ') 
                WHERE r.create_dtime >= NOW() - INTERVAL 1 MINUTE
                AND r.minute_cnt >=7;
                ''', (send_date,))
                results = cursor.fetchall()
                
                for result in results:
                    date  = result['date'].decode('utf-8')
                    minute= result['minute'].decode('utf-8')
                    code  = result['code'].decode('utf-8')
                    name  = result['name'].decode('utf-8')
                    first_alert = result['first_alert'].decode('utf-8')

                    if first_alert :
                        message_name = '★ '+ name
                    else:
                        message_name = name

                    h = minute[:2]
                    m = minute[2:]
                    message_minute = f'{h}:{m}'

                    message = f"{message_minute} [{message_name}] {result['minute_cnt']} 회 (1분)"
                    print(f"알림 전송: {message}")
                    await send_alert(bot, chat_id, message)
                    
                    # 메시지 이력 등록
                    cursor.execute('''
                    INSERT INTO telegram_message_history (date, minute, code, name, message, first_alert, message_fg)
                    VALUES (%s, %s, %s, %s, %s, %s, %s);
                    ''', (date, send_minute, code, name, message, first_alert, '2'))
                    db.commit()

        except pymysql.MySQLError as e:
            # 데이터베이스 오류 처리
            print(f"Database error: {e}")
        except Exception as e:
            # 기타 예외 처리
             print(f"Other error: {e}")

        finally:
            db.close()
        
        await asyncio.sleep(60)
        
# 비동기 메인 함수
async def main():
    await check_db_and_alert()

# 비동기 실행
asyncio.run(main())