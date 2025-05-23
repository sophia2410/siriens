# 일자별-패턴.png 에서 패턴 변경 처리 이후..
# - 파일명 백업 / chart-OpenChange, chart-pettern, chart-monthly 폴더 파일 업데이트

import os
import re
import subprocess

# 설정
capture_dir = r"D:/Obsidian/Trader Sophia/☆ Futures/chart-captures"
backup_json_path = os.path.join(capture_dir, "image_filename_backup.json")
backup_md_path = os.path.join(capture_dir, "image_filename_backup.md")

# 백업 생성용 딕셔너리 초기화
date_to_filename = {}

# chart-captures 내 연도별 폴더 순회하여 이미지 파일명 백업 생성
for root, _, files in os.walk(capture_dir):
    for fname in files:
        if not fname.endswith(".png"):
            continue
        match = re.match(r"(\d{4}-\d{2}-\d{2}) - (.+?)\.png", fname)
        if match:
            date, strategy = match.groups()
            date_to_filename[date] = f"{date} - {strategy}.png"


# Markdown으로 저장
with open(backup_md_path, "w", encoding="utf-8") as f:
    f.write("# 📦 이미지 파일명 백업 (.md 버전)\n\n")
    for date in sorted(date_to_filename, reverse=True):
        filename = date_to_filename[date]
        f.write(f"## {date}\n")
        f.write(f"- {filename}\n\n")

print("✅ image_filename_backup.md 생성 완료")


# 현재 파이썬 파일이 있는 디렉토리 기준으로 이미지 스크립트 경로 설정
current_dir = os.path.dirname(os.path.abspath(__file__))

# By Monthly
merge_script_by_monthly = os.path.join(current_dir, "Obsidian_merge_futures_charts_by_monthly.py")

if os.path.exists(merge_script_by_monthly):
    print("📦 By Monthly 이미지 변경 스크립트 실행 중...")
    subprocess.run(["python", merge_script_by_monthly], check=True)
else:
    print("⚠️ 백업 스크립트가 존재하지 않습니다:", merge_script_by_monthly)


# By Openchange
merge_script_by_openchange = os.path.join(current_dir, "Obsidian_merge_futures_charts_by_openchange.py")

if os.path.exists(merge_script_by_openchange):
    print("📦 By Monthly 이미지 변경 스크립트 실행 중...")
    subprocess.run(["python", merge_script_by_openchange], check=True)
else:
    print("⚠️ 백업 스크립트가 존재하지 않습니다:", merge_script_by_openchange)


# By Pattern
merge_script_by_pattern = os.path.join(current_dir, "Obsidian_merge_futures_charts_by_pattern.py")

if os.path.exists(merge_script_by_pattern):
    print("📦 By Monthly 이미지 변경 스크립트 실행 중...")
    subprocess.run(["python", merge_script_by_pattern], check=True)
else:
    print("⚠️ 백업 스크립트가 존재하지 않습니다:", merge_script_by_pattern)