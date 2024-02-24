import re
import os
import pymysql
import configparser
from datetime import datetime

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 커서 생성
cursor = db.cursor()

def remove_trailing_spaces_and_blank_lines_with_spaces(text):
    lines = text.split('\n')  # 줄 단위로 분할
    cleaned_lines = [line.rstrip() for line in lines if line.strip() or not line]  # 뒷쪽 공백 제거 및 공백만 있는 줄 제거
    cleaned_text = '\n'.join(cleaned_lines)  # 다시 텍스트로 결합
    return cleaned_text

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")


folder_path = 'D:/Obsidian/Trader Sophia/wordtomd'  # 폴더 경로를 지정합니다.
file_names = os.listdir(folder_path)  # 폴더의 모든 파일명을 리스트로 가져옵니다.

for file_name in file_names:  # 리스트를 반복문으로 순회합니다.
    file_path = os.path.join(folder_path, file_name)  # 파일 경로를 생성합니다.
    with open(file_path, 'r', encoding='utf-8') as f:  # 파일을 열고 읽습니다.
        content = f.readlines()

    # 파일의 내용에서 첫 행을 찾습니다.
    first_line = next(line for line in content if line.strip())
    first_line = first_line.replace("#",'')

    # 첫 행에서 날짜와 제목을 추출합니다.
    date, _, title = first_line.partition('Signal Report')
    date = date.strip()
    title = title.strip('"')

    rename =  f'NoDel_OrgCopyWord {date}.md'
    rename_path = os.path.join(folder_path, rename)  # 파일 경로를 생성합니다.

    # 읽어온 파일명을 변경합니다.
    os.rename(file_path, rename_path)
    
    # 새로운 파일명을 생성합니다.
    new_file_name = f'@WordToMD {date}.md'

        

    # 문자열 변경 작업을 수행합니다.
    content = [re.sub(r'\*\*', '', line) for line in content]  # "**" 제거
    content = [re.sub(r'#', '', line) for line in content]  # "#" 제거
    content = [re.sub(r'(\|\s*)+$', '\n', line) for line in content]  # 문자열 끝에 '|   '가 반복되는 경우를 모두 제거
    content = [re.sub(r'(\|---\s*)+', '', line) for line in content]  # '|---'가 반복되는 경우를 모두 제거
    content = [re.sub(r'(--->\s*)+', '', line) for line in content]  # '---> '가 반복되는 경우를 모두 제거
    content = [re.sub(r'(\|\s*)+', ' ', line) for line in content]  # '|'가 반복되는 경우를 모두 제거
    content = [re.sub(r'(\\\s*)+', ' ', line) for line in content]  # '\'가 반복되는 경우를 모두 제거
    content = [re.sub(r'^\|', '', line) for line in content]  # 각 줄의 첫 번째 '|' 제거
    content = [re.sub(r'^>', '', line, flags=re.MULTILINE) for line in content]  # 각 줄의 첫 번째 '>' 문자 제거
    content = [re.sub(r'<br><br>-', '\n-', line) for line in content]  # "<br><br>" '\n' 로 변경
    content = [re.sub(r'<br><br>', '', line) for line in content]  # "<br><br>" 제거
    content = [re.sub(r'<br>\s*<br>', '\n', line) for line in content]  # 각 줄의 첫 번째 "<br>" 제거
    content = [re.sub(r'nomad:', '> nomad : ', line, flags=re.MULTILINE) for line in content]  # 첫 번째 'nomad' '> 


    # 전체 내용을 하나의 문자열로 합칩니다.
    content_str = ''.join(content)
    content_str = content_str.replace(r'\r\n', '\n') #\r\n (즉, 0D0A) 문자를 찾아내고, 이를 빈 문자열로 대체
    content_str = content_str.replace('\u200B', ' ') #'zero-width space’ 특수문자 제거
    content_str = content_str.replace(' ', '') # ' ' 특수문자 제거
    content_str = content_str.replace('♘', '었')
    content_str = content_str.replace('\u0D0A0D0A20200D0A0D0A', '\r\n') #'zero-width space’ 특수문자 제거

    # 이미지 파일 경로 제거
    content_str = re.sub(r'!\[\]\(file:///C:/Users/elf96/AppData/Local/Temp/msohtmlclip1/01/clip_image(0[0-9][1-9]|0[1-9][0-9]|100).gif\)', '\n', content_str)
    
    pattern = r'(\[.*?\])\((.*?)\)'
    content_str = re.sub(pattern, r'\1(\2)\n', content_str)

    # 다시 리스트로 변환합니다.
    content = content_str.splitlines(True)

    # 제목 딕셔너리 데이터로 변경 작업
    # 제목 딕셔너리
    title_dict = {
        '[시장 정리]': '\n---\n# 시장 정리\n',
        '주요 뉴스': '\n---\n# 주요 뉴스\n',
        '[없다가 생긴 것] 새로운 이슈': '## [없다가 생긴 것] 새로운 이슈\n',
        '[주요이슈] 지속되는 테마': '## [주요이슈] 지속되는 테마\n',
        '< 경제 일반 >': '\n---\n# 뉴스\n\n## < 경제 일반 >\n',
        '< 경제/인구 구조 변화 >': '## < 경제/인구 구조 변화 >\n',
        '< 부동산 >': '## < 부동산 >\n',
        '< 국제 - 미중패권전쟁 >': '## < 국제 - 미중패권전쟁 >\n',
        '< 국제 - 미국 >': '## < 국제 - 미국 >\n',
        '< 국제 - 유럽 >': '## < 국제 - 유럽 >\n',
        '< 국제 - 중국 >': '## < 국제 - 중국 >\n',
        '< 국제 - 그외 >': '## < 국제 - 그외 >\n',
        '< 원자재 >': '## < 원자재 >\n',
        '< 국방 >': '## < 국방 >\n',
        '< 대북 경제 협력 >': '## < 대북 경제 협력 >\n',
        '< 정부정책 >': '## < 정부정책 >\n',
        '< CO2 / 신재생 >': '## < CO2 / 신재생 >\n',
        '< 미래차 >': '## < 미래차 >\n',
        '< 우주 항공 >': '## < 우주 항공 >\n',
        '< 삼성전자 / 반도체 >': '## < 삼성전자 / 반도체 >\n',
        '< AI / 로봇 >': '## < AI / 로봇 >\n',
        '< IT / 기술 >': '## < IT / 기술 >\n',
        '< 가상 자산 / 가상 현실 >': '## < 가상 자산 / 가상 현실 >\n',
        '< IP / 엔터 >': '## < IP / 엔터 >\n',
        '< BIO >': '## < BIO >\n',
        '< 정치 >': '## < 정치 >\n',
        '< M&A / 주요 공시 >': '## < M&A / 주요 공시 >\n',
        '< 기타 >': '## < 기타 >\n'
    }

    # 각 줄을 읽으면서 해당 줄이 딕셔너리의 키에 해당하는지 확인하고, 만약 그렇다면 그 줄을 해당 키의 값으로 대체합니다.
    content = [title_dict.get(line.strip(), line) for line in content]

    # 유목민 코멘트 + 시장정리 데이터를 읽어와서 파일에 씁니다.
    md_path = 'D:/Obsidian/Trader Sophia/♣ Signal Report/siri/@siri_'
    md_file = md_path + date[0:10].replace('.','') + '.md'

    # md 파일 읽기
    with open(md_file, 'r', encoding='utf-8') as f:
        md_content = f.readlines()

    # 변경된 내용을 새로운 파일에 씁니다.
    new_file_path = f'D:/Obsidian/Trader Sophia/convertword/{new_file_name}'  # 새 파일 경로를 지정합니다.
    with open(new_file_path, 'w', encoding='utf-8') as f:
        f.write(''.join(md_content))
        f.write(remove_trailing_spaces_and_blank_lines_with_spaces(''.join(content)))

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결 종료
db.close()