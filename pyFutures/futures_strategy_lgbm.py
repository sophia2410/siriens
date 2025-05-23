import pandas as pd
import numpy as np
from lightgbm import LGBMClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report

# 데이터 불러오기
df = pd.read_csv("E:/Project/202410/data/_futures/futures_snapshot_momentum.csv")
df['datetime'] = pd.to_datetime(df['datetime'])

# 오전장 필터링
df_morning = df[
    (df['datetime'].dt.time >= pd.to_datetime("09:00:00").time()) &
    (df['datetime'].dt.time <= pd.to_datetime("15:45:00").time())
].copy()

# 골든/데드 크로스 탐지
df_filtered = df_morning[df_morning['datetime'].dt.time >= pd.to_datetime("09:45:00").time()].copy()
spread = df_filtered['sma_5min_5'] - df_filtered['sma_5min_20']
df_filtered['prev_spread'] = spread.shift(1)
df_filtered['golden_cross'] = (df_filtered['prev_spread'] < 0) & (spread > 0)
df_filtered['dead_cross'] = (df_filtered['prev_spread'] > 0) & (spread < 0)

# 진입 시점 선택
entry_points = df_filtered[df_filtered['golden_cross'] | df_filtered['dead_cross']].copy()
entry_points['position'] = entry_points['golden_cross'].apply(lambda x: 1 if x else -1)

# 수익 가능성 타겟 생성
lookahead = 20
labels = []
for i in range(len(entry_points)):
    entry = entry_points.iloc[i]
    idx = entry.name
    price = entry['price']
    pos = entry['position']
    future = df_filtered.iloc[idx:idx+lookahead]['price']
    if pos == 1:
        labels.append(1 if future.max() - price >= 0.5 else 0)
    else:
        labels.append(1 if price - future.min() >= 0.5 else 0)
entry_points['target'] = labels

# 피처 선택
exclude = ['date', 'time', 'datetime', 'price', 'golden_cross', 'dead_cross', 'prev_spread', 'position', 'target']
features = [col for col in entry_points.columns if col not in exclude]
X = entry_points[features]
y = entry_points['target']

# 모델 훈련
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, shuffle=False)
model = LGBMClassifier(random_state=42)
model.fit(X_train, y_train)
y_pred = model.predict(X_test)

# 평가
print(classification_report(y_test, y_pred))
