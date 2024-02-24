import pandas as pd
from bs4 import BeautifulSoup
import pymysql
import configparser
import requests
from datetime import datetime
import threading

class DBUpdater:
    def __init__(self):
        config = configparser.ConfigParser()
        config.read('E:/Project/202410/www/boot/common/db/database_config.ini')
        db_config = {
            'host': config.get('database', 'host'),
            'user': config.get('database', 'user'),
            'password': config.get('database', 'password'),
            'db': config.get('database', 'db'),
            'charset': config.get('database', 'charset')
        }
        self.conn = pymysql.connect(**db_config)
        self.codes = set()

    def __del__(self):
        self.conn.close()

    def update_codes_from_kiwoom_stock(self):
        """kiwoom_stock 테이블에서 종목 코드를 불러와 세트에 저장"""
        with self.conn.cursor() as curs:
            sql = "SELECT code FROM kiwoom_stock WHERE market_fg IN ('KOSPI', 'KOSDAQ')"
            curs.execute(sql)
            result = curs.fetchall()
            self.codes = {code[0] for code in result}
        print("Codes updated from kiwoom_stock.")

        # # 테스트를 위해 코드 목록을 처음 10개만 사용
        # self.codes = list(self.codes)[:10]  # 코드 목록을 리스트로 변환 후 처음 10개만 선택

    def read_naver_and_update(self):
        """네이버에서 주식 시세를 읽어서 realtime_price 테이블에 저장"""
        for code in self.codes:
            code = code.decode('utf-8')

            url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"
            # print(url)
            response = requests.get(url, headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0'})
            if response.status_code == 200:
                html = BeautifulSoup(response.text, "html.parser")
                trs = html.find_all("tr", onmouseover=True)
                if trs:
                    tr = trs[0]  # 첫 번째 유효한 데이터 행 사용
                    data = tr.find_all("td")
                    # print(data)

                    # 데이터 추출
                    date = data[0].text.strip().replace('.', '')
                    
                    if date:
                        close = int(data[1].get_text().strip().replace(',',''))
                        change = int(data[2].get_text().strip().replace(',',''))
                        volume = int(data[6].get_text().strip().replace(',',''))
    
                        sign = tr.find('img', alt='하락') # 전일비에 하락 사인이 있는 경우 '-' 처리
                        if sign is not None :
                            change = -change
    
                        close_rate = round((close - (close+(change*-1))) / (close+(change*-1)) * 100 ,2) # 등락률 계산
        
                        # 여기서부터 DB에 저장하는 로직 구현
                        with self.conn.cursor() as curs:
                            sql = f"REPLACE INTO realtime_price(code, date, close, close_rate, volume, create_dtime) VALUES ('{code}', '{date}', {close}, {close_rate}, {volume}, now())"
                            # print(sql)
                            curs.execute(sql)
                        self.conn.commit()
                        print(f"Data updated for {code}")

    def schedule_next_run(self):
        """다음 크롤링을 위한 타이머 설정"""
        now = datetime.now()
        next_run_minute = ((now.minute // 5) + 1) * 5  # 다음 5분 주기 계산
        if next_run_minute >= 60:  # 시간 넘김 처리
            next_run_hour = (now.hour + 1) % 24
            next_run_minute = next_run_minute % 60
        else:
            next_run_hour = now.hour
        
        # 다음 실행 시간 설정
        next_run_time = now.replace(hour=next_run_hour, minute=next_run_minute, second=0, microsecond=0)
        wait_seconds = (next_run_time - now).total_seconds()

        print(f"Next update scheduled at {next_run_time.strftime('%Y-%m-%d %H:%M:%S')}. Waiting for {wait_seconds} seconds.")

        # wait_seconds 후에 run 메서드 실행
        threading.Timer(wait_seconds, self.run).start()
        
    def run(self):
        """초기 실행 및 주기적 업데이트 로직"""
        self.update_codes_from_kiwoom_stock()
        self.read_naver_and_update()
        self.schedule_next_run()

if __name__ == '__main__':
    
    dbu = DBUpdater()
    dbu.run()
