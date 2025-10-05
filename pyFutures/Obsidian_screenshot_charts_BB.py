from playwright.sync_api import sync_playwright
import pymysql
import os
import time
import configparser
import re

# 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
# capture_dir = os.path.join(base_dir, "chart-captures-BB")
capture_dir = os.path.join(base_dir, "chart-captures-BB+1day")

# viewport_size = {"width": 2540, "height": 1230}
viewport_size = {"width": 2150, "height": 1210}

# DB 설정 로드
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# DB에서 거래일 가져오기
conn = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)
cursor = conn.cursor()
# cursor.execute("SELECT date FROM calendar WHERE date between '2024-01-05' AND '2025-06-26' ORDER BY date")
cursor.execute("SELECT date FROM calendar WHERE date between '2025-07-03' AND '2025-07-14' ORDER BY date")
dates = [row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()]
conn.close()

# 1. 캡처 실행
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport=viewport_size)

    for date in dates:
        url = f"http://localhost/modules/futures/futures_chart_BB.php?date={date}&interval=60"
        print(f"▶ 캡처 중: {url}")
        page.goto(url)

        try:
            page.wait_for_selector("#chart-5m", timeout=15000)
            time.sleep(3)

            filename = f"{date}.png"

            year = date[:4]
            save_dir = os.path.join(capture_dir, year)
            os.makedirs(save_dir, exist_ok=True)
            screenshot_path = os.path.join(save_dir, filename)

            page.screenshot(path=screenshot_path, full_page=False)
            print(f"✅ 저장 완료: {screenshot_path}")
        except Exception as e:
            print(f"❌ 오류 ({date}):", e)

    browser.close()

print("✅ 캡처 완료")
