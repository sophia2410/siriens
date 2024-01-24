# 파이썬으로 네이버 증권 페이지에서 삼성전자의 지수와 전일대비 상승 정보를 크롤링하는 코드
import requests
from bs4 import BeautifulSoup
from datetime import date
import pymysql # MySQL 데이터베이스를 연결하고 조작하기 위한 모듈
import configparser # 설정 파일을 읽기 위한 모듈

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# MySQL 데이터베이스에 연결합니다. 연결 정보는 자신의 환경에 맞게 수정하세요.
db = pymysql.connect(
	host=config.get('database', 'host'),
	user=config.get('database', 'user'),
	password=config.get('database', 'password'),
	db=config.get('database', 'db'),
	charset=config.get('database', 'charset')
)

# 커서 생성
cursor = db.cursor()

# 관심종목 호출 페이지
with open("E:/Project/202410/www/pyObsidian/vars_viewChart.txt", "r", encoding="utf-8") as f:
	pgmId    = f.readline().strip()
	sector   = f.readline().strip() 
	theme    = f.readline().strip() 
	category = f.readline().strip() 
	search_date = f.readline().strip()

if pgmId == 'sophiaWatchlist' :
	sql = '''SELECT DISTINCT code FROM watchlist_sophia WHERE sector = %s AND theme LIKE CONCAT ('%%',%s,'%%') AND category LIKE CONCAT ('%%',%s,'%%')'''
	cursor.execute(sql, (sector,theme,category))
	print(sql, (sector,theme,category))
elif pgmId == 'watchlist' :
	sql = '''SELECT DISTINCT B.code FROM (SELECT date FROM calendar WHERE date <= %s ORDER BY date desc LIMIT 30) A INNER JOIN daily_watchlist B ON A.date = B.watchlist_date'''
	if search_date == '' :
		today = date.today()
		today = today.strftime('%Y%m%d')
		cursor.execute(sql, (today))
		print(sql, (today))
	else :
		cursor.execute(sql, (search_date))
		print(sql, (search_date))
		
# 쿼리 실행

# 결과 가져오기
cds = cursor.fetchall()
num_rows = len(cds)
# print(f"{num_rows} rows fetched.")

# 각 종목코드에 대해 웹 크롤링하고 데이터베이스에 등록하는 반복문
for cd in cds:
	code = cd[0].decode('utf-8')

	# 과거 구해온 가격 정보 지우기
	sql = '''DELETE FROM temporary_price WHERE code = %s'''
	# 쿼리 실행
	cursor.execute(sql, (code))

	# 웹 크롤링할 URL
	url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"
	# print(url)

	# 웹 크롤링 결과를 파싱하여 표 찾기
	table = BeautifulSoup(requests.get(url,
	headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text, "html.parser").find("table")
	# 표에서 첫 번째 row 찾기
	row = table.find_all("tr")[2]
	
	# row에서 각 열의 데이터 찾기
	data = row.find_all("span")
	# print(data)

	# 데이터 텍스트로 추출하기
	# date = data[0].get_text().strip().replace('.','') #now() 사용
	close = int(data[1].get_text().strip().replace(',',''))
	change = int(data[2].get_text().strip().replace(',',''))

	sign = row.find('img', alt='하락')
	
	if sign is not None :
		change *= -1

	close_rate = round((close - (close+(change*-1))) / (close+(change*-1)) * 100 ,2)


	# 데이터베이스에 등록할 쿼리
	sql = '''INSERT INTO temporary_price(code, crawling_dtime, crawling_price, crawling_rate) VALUES (%s, now(), %s, %s)'''
	# 쿼리 실행
	cursor.execute(sql, (code, close, close_rate))
	# 데이터베이스에 변경사항 반영
	db.commit()

# 데이터베이스 연결 닫기
db.close()


# 데이터 구하기는 일단 성공. 위에 쿼리문에서 종목코드 구해온 후 로직 적용하는 걸로 변경 필요함
# # url = "https://finance.naver.com/item/sise_day.nhn?code=005930&page=1"
# url = f"https://finance.naver.com/item/sise_day.naver?code={code}&page=1"

# table = BeautifulSoup(requests.get(url,
# 	headers={'referer':'https://finance.naver.com/','User-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'}).text, "html.parser").find("table")

# # print(table)
# # table = table.rename(columns={'날짜':'date','종가':'close','전일비':'diff','시가':'open','고가':'high','저가':'low','거래량':'volume'})
# # 표에서 첫 번째 row 찾기
# row = table.find_all("tr")[2]
# # print(row)
# # row에서 각 열의 데이터 찾기
# data = row.find_all("td")
# # print(data)

# # 데이터 텍스트로 추출하기
# date = data[0].get_text()
# close = data[1].get_text()
# change = data[2].get_text()

# # 결과 출력
# print(date, close, change)


# 네이버 종목 메인 페이지에서 가격 구해오기... 건별로 호출하니 너무 무거워져 제외처리
# url = "https://finance.naver.com/item/main.nhn?code=005930"
# response = requests.get(url)
# soup = BeautifulSoup(response.text, "html.parser")

# title = soup
# # find 메서드를 사용하여 원하는 태그 찾기
# p_no_today = soup.find("p", class_="no_today")
# today_number = p_no_today.find("span", class_="blind")
# print(today_number.text)

# p_no_exday = soup.find("p", class_="no_exday")
# exday_sign = p_no_exday.find("span", class_="ico plus")
# if exday_sign is None :
# 	exday_sign = '-'
# else:
# 	exday_sign = '+'
# exday_number = p_no_exday.find_all("span", class_="blind")[1]

# print(exday_sign, exday_number.text, '%')

# 조회할 관심종목 리스트 구해오기