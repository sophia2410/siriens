import pandas as pd
import urllib.request as urllib
from bs4 import BeautifulSoup
import mplfinance as mpf

opener = urllib.build_opener()
opener.addheaders = [("User-Agent" , "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.104 Safari/537.36")]

# 4.4.3 맨 뒤 페이지 숫자 구하기
sise_url = 'https://finance.naver.com/item/sise_day.nhn?code=068270'
with opener.open(sise_url) as doc:
    html = BeautifulSoup(doc, 'lxml')
    pgrr = html.find('td', class_ = 'pgRR')
    s = str(pgrr.a['href']).split('=')
    last_page = s[-1]

# 4.4.4 전체 페이지 읽어오기
df = pd.DataFrame()
for page in range(1, int(last_page) + 1):
    print('.', end = '')
    page_url = '{}&page={}'.format(sise_url, page)
    df = df.append(pd.read_html(opener.open(page_url), header = 0)[0])

# 차트 출력을 위해 데이터프레임 가공하기
df = df.dropna()
df = df.iloc[0:30]
df = df.rename(columns = {'날짜' : 'Date', '시가' : 'Open', '고가' : 'High', '저가' : 'Low', '종가' : 'Close', '거래량' : 'Volume'})
df = df.sort_values(by = 'Date')
df.index = pd.to_datetime(df.Date)
df = df[['Open', 'High', 'Low', 'Close', 'Volume']]

# 엠피엘_파이낸스로 캔들 차트 그리기
#mpf.plot(df, title = 'Celltrion candle chart', type = 'candle')


#type 값에 따라 차트의 형태가 완전히 달라진다.
#mpf.plot(df, title='Celltrion ohlc chart', type='ohlc')
#mpf.plot(df, title='Celltrion candle chart', type='candle')

#keyword arguments로 mpf.plot() 함수를 호출할 때 쓰이는 인수를 담은 딕셔너리
kwargs = dict(title='Celltrion chart', type='candle', mav=(3,6,9), volume=True)

print(df)
#marketcolor를 설정하여 style에 적용
mc= mpf.make_marketcolors(up='r', down='b', inherit=True)
s  = mpf.make_mpf_style(marketcolors=mc)
mpf.plot(df, **kwargs, style=s)