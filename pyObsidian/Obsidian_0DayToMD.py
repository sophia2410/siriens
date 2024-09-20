# 엑셀 파일을 읽기 위해 pandas 라이브러리를 임포트합니다.
import pandas as pd
import pymysql
import configparser
from datetime import datetime

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

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

# 오늘 날짜와 가장 가까운 날짜를 구합니다.
query_date = f"SELECT MAX(date) FROM daily_price WHERE date <= (select DATE_ADD(now(), INTERVAL 0 DAY))"

cursor.execute(query_date)
closest_date = cursor.fetchone()[0].decode('utf-8')
print(closest_date)

# 엑셀 파일의 경로와 이름을 지정합니다.
excel_file = f'E:/Project/202410/data/_EveningExcelToMD/{closest_date}.xlsx'

# 엑셀 파일을 읽어서 데이터프레임으로 저장합니다.
df = pd.read_excel(excel_file, engine='openpyxl')

# 마크다운 파일의 경로와 이름을 지정합니다. 엑셀 파일과 동일하게 합니다.
md_file = f'D:/Obsidian/Trader Sophia/♠ Daily Market/_Sophia Evening (아스타)/Sophia Evening {closest_date}.md'

# 마크다운 파일을 쓰기 모드로 엽니다.
with open(md_file, 'w', encoding='utf-8') as f:
    # 데이터프레임의 각 행을 반복하면서 마크다운 형식으로 변환합니다.
    for index, row in df.iterrows():
        # 종목코드, 종목명, 등락률, 거래량, 거래대금을 변수에 저장합니다.
        code = str(row['종목코드']).zfill(6)  # 종목코드를 문자열로 변환하고, 6자리가 될 때까지 앞에 0을 채웁니다.
        name = row['종목명']
        rate = row['등락률']
        volume = row['거래량']
        amount = row['거래대금']

        volume = format(volume, ',')
        # amount = format(amount, ',')

        # # 종목 요약 가져오기
        # cursor.execute(f"SELECT close_rate, round(volume/1000, 0) volume, round(amount/100000000, 0) amount FROM daily_price WHERE date = '{closest_date}' and code='{code}'")
        # temps = cursor.fetchall()
        # rate = ([temp[0] if temp[0] is not None else '' for temp in temps])
        # volume = ([temp[1] if temp[1] is not None else '' for temp in temps])
        # amount = ([temp[2] if temp[2] is not None else '' for temp in temps])

        # 마크다운 형식에 맞게 제목과 이미지를 작성합니다.
        f.write(f'## ● {name} ({rate}%) {volume}K / {amount}억 \n\n')

        # 종목 요약 가져오기
        cursor.execute(f"SELECT CONCAT('- ',comment) comment FROM stock_comment  WHERE code='{code}' ORDER BY id")
        stock_summaries = cursor.fetchall()
    
        # 종목 요약이 있는 경우에만 쓰기
        if stock_summaries:
            summaries = '\n'.join([summary[0].decode('utf-8') if summary[0] is not None else '' for summary in stock_summaries])
            f.write(f"{summaries}\n\n")

        # 어제,오늘 등록된 뉴스가 있는지 확인하고 가져오기
        query = f'''
        SELECT CONCAT('**',title,'**') news_title
            , content
            , CONCAT('(', news_date, CASE WHEN publisher IS NOT NULL THEN CONCAT(', ', publisher) ELSE '' END, ')') as news_info
        FROM signals 
        WHERE code='{code}' 
        AND news_date >=(select DATE_ADD({closest_date}, INTERVAL -1 DAY)) 
        ORDER BY news_date;
        '''

        cursor.execute(query)
        stock_news = cursor.fetchall()

        # 뉴스가 있는 경우에만 쓰기
        if stock_news:
            newscast = '\n'.join([news[0].decode('utf-8') if news[0] is not None else '' for news in stock_news])
            f.write(f"{newscast}\n")
            newscast = '\n'.join([news[1].decode('utf-8') if news[1] is not None else '' for news in stock_news])
            f.write(f"{newscast}")
            newscast = '\n'.join([news[2].decode('utf-8') if news[1] is not None else '' for news in stock_news])
            f.write(f"{newscast}\n\n")

        f.write(f'!(https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{code}.png?sidcode=) !(https://ssl.pstatic.net/imgfinance/chart/item/area/day/{code}.png?sidcode=) \n\n\n')
        # if int(amount.replace(',','')) > 1000 :
            # f.write(f'![|600](https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{code}.png?sidcode=)\n\n')
            # f.write(f' - 일봉차트/분봉차트\n\n\n')
            # f.write(f' - 매매 시나리오\n\n')
        # else :
            # f.write(f'![|600](https://ssl.pstatic.net/imgfinance/chart/item/candle/day/{code}.png?sidcode=)\n\n\n')

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")

# 데이터베이스 연결 종료
db.close()
