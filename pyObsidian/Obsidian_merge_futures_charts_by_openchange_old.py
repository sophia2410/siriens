

import os
import re

# 경로 설정
strategy_a_dir = r"D:\Obsidian\Trader Sophia\♠ Daily Market\futures\전략C - close&sma5"
strategy_b_dir = r"D:\Obsidian\Trader Sophia\♠ Daily Market\futures\시가등락률별차트"

# 1. 전략C의 모든 날짜별 이미지 경로 + 원본파일명 수집
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
                    image_match = re.search(r'!\[\[(.*?)\]\]', sections[i + 1])
                    if image_match:
                        image_path = image_match.group(1)
                        image_map[date] = (image_path, filename)

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
                    image_path, source_file = image_map[date]
                    source_title = os.path.splitext(source_file)[0]
                    if '-' in source_title:
                        source_title = source_title.split('-')[-1].strip()

                    # 기존 내용 중 제거 대상 제외하고 따로 저장
                    j = i + 1
                    buffer = []  # 유지할 줄들
                    while j < len(lines):
                        next_line = lines[j].strip()
                        # 다음 날짜 제목(## yyyy-mm-dd)이 오면 중단
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
                    # 개행 조절
                    if new_lines and new_lines[-1].strip() != "":
                        new_lines.append('\n')
                    # 새로운 타이틀과 이미지 삽입
                    new_lines.append(f'#### {source_title}\n')
                    new_lines.append(f'![[{image_path}]]\n')
                    new_lines.append('\n')
                    # 유지할 줄들 추가
                    new_lines.extend(buffer)
                    i = j - 1  # 위치 조정
                else:
                    new_lines.append(line)  # 이미지 없으면 날짜만 추가
            else:
                new_lines.append(line)  # 일반 줄
            i += 1

        # 파일 덮어쓰기
        with open(filepath, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)

print("✅ 기존 이미지/타이틀 제거 후 정확한 위치에 새로 삽입 완료!")
