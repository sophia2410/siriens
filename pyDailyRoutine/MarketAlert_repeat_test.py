# 기 발생 데이터로 테스트 하기 24.03.15
# 특정금액, 건수 이상 데이터 전송

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
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# 텔레그램 설정
bot_token = config.get('telegram', 'token', fallback='YOUR_FALLBACK_TOKEN')
chat_id = config.get('telegram', 'chat_id', fallback='YOUR_FALLBACK_CHAT_ID')
bot = Bot(token=bot_token)

# 비동기 함수 정의
async def send_alert(bot, chat_id, message):
    await bot.send_message(chat_id=chat_id, text=message)
    
async def test_alerts():
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
                # 테스트하고자 하는 데이터의 시간 범위를 결정합니다.
                # 예: '20240101'의 데이터를 시간 순으로 검사하고 싶은 경우
                test_date = '20240328'

                # 시간 순으로 데이터 조회
                cursor.execute('''
                SELECT DISTINCT minute FROM kiwoom_realtime_minute
                WHERE date = %s
                AND minute >= '0902'
                ORDER BY minute ASC;
                ''', (test_date,))
                test_results = cursor.fetchall()

                # 시뮬레이션 실행
                for test_result in test_results:
                    test_datetime = test_date + test_result['minute'].decode('utf-8')
                    print(f" ## {test_datetime} ############################")

                    cursor.execute('''
                    SELECT 
                        w.theme, s.code, s.name, last_min, minute_cnt,
                        ROUND(volume_sign_last_min  * amount_last_min / 100,0) AS amount_last_min,
                        ROUND(volume_sign_last_1min * amount_last_1min / 100,0) AS amount_last_1min,
                        ROUND(amount_acc_day/100,0) amount_acc_day,
                        rate
                    FROM (
                        SELECT
                            m.code,
                            t.last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_min   THEN minute_cnt ELSE NULL END), 0) minute_cnt,
                            IFNULL(MAX(CASE WHEN minute = t.last_min   THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_1min  THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_1min,
                            IFNULL(MAX(CASE WHEN minute = t.last_min   THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_1min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_1min  THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_2min  THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_1min,
                            IFNULL(MAX(CASE WHEN minute <= t.last_min THEN acc_trade_amount ELSE NULL END), 0) AS amount_acc_day,
                            (
                            SELECT m2.rate
                            FROM kiwoom_realtime_minute m2
                            WHERE m2.code = m.code AND 
                                STR_TO_DATE(CONCAT(m2.date, m2.minute), '%%Y%%m%%d%%H%%i') <= t.specific_datetime
                            ORDER BY STR_TO_DATE(CONCAT(m2.date, m2.minute), '%%Y%%m%%d%%H%%i') DESC
                            LIMIT 1
                            ) AS rate -- 주어진 시간 이전의 가장 최근 rate
                        FROM
                            kiwoom_realtime_minute m
                        JOIN (
                            SELECT
                                specific_datetime,
                                DATE_FORMAT(sd.specific_datetime, '%%H%%i') AS last_min,
                                DATE_FORMAT(sd.specific_datetime - INTERVAL 1  MINUTE, '%%H%%i') AS last_1min,
                                DATE_FORMAT(sd.specific_datetime - INTERVAL 2  MINUTE, '%%H%%i') AS last_2min
                            FROM
                                (SELECT STR_TO_DATE(%s, '%%Y%%m%%d%%H%%i') AS specific_datetime) sd
                            ) t
                        WHERE
                            m.date = DATE_FORMAT(t.specific_datetime, '%%Y%%m%%d') AND -- Only considering today's data
                            m.minute <= DATE_FORMAT(t.specific_datetime, '%%H%%i')
                        GROUP BY
                            m.code
                        ) g
                        JOIN
                            kiwoom_stock s
                        ON
                            s.code = g.code
                        JOIN
                            (SELECT code, MIN(theme) theme FROM watchlist_sophia WHERE realtime_yn = 'Y' or sector in( '5 끼있는친구들1', '6 끼있는친구들2') GROUP BY code) w
                        ON
                            w.code = g.code
                        LEFT OUTER JOIN 
                            (SELECT DISTINCT code FROM telegram_message_history WHERE date = %s) h
                        ON 
                            h.code = g.code
                        WHERE
                            g.amount_acc_day > 2000 AND
                            minute_cnt > 5 AND
                            amount_last_min > 500 AND
                            ROUND(volume_sign_last_min  * amount_last_min /100,0) > 0 AND 
                            ROUND(volume_sign_last_1min * amount_last_1min /100,0) >= 0 AND
                            h.code is null
                        ORDER BY theme, amount_acc_day DESC, rate DESC;
                    ''', (test_datetime,test_date,))
                    results = cursor.fetchall()
                    
                    for result in results:
                        date  = test_date
                        minute= result['last_min'].decode('utf-8')
                        theme = result['theme'].decode('utf-8')
                        code  = result['code'].decode('utf-8')
                        name  = result['name'].decode('utf-8')
                        rate  = result['rate']
                        minute_cnt  = result['minute_cnt']
                        acc_amount  = result['amount_acc_day']
                        amount_last_min = result['amount_last_min']

                        h = minute[:2]
                        m = minute[2:]
                        message_minute = f'{h}:{m}'
                        
                        message = f"{message_minute} [{theme}] {name} {rate} % 누적 {acc_amount} 억, 최근 {amount_last_min} 억, 건수 {minute_cnt} 건"
                        print(f"알림 전송: {message}")
                        await send_alert(bot, chat_id, message)
                        
                        # 메시지 이력 등록
                        cursor.execute('''
                        INSERT INTO telegram_message_history (date, minute, code, name, message, first_alert, message_fg)
                        VALUES (%s, %s, %s, %s, %s, %s, %s);
                        ''', (date, minute, code, name, message, 'Y', '1'))
                        db.commit()
                    
        finally:
            db.close()

# 비동기 실행을 위한 메인 함수
async def main():
    await test_alerts()

# 비동기 실행
if __name__ == "__main__":
    asyncio.run(main())