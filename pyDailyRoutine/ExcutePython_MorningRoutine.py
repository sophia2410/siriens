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
def extract_title(title):
    # HTML 엔티티를 디코딩 (예: &quot; -> ")
    decoded_title = html.unescape(title)
    
    # 불필요한 날짜와 "장 전 뉴스 Check" 부분을 제거
    cleaned_title = re.sub(r'^\d{4}\.\d{2}\.\d{2}\.\(.\)\s*\[장 전 뉴스 Check\]\s*', '', decoded_title)
    
    # 제일 바깥의 따옴표를 제거 (양 끝에 있을 경우)
    if cleaned_title.startswith('"') and cleaned_title.endswith('"'):
        cleaned_title = cleaned_title[1:-1]

    # 최종적으로 정리된 제목 반환
    return cleaned_title.strip()

# Connect to database
def connect_db():
    return pymysql.connect(
        host='siriens.mycafe24.com',
        user='siriens',
        password='hosting1004!',
        db='siriens',
        charset='utf8'
    )

# Crawl the morning report and update market_report table
def update_market_report(url):
    conn = connect_db()
    cur = conn.cursor()

    # Extract date from URL (modify as per actual use case)
    date = datetime.now().strftime("%Y%m%d")
    
    # Crawl the page
    response = requests.get(url, headers={'User-agent': 'Mozilla/5.0'})
    soup = BeautifulSoup(response.content, 'html.parser')

    # Extract title
    title_tag = soup.find("title")
    if title_tag:
        print("title_tag.text" + title_tag.text)
        morning_title = extract_title(title_tag.text)
        print("morning_title" + morning_title)
    else:
        morning_title = "No Title"

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
        INSERT INTO market_report (report_date, morning_report_title, morning_news_title, morning_news_link)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            morning_report_title=%s,
            morning_news_title=%s,
            morning_news_link=%s
    '''

    # 출력용으로 각 변수를 출력해봅니다.
    print(f"SQL Query: {sql}")
    print(f"Parameters: date={date}, morning_title={morning_title}, first_news={first_news}, first_news_link={first_news_link}")

    cur.execute(sql, (date, morning_title, first_news, first_news_link, morning_title, first_news, first_news_link))
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

        data['close_rate'] = data['Close'].pct_change() * 100
        data['close_rate'] = data['close_rate'].fillna(0)

        for row in data.itertuples():
            date = row.Index.strftime('%Y-%m-%d')

            # Insert or update market_index table
            sql = f"""
                INSERT IGNORE INTO market_index (market_fg, date, open, high, low, close, volume, close_rate)
                VALUES ('{index}', '{date}', {row.Open}, {row.High}, {row.Low}, {row.Close}, {row.Volume}, {row.close_rate})
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