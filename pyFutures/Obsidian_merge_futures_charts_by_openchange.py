import os
import re

# 경로 설정
capture_dir = r"D:/Obsidian/Trader Sophia/☆ Futures/chart-captures"
strategy_b_dir = r"D:/Obsidian/Trader Sophia/☆ Futures/chart-OpenChange"

# 1. chart-captures에서 날짜별 이미지 + 전략명 수집
image_map = {}

for root, _, files in os.walk(capture_dir):
    for fname in files:
        if not fname.endswith(".png"):
            continue
        match = re.match(r"(\d{4}-\d{2}-\d{2}) - (.+?)\.png", fname)
        if match:
            date, strategy = match.groups()
            relative_path = os.path.relpath(os.path.join(root, fname), start=strategy_b_dir)
            image_map[date] = (relative_path.replace('\\', '/'), strategy)

# 2. 전략B의 모든 파일을 처리
for filename in os.listdir(strategy_b_dir):
    if filename.endswith(".md"):
        filepath = os.path.join(strategy_b_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            lines = f.readlines()

        new_lines = []
        i = 0
        while i < len(lines):
            line = lines[i]
            date_match = re.search(r'## (\d{4}-\d{2}-\d{2})', line)
            if date_match:
                date = date_match.group(1)

                if date in image_map:
                    image_path, strategy = image_map[date]

                    # 기존 내용 중 제거 대상 제외하고 따로 저장
                    j = i + 1
                    buffer = []  # 유지할 줄들
                    while j < len(lines):
                        next_line = lines[j].strip()
                        if re.match(r'## \d{4}-\d{2}-\d{2}', next_line):
                            break
                        if (
                            next_line == '' or
                            re.match(r'#### .*', next_line) or
                            re.match(r'!\[\[.*\]\]', next_line)
                        ):
                            j += 1  # 제거 대상은 skip
                        else:
                            buffer.append(lines[j])
                            j += 1

                    # 날짜 제목 추가
                    new_lines.append(line)
                    if new_lines and new_lines[-1].strip() != "":
                        new_lines.append('\n')
                    new_lines.append(f'#### {strategy}\n')
                    new_lines.append(f'![[{image_path}]]\n')
                    new_lines.append('\n')
                    new_lines.extend(buffer)
                    i = j - 1  # 위치 조정
                else:
                    new_lines.append(line)
            else:
                new_lines.append(line)
            i += 1

        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)

print("✅ chart-captures 이미지 기반으로 전략B 파일 업데이트 완료!")