# md 파일에서 # 시장 정리 이후 텍스트를 읽어서 테이블에 등록하는 프로그램

# 필요한 모듈을 임포트합니다.
import re # 정규식을 사용하기 위한 모듈
import os # 파일과 디렉토리를 다루기 위한 모듈
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈
from datetime import datetime # 날짜와 시간을 다루기 위한 모듈
import Obsidian_DBUpHistory_SubMdu # 실제 파일 업로드 처리하는 부분 모듈화

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL 데이터베이스에 연결합니다. 연결 정보는 자신의 환경에 맞게 수정하세요.
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

# 커서 생성
cursor = db.cursor()

# 저장할 폴더들을 리스트로 지정합니다.
# md_folders = ['D:/Obsidian/Trader Sophia/♣ Signal Report/2023', 'D:/Obsidian/Trader Sophia/♣ Signal Report/siri']
# md_folders = ['D:/Obsidian/Trader Sophia/99 Inbox/siri']
md_folders = ['D:/Obsidian/Trader Sophia/♣ Signal Report/2024']

# 리스트에 있는 각 폴더에 대해 반복적으로 처리합니다.
for md_folder in md_folders:

    # 폴더 안에 있는 모든 md 파일의 이름을 리스트로 가져옵니다.
    md_files = os.listdir(md_folder)

    # 리스트에 있는 각 파일에 대해 반복적으로 처리합니다.
    for md_file in md_files:

        # 파일의 경로와 이름을 결합합니다.
        md_path = os.path.join(md_folder, md_file)
        print(md_path)

        # 파일의 이름에서 일자를 추출합니다. 파일명 형식은 @siri_YYYYMMDD.md 또는 @Signal Report YYYY.MM.DD(요일).md 입니다.
            # 파일명 형식에 따라 다른 정규식을 적용합니다.
        if md_folder.endswith('siri'):
            md_date = re.search(r'@siri_(\d{8}).md', md_path).group(1)
        else:
            md_date = re.search(r'@Signal Report (\d{4}).(\d{2}).(\d{2})\(.+\).md', md_path).group(1) + re.search(r'@Signal Report (\d{4}).(\d{2}).(\d{2})\(.+\).md', md_path).group(2) + re.search(r'@Signal Report (\d{4}).(\d{2}).(\d{2})\(.+\).md', md_path).group(3)

        Obsidian_DBUpHistory_SubMdu.get_market_summary(md_path, md_date, md_file, cursor, db)

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결을 닫습니다.
db.close()