import re
from datetime import datetime
from collections import Counter

# URL 패턴을 찾는 함수
def find_url_pattern():
    return re.compile(r'http[s]?://(?:[a-zA-Z]|[0-9]|[$-_@.&+]|[!*\\(\\),]|(?:%[0-9a-fA-F][0-9a-fA-F]))+')

def remove_angle_brackets(line):
    pattern = r'^<([^>]*)>'
    match = re.match(pattern, line)
    if match:
        return match.group(1).strip()
    else:
        return line

def convert_links(text):
    pattern = r'\[(.*?)\]\((.*?)\)'
    matches = re.findall(pattern, text)
    if matches:
        combined_text = ''.join([m[0] if m[0] else ' ' for m in matches])
        last_link = matches[-1][1]
        converted_text = f'{combined_text}'
        
        # URL 추출
        urls = [m[1] for m in matches]

        return '['+converted_text+']('+urls[0]+')'
    else:
        return text

def remove_trailing_spaces_and_blank_lines_with_spaces(text):
    lines = text.split('\n')  # 줄 단위로 분할
    cleaned_lines = [line.rstrip() for line in lines if line.strip() or not line]  # 뒷쪽 공백 제거 및 공백만 있는 줄 제거
    cleaned_text = '\n'.join(cleaned_lines)  # 다시 텍스트로 결합
    return cleaned_text

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

file_path = 'D:/Obsidian/Trader Sophia/99 Inbox/rawfile_evernote.md'  # 파일 경로를 지정합니다.
words_path = 'E:/Project/202410/www/PyObsidian/stock_list.txt'  # 단어 목록 파일 경로를 지정합니다.

# 단어 목록 파일을 읽습니다.
with open(words_path, 'r', encoding='utf-8') as f:
    words = [line.strip() for line in f]

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.readlines()

# 파일의 내용에서 첫 행을 찾습니다.
first_line = next(line for line in content if line.strip())

# 첫 행에서 날짜와 제목을 추출합니다.
date, _, title = first_line.partition('Signal Report')
date = date.strip()
title = title.strip('"')



# 새로운 파일명을 생성합니다.
new_file_name = f'@Signal Report {date}.md'

# 제목을 추가하고, 첫 행을 제거합니다.
index = content.index(first_line)
content[index] = f'Title:: {title}\n'  # 첫 행을 제목으로 대체합니다.

# 한 줄을 비우고 '@nomad'를 추가합니다.
content.insert(index + 1, '@nomad')

# 문자열 변경 작업을 수행합니다.
content = [re.sub(r'\*\*', '', line) for line in content]  # "**" 제거
content = [re.sub(r'#', '', line) for line in content]  # "#" 제거
content = [re.sub(r'(\|\s*)+$', '\n', line) for line in content]  # 문자열 끝에 '|   '가 반복되는 경우를 모두 제거
content = [re.sub(r'(\|---\s*)+', '', line) for line in content]  # '|---'가 반복되는 경우를 모두 제거
content = [re.sub(r'(--->\s*)+', '', line) for line in content]  # '---> '가 반복되는 경우를 모두 제거
content = [re.sub(r'(\|\s*)+', ' ', line) for line in content]  # '|'가 반복되는 경우를 모두 제거
content = [re.sub(r'^\|', '', line) for line in content]  # 각 줄의 첫 번째 '|' 제거
content = [re.sub(r'^>', '', line, flags=re.MULTILINE) for line in content]  # 각 줄의 첫 번째 '>' 문자 제거
content = [re.sub(r'<br><br>-', '\n-', line) for line in content]  # "<br><br>" '\n' 로 변경
content = [re.sub(r'<br><br>', '', line) for line in content]  # "<br><br>" 제거
content = [re.sub(r'<br>\s*<br>', '\n', line) for line in content]  # 각 줄의 첫 번째 "<br>" 제거
content = [re.sub(r'nomad:', '> nomad : ', line, flags=re.MULTILINE) for line in content]  # 첫 번째 'nomad' '> nomad' 로 변경
# content = [remove_angle_brackets(line) for line in content]  # 각 줄의 첫 번째 '<단어>' 제거  # Obsidian_ConvertPDFSignalReport 실행시만

# 전체 내용을 하나의 문자열로 합칩니다.
content_str = ''.join(content)
content_str = content_str.replace('\u200B', '') #'zero-width space’ 특수문자 제거
content_str = content_str.replace('\r\n', '') #\r\n (즉, 0D0A) 문자를 찾아내고, 이를 빈 문자열로 대체
content_str = content_str.strip('\n') # 문서 시작과 끝에 있는 빈 줄을 제거합니다.
content_str = re.sub(r'\n\n\n\n', '\n\n', content_str) # 연속된 빈 줄을 하나의 빈 줄로 변경합니다.
content_str = re.sub(r'\n\n\n', '\n\n', content_str) # 연속된 빈 줄을 하나의 빈 줄로 변경합니다.

# 다시 리스트로 변환합니다.
content = content_str.splitlines(True)

