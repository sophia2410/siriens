import os
import re

# 경로 설정
strategy_a_dir = r"D:\Obsidian\Trader Sophia\♠ Daily Market\futures\전략A"
strategy_b_dir = r"D:\Obsidian\Trader Sophia\♠ Daily Market\futures\전략B"

# 1. 전략A의 모든 날짜별 이미지 경로 수집
image_map = {}

for filename in os.listdir(strategy_a_dir):
    if filename.endswith(".md"):
        with open(os.path.join(strategy_a_dir, filename), 'r', encoding='utf-8') as f:
            content = f.read()
            # 날짜별로 구분
            sections = re.split(r'\n(## .+?)\n', content)
            for i in range(1, len(sections), 2):
                date_line = sections[i].strip()
                date_match = re.search(r'## (\d{4}-\d{2}-\d{2})', date_line)
                if date_match:
                    date = date_match.group(1)
                    # 이미지 경로 찾기
                    image_match = re.search(r'!\[\[(.*?)\]\]', sections[i+1])
                    if image_match:
                        image_map[date] = image_match.group(1)

# 2. 전략B의 모든 파일에 이미지 삽입
for filename in os.listdir(strategy_b_dir):
    if filename.endswith(".md"):
        filepath = os.path.join(strategy_b_dir, filename)
        with open(filepath, 'r', encoding='utf-8') as f:
            lines = f.readlines()

        new_lines = []
        i = 0
        while i < len(lines):
            line = lines[i]
            new_lines.append(line)
            date_match = re.search(r'## (\d{4}-\d{2}-\d{2})', line)
            if date_match:
                date = date_match.group(1)
                # 다음 줄부터 이미지가 있는지 확인
                has_image = False
                j = i + 1
                while j < len(lines):
                    next_line = lines[j].strip()
                    if next_line.startswith('## '):  # 다음 날짜로 넘어가면 중단
                        break
                    if re.match(r'!\[\[.*\]\]', next_line):  # 이미지 발견
                        has_image = True
                        break
                    j += 1
                # 이미지가 없고, image_map에 있으면 삽입
                if date in image_map and not has_image:
                    new_lines.append('\n')  # 개행 추가
                    new_lines.append(f'![[{image_map[date]}]]\n')
                    new_lines.append('\n')  # 개행 추가
            i += 1

        # 파일 덮어쓰기
        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)

print("✅ 이미지 삽입 완료!")
