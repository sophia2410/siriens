import schedule
import time
import subprocess
from datetime import datetime, timedelta
import logging

# 로깅 설정
logging.basicConfig(
    # filename='scheduler.log',
    level=logging.INFO,
    format='%(asctime)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)

# 크롤링 작업 함수
def job():
    logging.info("크롤링 작업 시작")
    try:
        # 크롤링 스크립트를 호출
        result = subprocess.run([r"C:\Users\elf96\AppData\Local\Programs\Python\Python39\python.exe", r"E:\Project\202410\www\pyDailyRoutine\DBUp_NaverFinance.py"], capture_output=True, text=True)
        logging.info(f"크롤링 작업 완료: {result.stdout}")
    except Exception as e:
        logging.error(f"크롤링 작업 중 오류 발생: {e}")

# 작업 시작 스케줄 설정 (매일 9시 3분에 시작)
schedule.every().day.at("09:00").do(lambda: schedule.every(3).minutes.do(job))

# 작업 중지 스케줄 설정 (매일 3시 35분에 종료)
def stop_job():
    logging.info("크롤링 작업 중지")
    schedule.clear(job)

schedule.every().day.at("15:35").do(stop_job)

logging.info("스케줄러 시작. 9시 3분 시작, 3시 35분에 종료. 3분간격")

while True:
    schedule.run_pending()
    time.sleep(1)
