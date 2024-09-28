import openpyxl
import pandas as pd
from bs4 import BeautifulSoup
from urllib.request import urlopen
from datetime import datetime
from threading import Timer
import sys
sys.path.append("E:/Project/202410/www/boot/common/db")
from DBConnect import DBConnect as db

class DBUpdater :
    def __init__(self) :
        """생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
        db.MariaDBConnct(self)
        self.curs = self.conn.cursor()

    def __del__(self) :
        """소멸자:MariaDB 연결 해제"""
        db.MariaDBClose(self)


    def read_xlsx(self):
        """카페 시그널이브닝 엑셀 파일을 읽어와서 데이터프레임으로 반환"""
        pathExl = 'E:/Project/202410/data/PythonDBUpload/Theme_230816.xlsx'
        sheetList = []

        # openpyxl를 이용하여 시트명 가져오기
        wb = openpyxl.load_workbook(pathExl)
        for i in wb.sheetnames:
            sheetList.append(i)

        # pandas를 이용하여 각 시트별 데이터 가져오기
        rdxls = pd.DataFrame()
        xlsx = pd.ExcelFile(pathExl)
        for j in sheetList:
            df = pd.read_excel(xlsx, j)
            # print('%s Sheet의 데이타 입니다.' %j)
            # print(df)
            # print('*' * 50)
            rdxls = rdxls.append(df)
        
        return rdxls

    def update_info(self):
        rdxls = self.read_xlsx()

        file=open("signal_evening.sql", "w", encoding="utf-8")

        for idx in range(len(rdxls)):
            str1    = rdxls.str1.values[idx]
            str3    = rdxls.str3.values[idx].replace("'", "\\'").replace('"','\\"')
            str5    = rdxls.str5.values[idx].replace("'", "\\'").replace('"','\\"')
            
            sql = f'''REPLACE INTO dumy_table_theme
                      ( str1
                      ,    str3
                      , str5
                      )
                      VALUES
                      ( '{str1}'
                      ,    '{str3}'
                      , '{str5}');'''
            print(sql)
            # file.write(sql)
            self.curs.execute(sql)
        self.conn.commit()

        file.close()

    def exe_info(self) :
            self.update_info()

if __name__ == '__main__':
    dbu = DBUpdater()
    dbu.exe_info()