import pandas as pd
import pymysql
import configparser
import os
from tqdm import tqdm
import xlrd
from datetime import datetime

# =====================================================================
# 스크립트: update_historical_rsi_separate_files.py
# 설명: 하드코딩된 base_path 경로 내 "rsi_5min.xls", "rsi_10min.xls", "rsi_15min.xls", "rsi_60min.xls"
#       파일에서 RSI_14 값을 읽어와 각 futures_<분봉> 테이블에 업데이트합니다.
# =====================================================================

# 하드코딩된 데이터 디렉토리 경로
base_path = "C:/KiwoomHero4/temp"
# DB 설정 파일 경로 (필요시 수정)
config_path = "E:/Project/202410/www/boot/common/db/database_config.ini"

# DB 설정 읽기 (INI가 ANSI(cp949) 인코딩일 경우)
config = configparser.ConfigParser()
config.read(config_path, encoding='cp949')

db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

minute_types = ['5min', '10min', '15min', '60min']

with db.cursor() as cursor:
    for m in minute_types:
        file_name = f"rsi_{m}.xls"
        file_path = os.path.join(base_path, file_name)
        if not os.path.exists(file_path):
            print(f"❌ 파일 없음: {file_path}")
            continue

        print(f"🔄 futures_{m} 업데이트 시작: {file_name}")
        # xlrd로 워크북 읽기, 인코딩 강제 설정
        try:
            wb = xlrd.open_workbook(file_path, encoding_override='cp949')
            sheet = wb.sheet_by_index(0)
            # 헤더
            header = [str(cell).strip() for cell in sheet.row_values(0)]
            # 데이터
            rows = [sheet.row_values(i) for i in range(1, sheet.nrows)]
            df = pd.DataFrame(rows, columns=header)
        except Exception as e:
            print(f"❌ 파일 로드 오류 ({file_name}): {e}")
            continue

        # 컬럼명 전처리
        df.columns = df.columns.str.strip()
        # 컬럼명 매핑
        rename_map = {
            '날짜': 'date',
            '시간': 'time',
            'RSI 14': 'rsi_14',
            'RSI14': 'rsi_14',
            'RSI_14': 'rsi_14'
        }
        df.rename(columns=rename_map, inplace=True)

        # 필수 컬럼 체크
        missing = [col for col in ('date', 'time', 'rsi_14') if col not in df.columns]
        if missing:
            print(f"❌ 필요한 컬럼 누락 in {file_name}: {missing}")
            continue

        # datetime 생성
        df['datetime'] = pd.to_datetime(
            df['date'].astype(str).str.strip() + ' ' + df['time'].astype(str).str.strip(),
            errors='coerce'
        )
        df = df.dropna(subset=['datetime'])

        # 필요한 컬럼만 추출
        df = df[['datetime', 'rsi_14']]

        # 업데이트 쿼리
        sql = f"""
            UPDATE futures_{m}
               SET rsi_14 = %s
             WHERE datetime = %s
        """

        for _, row in tqdm(df.iterrows(), total=len(df), desc=f"futures_{m}"):
            try:
                rsi_val = float(row['rsi_14']) if pd.notna(row['rsi_14']) else None
            except ValueError:
                rsi_val = None
            cursor.execute(sql, (rsi_val, row['datetime']))
        db.commit()
        print(f"✅ futures_{m} 업데이트 완료: {len(df)} rows")

    print("🎉 모든 분봉 RSI_14 업데이트 완료")

db.close()
