import os
import re
from collections import defaultdict
from datetime import datetime

# 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
capture_dir = os.path.join(base_dir, "chart-captures")
monthly_dir = os.path.join(base_dir, "chart-monthly")
os.makedirs(monthly_dir, exist_ok=True)

# 날짜별 항목 정리: { 'YYYY-MM': [(date, strategy, filename), ...] }
month_map = defaultdict(list)

# 파일 탐색
for root, _, files in os.walk(capture_dir):
    for fname in files:
        if not fname.endswith(".png"):
            continue
        match = re.match(r"(\d{4}-\d{2}-\d{2}) - (.+?)\.png", fname)
        if not match:
            continue
        date_str, strategy = match.groups()
        year_month = date_str[:7]
        month_map[year_month].append((date_str, strategy, fname))

# 월별 마크다운 파일 생성
for ym in sorted(month_map):
    entries = sorted(month_map[ym])
    md_path = os.path.join(monthly_dir, f"{ym}.md")
    with open(md_path, "w", encoding="utf-8") as f:
        f.write(f"# 📅 {ym} 차트 모음")
        f.write('\n')
        for date_str, strategy, fname in entries:
            year = date_str[:4]
            f.write(f"## {date_str}")
            f.write('\n')
            f.write(f"#### {strategy}")
            f.write('\n')
            f.write(f"![[chart-captures/{year}/{fname}]]")
            f.write('\n\n')

print("✅ 월별 마크다운 파일 생성 완료")
