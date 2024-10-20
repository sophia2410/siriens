import pandas as pd
import pymysql
import configparser
import numpy as np
from multiprocessing import Pool

# 설정 파일에서 DB 정보 가져오기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL DB 연결 설정
def get_db_connection():
    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset')
    )

def fetch_data():
    db = get_db_connection()
    try:
        # 5개월치 데이터를 가져오는 쿼리
        sql = '''
        SELECT date, code, open, high, low, close, volume, amount
        FROM daily_price
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        '''
        df = pd.read_sql(sql, db)
    finally:
        db.close()
    return df

def calculate_volatility(df):
    """변동률 계산 (고가 - 저가) / 종가"""
    df['변동률'] = (df['high'] - df['low']) / df['close']
    return df

def get_next_high_prices(df):
    """종목별 익일 및 익익일 고가 대비 수익률을 계산"""
    df['익일_수익률'] = df.groupby('code')['high'].shift(-1) / df['close'] - 1  # 종목별로 익일 고가 대비 수익률 계산
    df['익익일_수익률'] = df.groupby('code')['high'].shift(-2) / df['close'] - 1  # 종목별로 익익일 고가 대비 수익률 계산
    return df

def analyze_condition(df, 변동률_조건, 거래대금_조건):
    """특정 변동률과 거래대금 조건에 따른 상승 확률 계산"""
    # 변동률 및 거래대금 조건에 맞는 데이터 필터링
    조건 = (df['변동률'] < 변동률_조건) & (df['amount'] > 거래대금_조건)
    필터링된_데이터 = df[조건]
    
    # 익일 및 익익일 고가 대비 상승 확률 계산
    익일_상승확률 = (필터링된_데이터['익일_수익률'] > 0).mean()
    익익일_상승확률 = (필터링된_데이터['익익일_수익률'] > 0).mean()

    # 결과 저장
    return {
        '변동률': 변동률_조건,
        '거래대금': 거래대금_조건,
        '상승확률': max(익일_상승확률, 익익일_상승확률)  # 두 확률 중 더 높은 값 선택
    }

def parallel_condition_analysis(df, 변동률_범위, 거래대금_범위):
    """병렬 처리를 통해 각 조건에 따른 상승 확률을 계산하고, 모든 결과를 반환"""
    results = []
    
    # 변동률과 거래대금 조건 조합을 병렬 처리
    with Pool(processes=4) as pool:  # CPU 코어 수에 맞게 설정 가능
        results = pool.starmap(analyze_condition, [(df, 변동률, 거래대금) 
                        for 변동률 in 변동률_범위 for 거래대금 in 거래대금_범위])
    
    # 상승 확률을 기준으로 결과를 내림차순 정렬
    sorted_results = sorted(results, key=lambda x: x['상승확률'], reverse=True)
    return sorted_results

def summarize_by_volatility_and_amount(results):
    """변동률별로 거래대금 조건을 각각 출력"""
    df = pd.DataFrame(results)

    # 변동률 및 거래대금별 그룹화 없이 개별로 출력
    return df.sort_values(by=['변동률', '거래대금'], ascending=[True, True]).reset_index(drop=True)

def main():
    # 데이터 가져오기
    df = fetch_data()

    # 변동률 계산
    df = calculate_volatility(df)

    # 익일 및 익익일 고가 대비 수익률 계산
    df = get_next_high_prices(df)

    # 조건 탐색 범위 설정
    변동률_범위 = np.arange(0.01, 0.1, 0.01)  # 변동률: 0.01 ~ 0.09
    거래대금_범위 = np.arange(50000000000, 200000000000, 10000000000)  # 500억 ~ 2000억

    # 병렬 처리로 모든 조건에 대한 결과 리스트 반환
    결과_리스트 = parallel_condition_analysis(df, 변동률_범위, 거래대금_범위)

    # 변동률 및 거래대금별로 각각 출력
    정렬된_결과 = summarize_by_volatility_and_amount(결과_리스트)

    # 상위 n개의 결과 출력
    print("상위 조건들:")
    for rank, result in 정렬된_결과.iterrows():
        print(f"변동률: {result['변동률']}, 거래대금: {result['거래대금']}, 상승 확률: {result['상승확률'] * 100:.2f}%")

if __name__ == "__main__":
    main()