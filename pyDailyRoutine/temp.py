import re
import html

# Title extraction function for both Morning and Evening reports
def extract_title(title):
    # 불필요한 날짜, "장 전 뉴스 Check", "Signal Evening" 등의 부분을 제거
    cleaned_title = re.sub(r'^\d{4}\.\d{2}\.\d{2}\.\(.\)\s*(\[장 전 뉴스 Check\]|Signal Evening)\s*', '', title)
    
    # 제일 바깥의 따옴표를 제거 (양 끝에 있을 경우)
    if cleaned_title.startswith('"') and cleaned_title.endswith('"'):
        cleaned_title = cleaned_title[1:-1]

    # 최종적으로 정리된 제목 반환
    return cleaned_title.strip()

title_morning = '2024.09.12.(목) [장 전 뉴스 Check] ""엔비디아 밀어 올려"…나스닥 2.17% 급등"'
title_evening = '2024.09.11.(수) Signal Evening "美대선 첫 토론, 해리스 판정승... 이차전지·에너지주 강세"'

morning_title = extract_title(title_morning)
evening_title = extract_title(title_evening)

print(morning_title)  # 출력: 엔비디아 밀어 올려…나스닥 2.17% 급등
print(evening_title)  # 출력: 美대선 첫 토론, 해리스 판정승... 이차전지·에너지주 강세