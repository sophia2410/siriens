import requests
from bs4 import BeautifulSoup
import pymysql
import yfinance as yf
from pykrx import stock
import pandas as pd
from datetime import datetime
import re
import html

# Special character handler
def special_char(str):
    pt = str.replace('"','\\"').replace("'", "\\'")
    pt = pt.replace('∼','~')
    pt = pt.replace('＆','&')
    return pt

# Title extraction function
def extract_title_date(title):
    # HTML 엔티티를 디코딩 (예: &quot; -> ")
    decoded_title = html.unescape(title)

    # 정규식으로 날짜 추출 (\d{4}\.\d{2}\.\d{2} 형식)
    date_match = re.search(r'\d{4}\.\d{2}\.\d{2}', decoded_title)
    print(date_match)
    if date_match:
        extracted_date = date_match.group(0)
        # 날짜 형식을 YYYYMMDD로 변환
        formatted_date = extracted_date.replace('.', '')
    else:
        formatted_date = None
    
    # 불필요한 날짜와 "장 전 뉴스 Check" 부분을 제거
    cleaned_title = re.sub(r'^\d{4}\.\d{2}\.\d{2}\.\(.\)\s*\[장 전 뉴스 Check\]\s*', '', decoded_title)
    
    # 제일 바깥의 따옴표를 제거 (양 끝에 있을 경우)
    if cleaned_title.startswith('"') and cleaned_title.endswith('"'):
        cleaned_title = cleaned_title[1:-1]

    # 최종적으로 정리된 제목과 추출된 날짜 반환
    return cleaned_title.strip(), formatted_date

# Connect to database
def connect_db():
    return pymysql.connect(
        host='siriens.mycafe24.com',
        user='siriens',
        password='mariadb1004!',
        db='siriens',
        charset='utf8'
    )

# Crawl the morning report and update market_report table
def update_market_report(url):
    conn = connect_db()
    cur = conn.cursor()

    # Crawl the page
    response = requests.get(url, headers={'User-agent': 'Mozilla/5.0'})
    soup = BeautifulSoup(response.content, 'html.parser')

    # Extract title
    title_tag = soup.find("title")
    if title_tag:
        print("title_tag.text" + title_tag.text)
        morning_title, date = extract_title_date(title_tag.text)
        print("morning_title" + morning_title)
        print("date" + morning_title)
    else:
        morning_title = "No Title"

    # 날짜가 추출되지 않으면 현재 날짜 사용 (예외 처리)
    if not date:
        date = datetime.now().strftime("%Y%m%d")

    # Extract the first news
    module_text = soup.find('div', {'class': 'se-module se-module-text'})
    if module_text:
        first_news_tag = module_text.find('p', {'class': 'se-text-paragraph'})
        if first_news_tag:
            first_news_a = first_news_tag.find('a', href=True)
            if first_news_a:
                first_news = first_news_a.text.strip()
                first_news_link = first_news_a['href'].split('#')[0]
            else:
                first_news = first_news_tag.text.strip()
                first_news_link = 'No Link'
        else:
            first_news = 'No News'
            first_news_link = 'No Link'
    else:
        first_news = 'No News'
        first_news_link = 'No Link'

    # Insert or update the market_report table
    sql = '''
        INSERT INTO market_report (date, morning_report_title, morning_news_title, morning_news_link)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            morning_report_title=%s,
            morning_news_title=%s,
            morning_news_link=%s
    '''

    # 출력용으로 각 변수를 출력해봅니다.
    print(f"SQL Query: {sql}")
    print(f"Parameters: date={date}, morning_title={first_news}, first_news={first_news}, first_news_link={first_news_link}")

    cur.execute(sql, (date, first_news, first_news, first_news_link, first_news, first_news, first_news_link))
    conn.commit()
    cur.close()
    conn.close()

# Fetch NASDAQ and S&P 500 indices and update the market_index table
def update_market_index():
    conn = connect_db()
    cursor = conn.cursor()

    # Define the indices
    index_dict = {
        'S&P 500': '^GSPC',
        'NASDAQ': '^IXIC'
    }

    # Get the start and end dates
    start_date = (datetime.now() - pd.DateOffset(weeks=1)).strftime('%Y-%m-%d')
    end_date = datetime.now().strftime('%Y-%m-%d')

    for index, ticker in index_dict.items():
        data = yf.download(ticker, start=start_date, end=end_date)
    # MultiIndex 컬럼을 단순한 인덱스로 변환
        if isinstance(data.columns, pd.MultiIndex):
            data.columns = data.columns.droplevel(1)

        data['close_rate'] = data['Close'].pct_change() * 100
        data['close_rate'] = data['close_rate'].fillna(0)

        for row in data.itertuples():
            date = row.Index.strftime('%Y-%m-%d')

            # Insert or update market_index table
            sql = f"""
                INSERT IGNORE INTO market_index (market_fg, date, open, high, low, close, volume, close_rate)
                VALUES ('{index}', '{date}', {row[1]}, {row[2]}, {row[3]}, {row[4]}, {row[6]}, {row[7]})
            """
            cursor.execute(sql)

    conn.commit()
    cursor.close()
    conn.close()

# Main function to handle both tasks
def main(url):
    print("Processing Morning Report and Market Index...")
    update_market_report(url)
    update_market_index()
    print("Processing completed.")

# Entry point for running the script
if __name__ == "__main__":
    import sys
    if len(sys.argv) > 1:
        url = sys.argv[1]
        main(url)
    else:
        print("Error: Please provide a URL as an argument.")