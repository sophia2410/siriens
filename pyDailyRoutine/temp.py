import pandas as pd

# 1분봉 CSV 파일 로딩
df = pd.read_csv('C:/KiwoomHero4/temp/2025-05-02_1min.csv')
df['datetime'] = pd.to_datetime(df['datetime'])
df.set_index('datetime', inplace=True)

# 5분 단위 그룹핑 키 생성
df['group_key'] = df.index.to_series().dt.floor('5T')

# 각 5분 구간마다 1분봉 개수 출력
group_counts = df.groupby('group_key').size()
print(group_counts.sort_index().head(30))
