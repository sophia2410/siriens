
import pymysql

class DBConnect :
    def MariaDBConnct(obj) :
        """생성자:MariaDB 연결 및 종목코드 딕셔너리 생성"""
        # obj.conn = pymysql.connect(host='localhost', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')
        obj.conn = pymysql.connect(host='yunseul0907.cafe24.com', user='yunseul0907', password='hosting1004!', db='yunseul0907', charset='utf8')

    def MariaDBClose(obj) :
        """소멸자:MariaDB 연결 해제"""
        print("연결종료")
        obj.conn.close()