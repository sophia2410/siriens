# TinyMCE에디터 html 파일 변환
# DB 데이터 사이즈 비대 방지를 위해 TinyMCE에디터 입력한 글을 html 파일형식으로 저장하는 아이디어
# DB 등록 후 백업 vs 바로 html 저장 고민 중. 어떤 방식이던 반영 필수 !!! 
# 2025.03.15

import os
import pymysql
import configparser
from datetime import datetime
from collections import defaultdict

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL 데이터베이스 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 백업 저장 폴더 설정
backup_dir = "E:/Project/202410/www/backup/html"
if not os.path.exists(backup_dir):
    os.makedirs(backup_dir)

# 데이터 조회
query = """
    SELECT 
        id,
        trade_date,
        trade_method,
        profit_loss,
        trade_items,
        UNCOMPRESS(comment) AS comment
    FROM journal_trade
"""
cursor = db.cursor(pymysql.cursors.DictCursor)
cursor.execute(query)
rows = cursor.fetchall()

# 날짜별 데이터를 저장할 딕셔너리 생성
data_by_date = defaultdict(list)

for row in rows:
    trade_date = row['trade_date'].strftime("%Y-%m-%d")

    # 모든 컬럼을 UTF-8로 변환
    trade_method = row['trade_method'].decode('utf-8') if isinstance(row['trade_method'], bytes) else row['trade_method']
    profit_loss = row['profit_loss'].decode('utf-8') if isinstance(row['profit_loss'], bytes) else row['profit_loss']
    trade_items = row['trade_items'].decode('utf-8') if isinstance(row['trade_items'], bytes) else row['trade_items']
    comment = row['comment'].decode('utf-8') if isinstance(row['comment'], bytes) else row['comment']

    # 개행 변환 (HTML <br> 적용)
    comment_text = comment.replace('\n', '<br>')

    data_by_date[trade_date].append({
        "method": trade_method,
        "profit_loss": profit_loss,
        "items": trade_items,
        "comment": comment_text
    })

# 날짜별 HTML 파일 생성
for trade_date, entries in data_by_date.items():
    # 파일명 설정
    date_str = trade_date.replace("-", "")
    file_name = f"{backup_dir}/backup_trade_{date_str}.html"

    # HTML 내용 생성
    html_content = f"<!DOCTYPE html>\n<html>\n<head>\n<title>거래 내역 - {trade_date}</title>\n<meta charset='UTF-8'>\n</head>\n<body>\n"
    html_content += f"<h1>{trade_date} 거래 내역</h1>\n"

    for entry in entries:
        html_content += f"<div style='border-bottom:1px solid #ddd; padding:10px; margin-bottom:10px;'>\n"
        html_content += f"<p><strong>거래 방법:</strong> {entry['method']}</p>\n"
        html_content += f"<p><strong>손익:</strong> {entry['profit_loss']}</p>\n"
        html_content += f"<p><strong>거래 품목:</strong> {entry['items']}</p>\n"
        html_content += f"<p><strong>코멘트:</strong> {entry['comment']}</p>\n"
        html_content += f"</div>\n"

    html_content += "</body>\n</html>"

    # HTML 파일 저장
    with open(file_name, "w", encoding="utf-8") as file:
        file.write(html_content)
    
    print(f"✅ 백업 완료: {file_name}")

# 연결 종료
cursor.close()
db.close()
