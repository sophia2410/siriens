from excel_download import download_excel

# 설정 파일 경로
config_file_path = 'E:/Project/202410/www/boot/common/db/database_config.ini'

# 파일명 접두어
filename_prefix = 'watchlist'

# SQL 쿼리와 정렬 조건을 읽음
with open("E:/Project/202410/www/pyObsidian/vars_downExcel.txt", "r", encoding="utf-8") as f:
    lines = f.readlines()
    filename_prefix = lines[0].strip() 
    orderby = lines[1].strip() 
    query = "".join(lines[2:])

# 파일 저장 디렉토리 설정
output_dir = 'C:/KiwoomHero4/temp'

# 모드 선택 (True: 키움용, False: 일반용)
kiwoom_mode = True  # 키움용 모드 활성화 (필요에 따라 변경)

# 일반용 엑셀 헤더 설정 (키움용이 아닌 경우)
headers = ['Column1', 'Column2', 'Column3', 'Column4'] if not kiwoom_mode else None

# 엑셀 파일 생성 및 다운로드 실행
file_path = download_excel(config_file_path, query, orderby, output_dir, filename_prefix, kiwoom_mode, headers)

# 생성된 파일 확인
print(f"엑셀 파일이 생성되었습니다: {file_path}")
