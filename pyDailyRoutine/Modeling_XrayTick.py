import pandas as pd
import configparser
from sqlalchemy import create_engine
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
import matplotlib.pyplot as plt
import matplotlib.font_manager as fm
from datetime import timedelta

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# SQLAlchemy 엔진 생성 함수
def create_engine_from_config():
    user = config.get('database', 'user')
    password = config.get('database', 'password')
    host = config.get('database', 'host')
    db = config.get('database', 'db')
    charset = config.get('database', 'charset')
    
    # SQLAlchemy 엔진 생성
    connection_string = f'mysql+pymysql://{user}:{password}@{host}/{db}?charset={charset}'
    return create_engine(connection_string)

# 데이터 로드 함수
def load_data(engine, start_date, end_date):
    # daily_price 데이터 로드
    query_daily_price = f"""
    SELECT code, date, close, pre_close, volume
    FROM daily_price
    WHERE date BETWEEN '{start_date}' AND '{end_date}'
    """
    df_daily_price = pd.read_sql(query_daily_price, engine, parse_dates=['date'])

    # kiwoom_xray_tick_summary 데이터 로드
    query_tick_summary = f"""
    SELECT code, date, name, tot_volume, tot_amt, avg_amt
    FROM kiwoom_xray_tick_summary
    WHERE date BETWEEN '{start_date}' AND '{end_date}'
    """
    df_tick_summary = pd.read_sql(query_tick_summary, engine, parse_dates=['date'])
    
    return df_daily_price, df_tick_summary

# 데이터 전처리 및 특징 추출
def preprocess_data(df_daily_price, df_tick_summary):
    # 데이터 병합
    df = pd.merge(df_daily_price, df_tick_summary, on=['date', 'code'])

    # 등락률 차이 계산
    df['price_change'] = df['close'] - df['pre_close']
    df['price_change_rate'] = df['price_change'] / df['pre_close'] * 100

    # 평균 거래 금액 비율
    df['avg_amt_rate'] = df['tot_amt'] / df['tot_volume']

    # 필요 없는 컬럼 제거
    df = df.drop(columns=['pre_close', 'tot_amt'])

    # 데이터 타입 최적화
    df['code'] = df['code'].astype('category')
    df['price_change'] = df['price_change'].astype('float32')
    df['price_change_rate'] = df['price_change_rate'].astype('float32')
    df['avg_amt_rate'] = df['avg_amt_rate'].astype('float32')

    # 라벨 생성 (예: 3일 후)
    df['future_close'] = df.groupby('code')['close'].shift(-3)
    df['future_price_change_rate'] = (df['future_close'] - df['close']) / df['close'] * 100
    df['label'] = (df['future_price_change_rate'] > 0).astype(int)

    # 라벨링 후 필요 없는 컬럼 제거
    df = df.drop(columns=['future_close', 'future_price_change_rate'])
    
    return df

# 모델 학습 및 평가
def train_and_evaluate_model(df):
    # 특징과 라벨로 분리
    X = df.drop(columns=['date', 'code', 'label', 'name'])
    y = df['label']

    # 학습용, 테스트용 데이터 분리
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # 모델 학습
    model = RandomForestClassifier()
    model.fit(X_train, y_train)

    return model

# 최근 데이터로 예측
def predict_recent_data(df, model, recent_start_date):
    recent_data = df[df['date'] >= recent_start_date]  # 최근 날짜 필터링
    X_recent = recent_data.drop(columns=['date', 'code', 'label', 'name'])
    recent_data['prediction'] = model.predict(X_recent)
    
    # 상승 예측된 종목 필터링
    predicted_up = recent_data[recent_data['prediction'] == 1]
    
    # 숫자 컬럼만 평균 계산
    numeric_cols = predicted_up.select_dtypes(include=['float32', 'float64', 'int']).columns
    top_predicted_up = predicted_up.groupby(['code', 'name'])[numeric_cols].mean().sort_values(by='price_change_rate', ascending=False).head(10)
    top_predicted_up_codes = top_predicted_up.index.get_level_values('code').tolist()
    
    return recent_data[recent_data['code'].isin(top_predicted_up_codes)]

# 향후 7일 예측
def predict_future_data(df, model):
    last_date = df['date'].max()
    future_dates = pd.date_range(last_date + timedelta(days=1), periods=7, freq='B')  # 다음 7일 예측, 주말 제외
    future_data = []

    for code in df['code'].unique():
        recent_data = df[df['code'] == code].sort_values(by='date').tail(1)
        for future_date in future_dates:
            future_row = recent_data.copy()
            future_row['date'] = future_date
            future_data.append(future_row)
    
    future_df = pd.concat(future_data, ignore_index=True)
    X_future = future_df.drop(columns=['date', 'code', 'label', 'name'])
    future_df['prediction'] = model.predict(X_future)
    
    return future_df

# 예측된 상승 종목 확인 및 시각화
def visualize_predictions(recent_data, future_data):
    # 한글 폰트 설정
    font_path = 'malgun.ttf'  # 사용하려는 폰트 경로 설정
    font_prop = fm.FontProperties(fname=font_path)
    plt.rcParams['font.family'] = font_prop.get_name()

    # 시각화
    plt.figure(figsize=(14, 7))
    for code in recent_data['code'].unique():
        stock_data = recent_data[recent_data['code'] == code]
        future_stock_data = future_data[future_data['code'] == code]
        plt.plot(stock_data['date'], stock_data['close'], label=f"{stock_data['name'].iloc[0]} Close Price")
        plt.scatter(stock_data['date'], stock_data['close'], c='red', label=f"{stock_data['name'].iloc[0]} Predicted Up")
        plt.plot(future_stock_data['date'], future_stock_data['close'], linestyle='--', label=f"{future_stock_data['name'].iloc[0]} Predicted Future")
    plt.legend()
    plt.show()

# 메인 함수
def main():
    # SQLAlchemy 엔진 생성
    engine = create_engine_from_config()

    # 데이터 로드 (부분적으로)
    start_date = '20240226'
    end_date = '20240715'  # 현재 날짜
    df_daily_price, df_tick_summary = load_data(engine, start_date, end_date)

    # 데이터 전처리 및 특징 추출
    df = preprocess_data(df_daily_price, df_tick_summary)

    # 모델 학습 및 평가
    model = train_and_evaluate_model(df)

    # 최근 데이터로 예측
    recent_start_date = '20240708'  # 최근 일주일간 데이터
    recent_data = predict_recent_data(df, model, recent_start_date)

    # 향후 7일 예측
    future_data = predict_future_data(df, model)

    # 예측된 상승 종목 확인 및 시각화
    visualize_predictions(recent_data, future_data)

if __name__ == "__main__":
    main()
