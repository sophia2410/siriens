import sys
import asyncio
from telegram import Bot
import pymysql.cursors
import configparser
from datetime import datetime, timedelta
from gtts import gTTS
import os
import tempfile
import pygame

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
async def send_alert(bot, chat_id, message, parse_mode=None):
    await bot.send_message(chat_id=chat_id, text=message, parse_mode=parse_mode)

async def fetch_and_announce_alerts():
    while True:

        current_time = datetime.now()
        start_time = current_time.replace(hour=9, minute=1, second=0, microsecond=0)
        end_time = current_time.replace(hour=15, minute=31, second=0, microsecond=0)

        if start_time <= current_time <= end_time:
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
                    # 현재 시간을 기준으로 1분 전 시간 계산
                    current_time = datetime.now()
                    one_minute_ago = current_time - timedelta(minutes=1)
                    test_datetime = one_minute_ago.strftime('%Y%m%d%H%M')
                    print(f" ## {test_datetime} ############################")

                    cursor.execute('''
                    SELECT 
                        w.theme, s.code, s.name, last_min, minute_cnt,
                        ROUND(volume_sign_last_min * amount_last_min / 100, 0) AS amount_last_min,
                        ROUND(volume_sign_last_1min * amount_last_1min / 100, 0) AS amount_last_1min,
                        ROUND(amount_acc_day / 100, 0) amount_acc_day,
                        rate
                    FROM (
                        SELECT
                            m.code,
                            t.last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_min THEN minute_cnt ELSE NULL END), 0) minute_cnt,
                            IFNULL(MAX(CASE WHEN minute = t.last_min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_1min THEN CASE WHEN (minus_tick_cnt - plus_tick_cnt) > 5 THEN -1 ELSE CASE WHEN minute_volume > 0 THEN 1 ELSE -1 END END ELSE NULL END), 0) volume_sign_last_1min,
                            IFNULL(MAX(CASE WHEN minute = t.last_min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_1min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_min,
                            IFNULL(MAX(CASE WHEN minute = t.last_1min THEN acc_trade_amount ELSE NULL END) - MAX(CASE WHEN minute <= t.last_2min THEN acc_trade_amount ELSE 0 END), 0) AS amount_last_1min,
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
                                DATE_FORMAT(sd.specific_datetime - INTERVAL 1 MINUTE, '%%H%%i') AS last_1min,
                                DATE_FORMAT(sd.specific_datetime - INTERVAL 2 MINUTE, '%%H%%i') AS last_2min
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
                        WHERE
                            minute_cnt > 5 AND
                            amount_last_min > 500 AND
                            ROUND(volume_sign_last_min * amount_last_min / 100, 0) > 0 AND 
                            ROUND(volume_sign_last_1min * amount_last_1min / 100, 0) >= 0
                        ORDER BY theme, amount_acc_day DESC, rate DESC;
                    ''', (test_datetime,))
                    results = cursor.fetchall()

                    # Group results by theme
                    grouped_results = {}
                    for result in results:
                        theme = result['theme'].decode('utf-8')
                        if theme not in grouped_results:
                            grouped_results[theme] = []
                        grouped_results[theme].append(result)
                        
                    audio_messages = []

                    # Generate and send messages for each theme
                    for theme, items in grouped_results.items():
                        messages = []
                        for item in items:
                            date = current_time.strftime('%Y%m%d')
                            minute = item['last_min'].decode('utf-8')
                            code = item['code'].decode('utf-8')
                            name = item['name'].decode('utf-8')
                            rate = item['rate']
                            rounded_rate = round(rate, 0)
                            minute_cnt = item['minute_cnt']
                            acc_amount = item['amount_acc_day']
                            amount_last_min = item['amount_last_min']

                            h = minute[:2]
                            m = minute[2:]
                            message_minute = f'{h}:{m}'
    
                            # Bold the amount_last_min if it is 30억 or more
                            if amount_last_min >= 20:  # Assuming 30 is equivalent to 30억
                                name_str = f"<b>{name}</b>"
                                amount_last_min_str = f"<b>{amount_last_min}억</b>"
                            
                                audio_message = f"\n {name} {rounded_rate}% {amount_last_min}억"
                                audio_messages.append(audio_message)
                            else:
                                name_str = f"{name}"
                                amount_last_min_str = f"{amount_last_min}억"
                                
                                audio_message = ""
    
                            message = f"[{name_str}] {rate}%, {amount_last_min_str}/{acc_amount}억, {minute_cnt}건"
                            messages.append(message)
                        
                        # Join messages for the same theme
                        final_message = f"{message_minute} [{theme}]\n" + "\n".join(messages)
                        print(f"알림 전송: {final_message}")
                        await send_alert(bot, chat_id, final_message, parse_mode='HTML')

                    # Convert the message to speech
                    if len(audio_messages) > 0 :
                        final_audio_message = "".join(audio_messages)
                        tts = gTTS(text=final_audio_message, lang='ko')
                        with tempfile.NamedTemporaryFile(delete=False, suffix=".mp3") as temp_audio_file:
                            tts.save(temp_audio_file.name)
                            audio_path = temp_audio_file.name
                        
                        # Play the audio asynchronously using pygame
                        await play_audio_async(audio_path)
            finally:
                db.close()

        # 1분 대기
        await asyncio.sleep(60)

async def play_audio_async(audio_path):
    """Play audio file asynchronously using pygame."""
    try:
        pygame.mixer.init()
        pygame.mixer.music.load(audio_path)
        pygame.mixer.music.play()
        while pygame.mixer.music.get_busy():
            await asyncio.sleep(1)
    except Exception as e:
        print(f"Error playing sound: {str(e)}")
    finally:
        pygame.mixer.quit()
        os.remove(audio_path)

# 비동기 실행을 위한 메인 함수
async def main():
    await fetch_and_announce_alerts()

# 비동기 실행
if __name__ == "__main__":
    asyncio.run(main())