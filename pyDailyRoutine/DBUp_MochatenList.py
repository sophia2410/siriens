import pandas as pd
from bs4 import BeautifulSoup
from urllib.request import urlopen
from datetime import datetime
from threading import Timer
import sys
sys.path.append("E:/Project/202410/www/boot/common/db")
from DBConnect import DBConnect as db

class DBUpdater:
    def __init__(self):
        """생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
        db.MariaDBConnct(self)
        self.curs = self.conn.cursor()

    def __del__(self):
        """소멸자:MariaDB 연결 해제"""
        db.MariaDBClose(self)

    def read_xlsx(self):
        """모차십 종목 엑셀 파일을 읽어와서 데이터프레임으로 반환"""
        global mochaten_date
        global trade_date

        sql = "SELECT min(date) date FROM calendar a WHERE date > (select DATE_FORMAT(now(), '%Y%m%d'))"
        df = pd.read_sql(sql, self.conn)
        mochaten_date = df['date'][0].decode('utf-8')

        sql = "SELECT max(date) date FROM calendar a WHERE date <= (select DATE_FORMAT(now(), '%Y%m%d'))"
        df = pd.read_sql(sql, self.conn)
        trade_date = df['date'][0].decode('utf-8')
        

        # 특정일자 모차십 처리
        # mochaten_date = '20240807'
        # trade_date = '20240806'

        pathExl = f'E:/Project/202410/data/_Mochaten/{mochaten_date}.xlsx'
        rdxls = pd.read_excel(pathExl, engine='openpyxl')
        rdxls = rdxls.rename(columns={'차트구분':'cha_fg', '종목코드':'code', '종목명':'name', '시가총액':'market_cap', '등락률':'close_rate', '거래량':'volume', '거래대금':'tot_trade_amt', '외국인순매수금액':'f_trade_amt', '기관순매수금액':'o_trade_amt', '프로그램순매수금액':'p_trade_amt', '영업이익률(Y)':'op_ratio', '부채비율(Y)':'lb_ratio', '유통비율':'dt_ratio'})
        rdxls.code = rdxls.code.map('{:06d}'.format)
        return rdxls

    def update_info(self):
        rdxls = self.read_xlsx()
        today = datetime.today().strftime('%Y-%m-%d')

        sql = f"DELETE FROM mochaten WHERE mochaten_date = '{mochaten_date}'"
        self.curs.execute(sql)

        for idx in range(len(rdxls)):
            cha_fg = rdxls.cha_fg.values[idx]
            code = rdxls.code.values[idx]
            name = rdxls.name.values[idx]
            market_cap = rdxls.market_cap.values[idx]
            close_rate = rdxls.close_rate.values[idx] if not pd.isna(rdxls.close_rate.values[idx]) else 0
            volume = rdxls.volume.values[idx]
            tot_trade_amt = rdxls.tot_trade_amt.values[idx]
            f_trade_amt = int(rdxls.f_trade_amt.values[idx]) if not pd.isna(rdxls.f_trade_amt.values[idx]) else 0
            o_trade_amt = int(rdxls.o_trade_amt.values[idx]) if not pd.isna(rdxls.o_trade_amt.values[idx]) else 0
            p_trade_amt = int(rdxls.p_trade_amt.values[idx]) if not pd.isna(rdxls.p_trade_amt.values[idx]) else 0
            op_ratio = rdxls.op_ratio.values[idx] if not pd.isna(rdxls.op_ratio.values[idx]) else 0
            lb_ratio = rdxls.lb_ratio.values[idx] if not pd.isna(rdxls.lb_ratio.values[idx]) else 0
            dt_ratio = rdxls.dt_ratio.values[idx] if not pd.isna(rdxls.dt_ratio.values[idx]) else 0

            sql = f'''REPLACE INTO mochaten
                      (mochaten_date, cha_fg, code, name, market_cap, close_rate, volume, tot_trade_amt, f_trade_amt, o_trade_amt, p_trade_amt, op_ratio, lb_ratio, dt_ratio, trade_date, create_dtime)
                      VALUES ('{mochaten_date}', '{cha_fg}', '{code}', '{name}', '{market_cap}', '{close_rate}', '{volume}', '{tot_trade_amt}', '{f_trade_amt}', '{o_trade_amt}', '{p_trade_amt}', '{op_ratio}', '{lb_ratio}', '{dt_ratio}', '{trade_date}', now())'''
            self.curs.execute(sql)
        self.conn.commit()

        # 추가할 쿼리문 실행
        # 500억 이상 or 상한가+250억 이상 0일차 불러오기
        sql = f'''INSERT IGNORE INTO daily_watchlist
                (watchlist_date, code, name, regi_reason, close_rate, volume, tot_trade_amt, market_cap, tracking_yn, tracking_start_date, create_dtime)
                SELECT A.trade_date, A.code, A.name, '0일차', A.close_rate, A.volume, A.tot_trade_amt, A.market_cap, 'Y', A.trade_date, now()
                FROM mochaten A
                WHERE A.trade_date = '{trade_date}'
                AND A.cha_fg = 'MC000'
                AND (A.tot_trade_amt >= 500 OR (A.close_rate > 29 AND A.tot_trade_amt >= 150))'''
        self.curs.execute(sql)
        self.conn.commit()

        # date, time, name을 signals 데이터에 업데이트
        sql = f'''UPDATE daily_watchlist A
                INNER JOIN daily_price B ON B.date = A.watchlist_date AND B.code = A.code
                INNER JOIN (SELECT * FROM daily_watchlist WHERE (watchlist_date, code) IN (SELECT MAX(watchlist_date), code FROM daily_watchlist WHERE watchlist_date < '{trade_date}' GROUP BY code)) C
                ON C.code = A.code
                SET A.close_rate = CASE WHEN A.close_rate IS NULL THEN B.close_rate ELSE A.close_rate END,
                    A.volume = CASE WHEN A.volume IS NULL THEN round(B.volume/1000,0) ELSE A.volume END,
                    A.tot_trade_amt = CASE WHEN A.tot_trade_amt IS NULL THEN round(B.amount/100000000,0) ELSE A.tot_trade_amt END,
                    A.sector =  C.sector,
                    A.theme =  C.theme,
                    A.issue =  C.issue,
                    A.stock_keyword =  C.stock_keyword,
                    A.tracking_reason =  C.tracking_reason,
                    A.tracking_start_date =  CASE WHEN C.tracking_yn = 'Y' THEN C.tracking_start_date ELSE A.watchlist_date END
                WHERE A.watchlist_date = '{trade_date}' '''
        self.curs.execute(sql)
        self.conn.commit()

    def exe_info(self):
        self.update_info()

if __name__ == '__main__':
    dbu = DBUpdater()
    dbu.exe_info()
