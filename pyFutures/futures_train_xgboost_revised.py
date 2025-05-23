import pandas as pd
import numpy as np
import xgboost as xgb
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report
from sklearn.utils import compute_class_weight

import matplotlib.pyplot as plt
import seaborn as sns

# 1. 데이터 불러오기 (새 라벨링된 CSV 파일)
df = pd.read_csv("E:/Project/202410/data/_futures/futures_snapshot_labeled_revised.csv")
df["datetime"] = pd.to_datetime(df["datetime"])

# 2. 필수 컬럼 결측치 제거
df = df.dropna(subset=["label", "sma_5min_5", "sma_5min_20", "price", "volume"])

# 3. Feature 생성
if "gap_5_20" not in df.columns:
    df["gap_5_20"] = df["sma_5min_5"] - df["sma_5min_20"]
df["gap_price_5"] = df["price"] - df["sma_5min_5"]
df["gap_price_20"] = df["price"] - df["sma_5min_20"]
df["slope_5"] = df["sma_5min_5"] - df["sma_5min_5"].shift(1)
df["slope_20"] = df["sma_5min_20"] - df["sma_5min_20"].shift(1)
df["price_slope"] = df["price"] - df["price"].shift(1)
df["volume_avg3"] = df["volume"].rolling(window=3).mean()

# 4. 학습에 사용할 feature 목록
features = [
    "gap_5_20", "gap_price_5", "gap_price_20",
    "slope_5", "slope_20", "price_slope", "volume_avg3"
]
df = df.dropna(subset=features)

X = df[features]
y = df["label"]

# 5. 학습/검증 분할
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, stratify=y, random_state=42
)

# 6. 라벨 매핑: -1 → 0, 0 → 1, 1 → 2
label_map = {-1: 0, 0: 1, 1: 2}
reverse_label_map = {0: -1, 1: 0, 2: 1}
y_train_mapped = y_train.map(label_map)
y_test_mapped = y_test.map(label_map)

# 7. 클래스 가중치 계산 (불균형 보정)
classes = np.unique(y_train_mapped)
weights = compute_class_weight(class_weight="balanced", classes=classes, y=y_train_mapped)
class_weights = dict(zip(classes, weights))
sample_weight = y_train_mapped.map(class_weights)

# 8. XGBoost 모델 정의 및 학습
model = xgb.XGBClassifier(
    objective='multi:softmax',
    num_class=3,
    max_depth=4,
    learning_rate=0.1,
    n_estimators=100,
    eval_metric='mlogloss',
    random_state=42
)
model.fit(X_train, y_train_mapped, sample_weight=sample_weight)

# 9. 확률 기반 필터링
y_proba = model.predict_proba(X_test)
y_pred_class = np.argmax(y_proba, axis=1)

confidence_threshold = 0.9  # 실험 기준 설정 가능 (예: 0.80, 0.70 등)
y_pred_confident = []
y_test_confident = []

for i, pred_class in enumerate(y_pred_class):
    confidence = y_proba[i][pred_class]
    if confidence >= confidence_threshold: 
        y_pred_confident.append(pred_class)
        y_test_confident.append(y_test_mapped.iloc[i])

# 10. 레이블 복원
y_pred_final = pd.Series(y_pred_confident).map(reverse_label_map)
y_test_final = pd.Series(y_test_confident).map(reverse_label_map)

# 11. 성능 평가 출력
print(f"✅ 확률 기준 {confidence_threshold:.2f} 이상 신호만 평가:")
print(y_pred_final.value_counts())
print("\n📊 Precision 중심 성능 평가(0.9):")
print(classification_report(y_test_final, y_pred_final, digits=3))


# 1. Feature 중요도 추출
importances = model.feature_importances_
feature_names = X.columns
importance_df = pd.DataFrame({
    "feature": feature_names,
    "importance": importances
}).sort_values(by="importance", ascending=False)

# 2. 시각화
plt.figure(figsize=(8, 5))
sns.barplot(x="importance", y="feature", data=importance_df, palette="viridis")
plt.title("XGBoost Feature Importance")
plt.xlabel("중요도")
plt.ylabel("피처")
plt.tight_layout()
plt.show()