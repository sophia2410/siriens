import re
import pandas as pd
import Obsidian_StockList as stli
from datetime import datetime

def replace_words(content, words):
    han_let = '[\가-힣]'
    eng_let = '[a-zA-Z]'
    lb = '\[\['
    rb = '\]\]'

    new_content = []
    for line in content:
        for word in words:
            # line = re.sub(rf'\b{word}\b', f'[[{word}]]', line)
            # line = re.sub(r'\b' + re.escape(word), '[[' + word + ']]', line) 
            # line = re.sub(r'(?<!\[\[)' + re.escape(word) + r'(?!\]\])', '[[' + word + ']]', line)
            # line = re.sub(r'\b(?!\[\[)' + re.escape(word) + r'(?!\]\])', '[[' + word + ']]', line) # 오류
            # line = re.sub(r'(?<![[\가-힣a-zA-Z)\]\[])' + re.escape(word) + r'\b(?!\]\])', '[[' + word + ']]', line) # 너무 느림 + 경고
            
            # line = re.sub(r'(?<!' + han_let + eng_let + lb ')' + re.escape(word) , '[[' + word + ']]', line) # 구분오류

            line = re.sub(r'(?<![[\가-힣a-zA-Z)\]\[])' + re.escape(word), '[[' + word + ']]', line) # 너무 느림 + 경고
        new_content.append(line)
    return new_content

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

# 엑셀 파일 경로
excel_file = 'E:/Project/202410/data/_ConvertSiri_NomadReviewExcel/SignalReport_202306.xlsx'

# 옵시디언 마크다운 파일 저장 경로
# md_path = 'D:/Obsidian/Trader Sophia/♣ Signal Report/2023/siri_'
md_path = 'D:/Obsidian/Trader Sophia/99 Inbox/siri/@siri_'

# 엑셀 파일의 시트명 리스트
sheet_names = pd.ExcelFile(excel_file).sheet_names

# 각 시트별로 반복
for sheet_name in sheet_names:
    # 시트 데이터 읽기 (header=None)
    df = pd.read_excel(excel_file, sheet_name=sheet_name, header=None)
    # B 열의 데이터만 추출
    data = df[1].dropna().tolist()
    # 마크다운 파일명 생성
    md_file = md_path + sheet_name + '.md'
    # 마크다운 파일 열기
    try:
        # 엑셀 파일 읽어서 md 파일 작성
        with open(md_file, 'w', encoding='utf-8') as f:
            # 첫 번째 행은 제목으로 쓰기
            f.write(f'Title:: "{data[0]}"\n')
            # 두 번째 행은 @nomad 태그로 쓰기
            f.write(f'\n# @nomad\n\n{data[1]}\n')

            # 세 번째 행부터 반복
            for line in data[2:]:
                # 문장 제일 앞에 # 제거하기
                line = re.sub(r'#', '', line)
                line = re.sub(r' ', ' ', line)
                line = re.sub(r'`', "'", line)

                # "[시장 정리]" 텍스트가 있으면 # 시장 정리 태그로 바꾸기
                if line == "[시장 정리]" or line == "[시장정리]" or line == "시장정리" :
                    line = "\n---\n# 시장 정리\n"

                # 파일에 쓰기
                f.write(f'{line}\n\n')
            
        # md 파일 읽기
        with open(md_file, 'r', encoding='utf-8') as f:
           content = f.readlines()
           
        # 단어 치환
        content = replace_words(content, stli.words)
            
        # 치환 결과 md 파일 저장
        with open(md_file,'w',encoding='utf-8') as f:
            f.writelines(content)
            
        # 완료 메시지 출력
        print(f'{sheet_name} 파일이 생성되었습니다.')
            
    except Exception as e:
        print(f"{md_file} 파일 처리 오류: {e}")

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")