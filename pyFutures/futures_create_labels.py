import pandas as pd
import numpy as np

# 1. CSV 불러오기
df = pd.read_csv("E:/Project/202410/data/_futures/futures_snapshot_momentum.csv")
df["datetime"] = pd.to_datetime(df["datetime"])

# 2. 9:45 ~ 15:45 범위 필터링
df = df[(df["datetime"].dt.time >= pd.to_datetime("09:45").time()) &
        (df["datetime"].dt.time <= pd.to_datetime("15:45").time())].copy()

# 3. 미래 10분 후 5분 평균 가격 계산 (레이블용)
future_offset = 10  # 10 row 후
future_window = 5   # 그 뒤 5개 평균
threshold = 0.5     # 레이블 기준 포인트

df["future_avg_price"] = df["price"].shift(-future_offset).rolling(window=future_window).mean()

# 4. 레이블 생성 함수
def label_price_change(row):
    if pd.isna(row["future_avg_price"]):
        return np.nan
    price_now = row["price"]
    future_price = row["future_avg_price"]
    if future_price - price_now > threshold:
        return 1   # 롱 진입 적합
    elif price_now - future_price > threshold:
        return -1  # 숏 진입 적합
    else:
        return 0   # 진입 없음

df["label"] = df.apply(label_price_change, axis=1)

# 5. 결과 저장
df.to_csv("E:/Project/202410/data/_futures/futures_snapshot_labeled.csv", index=False)
print("✅ 레이블 생성 완료: futures_snapshot_labeled.csv 저장됨")
