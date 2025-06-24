from playwright.sync_api import sync_playwright
import pymysql
import os
import time
import configparser
import re

# 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
capture_dir = os.path.join(base_dir, "chart-captures")
backup_md_path = os.path.join(capture_dir, "image_filename_backup.md")
# viewport_size = {"width": 2106, "height": 1150}
viewport_size = {"width": 2540, "height": 1230}

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
cursor.execute("SELECT date FROM calendar WHERE date between '2023-12-14' AND '2025-05-19' ORDER BY date")
cursor.execute("SELECT date FROM calendar WHERE date between '2023-12-14' AND '2025-01-31' ORDER BY date")
dates = [row[0].strftime('%Y-%m-%d') for row in cursor.fetchall()]
conn.close()

# 1. Markdown 백업 파일 읽기
date_to_filename = {}

if os.path.exists(backup_md_path):
    with open(backup_md_path, "r", encoding="utf-8") as f:
        lines = f.readlines()
    current_date = None
    for line in lines:
        line = line.strip()
        date_match = re.match(r"## (\d{4}-\d{2}-\d{2})", line)
        if date_match:
            current_date = date_match.group(1)
        elif current_date and line.startswith("- "):
            filename = line[2:]
            date_to_filename[current_date] = filename
else:
    print("⚠️ 백업 .md 파일이 존재하지 않습니다.")
    exit(1)

# 2. 캡처 실행
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page(viewport=viewport_size)

    for date in dates:
        url = f"http://localhost/modules/futures/futures_chart.php?date={date}"
        print(f"▶ 캡처 중: {url}")
        page.goto(url)

        try:
            page.wait_for_selector("#chart-5m", timeout=15000)
            time.sleep(3)

            filename = date_to_filename.get(date)
            if not filename:
                filename = f"{date} - 미분류.png"

            year = date[:4]
            save_dir = os.path.join(capture_dir, year)
            os.makedirs(save_dir, exist_ok=True)
            screenshot_path = os.path.join(save_dir, filename)

            page.screenshot(path=screenshot_path, full_page=False)
            print(f"✅ 저장 완료: {screenshot_path}")
        except Exception as e:
            print(f"❌ 오류 ({date}):", e)

    browser.close()

print("✅ 캡처 완료 (백업 파일명 기반)")
