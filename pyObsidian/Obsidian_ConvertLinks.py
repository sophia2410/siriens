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

file_path = 'D:/Obsidian/Trader Sophia/99 Inbox/pdf_html_for_md.md'  # 파일 경로를 지정합니다.

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.readlines()

# 제목 딕셔너리 데이터로 변경 작업
# 제목 딕셔너리
title_dict = {
    '< 경제 일반 >': '경제 일반',
    '< 경제/인구 구조 변화 >':'경제/인구 구조 변화',
    '< 부동산 >':'부동산',
    '< 국제 - 미중패권전쟁 >':'국제 - 미중패권전쟁',
    '< 국제 - 미국 >':'국제 - 미국',
    '< 국제 - 유럽 >':'국제 - 유럽',
    '< 국제 - 중국 >':'국제 - 중국',
    '< 국제 - 그외 >':'국제 - 그외',
    '< 원자재 >':'원자재',
    '< 국방 >':'국방',
    '< 대북 경제 협력 >':'대북 경제 협력',
    '< 국방 >':'정부정책',
    '< CO2 / 신재생 >':'CO2 / 신재생',
    '< 미래차 >':'미래차',
    '< 우주 항공 >':'우주 항공',
    '< 삼성전자 / 반도체 >':'삼성전자 / 반도체',
    '<AI / 로봇>':'AI / 로봇',
    '< IT / 기술 >':'IT / 기술',
    '< 가상자산 / 가상현실 >':'가상 자산 / 가상 현실',
    '< IP / 엔터 >':'IP/엔터',
    '< BIO >':'BIO',
    '< 정치 >':'정치',
    '< M&A / 주요 공시 >':'M&A / 주요 공시',
    '< 기타 >':'기타'
}

# 각 줄을 읽으면서 해당 줄이 딕셔너리의 키에 해당하는지 확인하고, 만약 그렇다면 그 줄을 해당 키의 값으로 대체합니다.
content = [title_dict.get(line.strip(), line) for line in content]


# 연속된 링크를 하나의 링크로 합치는 로직을 추가합니다.
content = [convert_links(line) for line in content]

# 변경된 내용을 새로운 파일에 씁니다.
new_file_path = f'D:/Obsidian/Trader Sophia/99 Inbox/pdf_html_for_md.md'  # 새 파일 경로를 지정합니다.
with open(new_file_path, 'w', encoding='utf-8') as f:
    f.write(remove_trailing_spaces_and_blank_lines_with_spaces(''.join(content)))

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")