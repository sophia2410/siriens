import requests
from bs4 import BeautifulSoup
import pymysql
from datetime import datetime
import time
import configparser

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# 제외할 종목의 접두어 목록
exclude_prefixes = [
    "1Q ", "ACE ", "ARIRANG ", "HANARO ", "KB ", "KODEX ", "KBSTAR ", "KoAct ", "KOSEF ",
    "N2 ", "SOL ", "TIGER ", "TIMEFOLIO ", "UNICORN ", "WOORI ", "신한 ", "대신 ", "미래에셋 ", 
    "삼성 ", "하나 ", "메리츠 ", "키움 ", "미래에셋 ", "한투 ", "히어로즈 "
]

# 데이터베이스 연결 정보 함수
def create_db_connection():
    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset')
    )

def delete_existing_data(date):
    conn = create_db_connection()
    cursor = conn.cursor()
    sql = "DELETE FROM naver_finance_stock WHERE date = %s"
    cursor.execute(sql, (date,))
    conn.commit()
    cursor.close()
    conn.close()

def save_to_db(data, date, crawl_time):
    conn = create_db_connection()
    cursor = conn.cursor()
    
    sql = """
    INSERT INTO naver_finance_stock (
        date, code, name, current_price, `change`, change_rate, face_value,
        market_cap, listed_shares, foreign_ratio, volume, per, roe, crawl_time
    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
        current_price = VALUES(current_price),
        `change` = VALUES(`change`),
        change_rate = VALUES(change_rate),
        face_value = VALUES(face_value),
        market_cap = VALUES(market_cap),
        listed_shares = VALUES(listed_shares),
        foreign_ratio = VALUES(foreign_ratio),
        volume = VALUES(volume),
        per = VALUES(per),
        roe = VALUES(roe),
        crawl_time = VALUES(crawl_time)
    """
    
    for row in data:
        cursor.execute(sql, (date, *row, crawl_time))
    
    conn.commit()
    cursor.close()
    conn.close()

def parse_value(value, value_type='int'):
    value = value.strip().replace(',', '').replace('%', '')
    if value == '' or value == '-':
        return 0
    try:
        if value_type == 'int':
            return int(value)
        elif value_type == 'float':
            return float(value)
    except ValueError:
        return 0

def parse_change(change_td):
    if 'bu_pup' in str(change_td):
        return parse_value(change_td.find('span', class_='tah').text, 'int')
    elif 'bu_pdn' in str(change_td):
        return -parse_value(change_td.find('span', class_='tah').text, 'int')
    else:
        return 0

def should_exclude(name):
    for prefix in exclude_prefixes:
        if name.startswith(prefix):
            return True
    return False

def get_stock_data(base_url, max_pages):
    data = []
    page_num = 1

    while page_num <= max_pages:
        url = base_url + str(page_num)
        response = requests.get(url)
        response.encoding = 'euc-kr'
        soup = BeautifulSoup(response.text, 'html.parser')
        
        table = soup.find('table', class_='type_2')
        if not table:
            break
        
        rows = table.find_all('tr')
        page_data = []

        for row in rows:
            cols = row.find_all('td')
            if len(cols) > 1:
                try:
                    code = cols[1].find('a')['href'].split('=')[1].zfill(6)
                    name = cols[1].text.strip()
                    if should_exclude(name):
                        continue
                    current_price = parse_value(cols[2].text, 'int')
                    change = parse_change(cols[3])
                    change_rate = parse_value(cols[4].text, 'float')
                    face_value = parse_value(cols[5].text, 'int')
                    market_cap = parse_value(cols[6].text, 'int')
                    listed_shares = parse_value(cols[7].text, 'int')
                    foreign_ratio = parse_value(cols[8].text, 'float')
                    volume = parse_value(cols[9].text, 'int')
                    per = parse_value(cols[10].text, 'float')
                    roe = parse_value(cols[11].text, 'float')
                except ValueError as e:
                    print(f"Error parsing row: {e}, skipping row: {cols}")
                    continue
                
                page_data.append([
                    code, name, current_price, change, change_rate, face_value, 
                    market_cap, listed_shares, foreign_ratio, volume, per, roe
                ])
        
        if not page_data:
            break
        
        data.extend(page_data)
        print(f"페이지 {page_num} 완료. 현재까지 수집된 종목 수: {len(data)}")
        page_num += 1

    return data

def get_total_pages(base_url):
    url = base_url + "1"
    response = requests.get(url)
    response.encoding = 'euc-kr'
    soup = BeautifulSoup(response.text, 'html.parser')
    pagination = soup.find('td', class_='pgRR')
    if pagination:
        last_page_link = pagination.find('a')['href']
        total_pages = int(last_page_link.split('=')[-1])
    else:
        total_pages = 1
    return total_pages

# 메인 크롤링 및 저장 로직
def main():
    start_time = time.time()  # 시작 시간 기록
    date = datetime.now().strftime('%Y%m%d')  # `YYYYMMDD` 형식의 문자열로 저장
    crawl_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')  # 크롤링 시간 기록
    base_urls = [
        "https://finance.naver.com/sise/sise_market_sum.naver?sosok=1&page=",
        "https://finance.naver.com/sise/sise_market_sum.naver?sosok=0&page="
    ]
    
    all_data = []

    for base_url in base_urls:
        max_pages = get_total_pages(base_url)
        # max_pages = 2 # 테스트용
        data = get_stock_data(base_url, max_pages)
        all_data.extend(data)
    
    # 기존 데이터 삭제
    delete_existing_data(date)
    
    # 새로운 데이터 저장
    save_to_db(all_data, date, crawl_time)
    
    end_time = time.time()  # 종료 시간 기록
    elapsed_time = end_time - start_time  # 소요 시간 계산
    
    print(f"크롤링 및 데이터베이스 저장 완료! 소요 시간: {elapsed_time:.2f}초")

if __name__ == "__main__":
    main()
