import os
import re
from collections import defaultdict

# 설정
base_dir = r"D:/Obsidian/Trader Sophia/☆ Futures"
capture_dir = os.path.join(base_dir, "chart-captures")
category_dir = os.path.join(base_dir, "chart-pettern")
os.makedirs(category_dir, exist_ok=True)

# 사용할 전략 패턴 키워드 (순서 중요)
patterns = [
    "골든크로스", "상승서핑", "상승웨이브",
    "데드크로스", "하락서핑", "하락웨이브",
    "횡보", "지그재그"
]

# 초기화
pattern_map = defaultdict(list)

# chart-captures 내 이미지 탐색
for root, _, files in os.walk(capture_dir):
    for fname in files:
        if not fname.endswith(".png"):
            continue
        match = re.match(r"(\d{4}-\d{2}-\d{2}) - (.+?)\.png", fname)
        if not match:
            continue
        date, strategy = match.groups()
        for pattern in patterns:
            if pattern in strategy:
                pattern_map[pattern].append((date, fname))
                break

# 마크다운 파일로 저장 (패턴 순서대로 번호를 붙임)
for idx, pattern in enumerate(patterns, start=1):
    entries = sorted(pattern_map[pattern])
    filename = f"{idx} {pattern}.md"
    md_path = os.path.join(category_dir, filename)
    with open(md_path, "w", encoding="utf-8") as f:
        f.write(f"# 📊 {pattern} 전략 차트")
        f.write('\n')
        for date, fname in entries:
            f.write(f"## {date}")
            f.write('\n')
            f.write(f"![[chart-captures/{date[:4]}/{fname}]]")
            f.write('\n')

print("✅ 번호가 포함된 전략별 마크다운 파일 생성 완료!")