# 제목 딕셔너리 데이터로 변경 작업
# 제목 딕셔너리
title_dict = {
    '@nomad': '\n# @nomad\n\n',
    '[시장 정리]': '\n---\n# 시장 정리\n',
    '주요 뉴스': '\n---\n# 주요 뉴스\n',
    '[없다가 생긴 것] 새로운 이슈':'## [없다가 생긴 것] 새로운 이슈\n',
    '[주요이슈] 지속되는 테마':'## [주요이슈] 지속되는 테마\n',
    '< 경제 일반 >': '\n---\n# 뉴스\n\n## < 경제 일반 >\n',
    '< 경제/인구 구조 변화 >': '---\n## < 경제/인구 구조 변화 >\n',
    '< 부동산 >': '---\n## < 부동산 >\n',
    '< 국제 - 미중패권전쟁 >': '---\n## < 국제 - 미중패권전쟁 >\n',
    '< 국제 - 미국 >': '---\n## < 국제 - 미국 >\n',
    '< 국제 - 유럽 >': '---\n## < 국제 - 유럽 >\n',
    '< 국제 - 중국 >': '---\n## < 국제 - 중국 >\n',
    '< 국제 - 그외 >'   : '---\n## < 국제 - 그외 >\n',
    '< 원자재 >': '---\n## < 원자재 >\n',
    '< 국방 >': '---\n## < 국방 >\n',
    '< 대북 경제 협력 >': '---\n## < 대북 경제 협력 >\n',
    '< 정부정책 >': '---\n## < 정부정책 >\n',
    '< CO2 / 신재생 >': '---\n## < CO2 / 신재생 >\n',
    '< CO2 /  신재생 >': '---\n## < CO2 / 신재생 >\n',
    '< 미래차 >': '---\n## < 미래차 >\n',
    '< 우주 항공 >': '---\n## < 우주 항공 >\n',
    '< 삼성전자 / 반도체 >': '---\n## < 삼성전자 / 반도체 >\n',
    '< AI / 로봇 >': '---\n## < AI / 로봇 >\n',
    '< IT / 기술 >': '---\n## < IT / 기술 >\n',
    '< 가상 자산 / 가상 현실 >': '---\n## < 가상 자산 / 가상 현실 >\n',
    '< IP / 엔터 >': '---\n## < IP / 엔터 >\n',
    '< 코로나19 >':'---\n## < 코로나19 >',
    '< BIO >': '---\n## < BIO >\n',
    '< 정치 >': '---\n## < 정치 >\n',
    '< M&A / 주요 공시 >': '---\n## < M&A / 주요 공시 >\n',
    '< 기타 >': '---\n## < 기타 >\n'
}

# 각 줄을 읽으면서 해당 줄이 딕셔너리의 키에 해당하는지 확인하고, 만약 그렇다면 그 줄을 해당 키의 값으로 대체합니다.
content = [title_dict.get(line.strip(), line) for line in content]

# 각 단어에 대해, 해당 단어를 대괄호로 둘러싼 문자열로 대체합니다.
# URL 패턴을 찾는 정규표현식 -- 함수로 변경
# url_pattern = re.compile(r'http[s]?://(?:[a-zA-Z]|[0-9]|[$-_@.&+]|[!*\\(\\),]|(?:%[0-9a-fA-F][0-9a-fA-F]))+')

# '주요 뉴스'와 '경제 일반' 사이의 내용만 변경하려면, 해당 섹션의 시작과 끝을 표시하는 플래그를 설정합니다.
in_section = False
for word in words:
    new_content = []
    for line in content:
        stripped_line = line.strip()

        # 각 줄에서 '시장 정리'와 '경제 일반'이 포함되는지 확인합니다.
        if '주요 뉴스' in line:
            in_section = True
        elif '경제 일반' in line:
            in_section = False

        # 섹션 내부에서만 단어를 대체합니다.
        if in_section:
            # 각 줄에서 URL을 찾습니다.
            pattern = find_url_pattern()
            urls = pattern.findall(line)
            # URL이 없는 경우에만 단어를 대체합니다.
            if not urls:
                # line = re.sub(r'(?<![가-힣])' + re.escape(word) + r'(?![가-힣])', f'[[{word}]]', line)
                line = re.sub(r'(?<![가-힣a-zA-Z])' + re.escape(word) + r'(?![가-힣a-zA-Z])', f'[[{word}]]', line)
        new_content.append(line)
    content = new_content


# 연속된 링크를 하나의 링크로 합치는 로직을 추가합니다.
# content = [convert_links(line) for line in content] # Obsidian_ConvertPDFSignalReport 실행시만

# 변경된 내용을 새로운 파일에 씁니다.
new_file_path = f'D:/Obsidian/Trader Sophia/99 Inbox/{new_file_name}'  # 새 파일 경로를 지정합니다.
with open(new_file_path, 'w', encoding='utf-8') as f:
    f.write(remove_trailing_spaces_and_blank_lines_with_spaces(''.join(content)))

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")