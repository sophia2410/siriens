import pandas as pd
from bs4 import BeautifulSoup
import pymysql
import configparser
import requests
from datetime import datetime, timedelta
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

    def delete_data(self):
        with self.conn.cursor() as curs:
            # 전일 데이터까지 삭제하는 로직
            yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y%m%d')
            delete_sql = f"DELETE FROM realtime_price WHERE date <= '{yesterday}'"
            print(delete_sql)
            curs.execute(delete_sql)

    def update_codes_from_kiwoom_stock(self):
        with self.conn.cursor() as curs:
            sql = "SELECT DISTINCT code FROM watchlist_sophia"
            curs.execute(sql)
            result = curs.fetchall()
            self.codes = {code[0] for code in result}
        print("Codes updated from kiwoom_stock.")

        # # 테스트를 위해 코드 목록을 처음 10개만 사용
        # self.codes = list(self.codes)[:10]  # 코드 목록을 리스트로 변환 후 처음 10개만 선택

    def read_naver_and_update(self):
        now = datetime.now()
        if not (now.weekday() < 5 and now.hour >= 9 and (now.hour < 15 or (now.hour == 15 and now.minute <= 30))):
            print("Outside trading hours, skip updating.")
            return

        for code in self.codes:
            code = code.decode('utf-8')
            url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"
            response = requests.get(url, headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0'})
            if response.status_code == 200:
                html = BeautifulSoup(response.text, "html.parser")
                trs = html.find_all("tr", onmouseover=True)
                for tr in trs:
                    data = tr.find_all("td")
                    date = data[0].text.strip().replace('.', '')
                    if date:
                        close = int(data[1].get_text().strip().replace(',',''))
                        change = int(data[2].get_text().strip().replace(',',''))
                        volume = int(data[6].get_text().strip().replace(',',''))

                        sign = tr.find('img', alt='하락')
                        if sign is not None:
                            change = -change

                        close_rate = round((close - (close + change)) / (close + change) * 100, 2)

                with self.conn.cursor() as curs:
                    sql = f"REPLACE INTO realtime_price(code, date, close, close_rate, volume, create_dtime) VALUES ('{code}', '{date}', {close}, {close_rate}, {volume}, now())"
                    # print(sql)
                    curs.execute(sql)
                self.conn.commit()
                # print(f"Data updated for {code}")

    def schedule_next_run(self):
        # 현재 시간을 기준으로 다음 5분 단위 시간 계산
        now = datetime.now()
        next_run_minute = ((now.minute // 5) + 1) * 5
        if next_run_minute >= 60:  # 시간 넘김 처리
            next_run_hour = (now.hour + 1) % 24
            next_run_minute = 0  # 60분이 되면 0분으로 설정
        else:
            next_run_hour = now.hour
        
        # 다음 실행 시간 설정
        next_run_time = now.replace(hour=next_run_hour, minute=next_run_minute, second=0, microsecond=0)
        
        # 만약 계산된 다음 실행 시간이 현재 시간보다 과거라면, 5분을 더해 미래로 설정
        if next_run_time <= now:
            next_run_time += timedelta(minutes=5)

        wait_seconds = (next_run_time - now).total_seconds()

        print(f"Next update scheduled at {next_run_time.strftime('%Y-%m-%d %H:%M:%S')}. Waiting for {wait_seconds} seconds.")

        # wait_seconds 후에 run 메서드 실행
        threading.Timer(wait_seconds, self.run).start()

    def run(self):
        self.delete_data()
        self.update_codes_from_kiwoom_stock()
        self.read_naver_and_update()
        self.schedule_next_run()

if __name__ == '__main__':
    dbu = DBUpdater()
    dbu.run()
