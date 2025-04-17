#!/usr/bin/env python3
# 파일명: futures_pnl_calculator.py
# 선물매매 손익계산 프로그램.

import configparser
import pymysql
import pandas as pd
import os
import sys
sys.stdout.reconfigure(encoding='utf-8')

def main():
    # 1) DB/파일에서 체결 데이터 로드 & 전처리
    config = configparser.ConfigParser()
    config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

    db = pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset')
    )

    with db.cursor() as cursor:
        sql = "SELECT max(date) AS date FROM calendar a WHERE date <= now()"
        cursor.execute(sql)
        date_obj = cursor.fetchone()[0]
        date_str = date_obj.strftime('%Y-%m-%d')

    db.close()

    file_path = os.path.join(
        r"E:\Project\202410\data\_futures",
        f"futures_{date_str}.xlsx"
    )
    
    # # 디버깅 시 특정 날짜 고정
    # file_path = os.path.join(
    #     r"E:\Project\202410\data\_futures",
    #     f"futures_2025-03-31.xlsx"
    # )

    df = pd.read_excel(file_path)
    df = df[df['상태'] == '체결'].copy()  # 체결된 행만
    df['주문시간'] = pd.to_datetime(df['주문시간'], format='%H:%M:%S')
    df = df.iloc[::-1]  # 역순 정렬 (원하실 경우)

    # 2) 손익 계산
    FUTURES_MULTIPLIER = 250000
    COMMISSION_RATE = 0.00025104 / 100  # 0.00025104%

    results = []

    for i in range(0, len(df), 2):
        if i + 1 >= len(df):
            break

        row_open  = df.iloc[i]
        row_close = df.iloc[i+1]

        def to_side(text):
            if text.startswith('매도'):
                return '매도'
            elif text.startswith('매수'):
                return '매수'
            return 'ERR'

        open_side  = to_side(row_open['구분'])
        close_side = to_side(row_close['구분'])

        open_price  = row_open['체결가']
        close_price = row_close['체결가']
        open_qty    = row_open['체결량']
        close_qty   = row_close['체결량']
        open_time   = row_open['주문시간']
        close_time  = row_close['주문시간']

        qty = min(open_qty, close_qty)

        # (1) 기본 손익 계산
        if open_side == '매수' and close_side == '매도':
            point_diff  = close_price - open_price
            gross_pnl   = point_diff * FUTURES_MULTIPLIER * qty
        elif open_side == '매도' and close_side == '매수':
            point_diff  = open_price - close_price
            gross_pnl   = point_diff * FUTURES_MULTIPLIER * qty
        else:
            point_diff  = None
            gross_pnl   = None

        # (2) 수수료(양방향)
        notional_open  = open_price  * FUTURES_MULTIPLIER * qty
        notional_close = close_price * FUTURES_MULTIPLIER * qty
        commission     = (notional_open + notional_close) * COMMISSION_RATE

        # (3) 최종 손익(순손익)
        if gross_pnl is not None:
            net_pnl = gross_pnl - commission
        else:
            net_pnl = None

        # (4) 오픈-청산 시간 차이(분 단위)
        time_diff_minutes = (close_time - open_time).total_seconds() / 60.0

        # (5) 날짜 제외, HH:MM:SS만 표시
        open_time_str  = open_time.strftime('%H:%M:%S')
        close_time_str = close_time.strftime('%H:%M:%S')

        results.append({
            '오픈시간'   : open_time_str,
            '오픈구분'   : open_side,
            '오픈포인트' : open_price,
            '청산시간'   : close_time_str,
            '청산구분'   : close_side,
            '청산포인트' : close_price,
            '수량'       : qty,
            '차이포인트' : point_diff,
            '수수료'     : commission,
            '손익'       : net_pnl,  # 순손익
            '오픈-청산(분)': f"{time_diff_minutes:.1f}"
        })

    result_df = pd.DataFrame(results)

    # --(A) 인덱스를 1부터 시작하도록 조정--
    result_df.index = result_df.index + 1

    # 3) 데이터타입 변환
    numeric_cols = ['수량', '차이포인트', '수수료', '손익', '오픈포인트', '청산포인트']
    for col in numeric_cols:
        if col in result_df.columns:
            result_df[col] = pd.to_numeric(result_df[col], errors='coerce')

    # 4) 컬럼별 색상 처리 함수
    def color_side(val):
        """매수 → 빨강, 매도 → 파랑"""
        if val == '매수':
            return 'color:red; font-weight:bold;'
        elif val == '매도':
            return 'color:blue; font-weight:bold;'
        else:
            return ''

    def color_pnl(val):
        """손익이 +이면 빨강, -이면 파랑"""
        import math
        if val is None or pd.isna(val):
            return ''
        elif val > 0:
            return 'color:red;'
        elif val < 0:
            return 'color:blue;'
        else:
            return ''

    # 5) 스타일 지정
    styled_df = (
        result_df.style
        .applymap(color_side, subset=['오픈구분','청산구분'])  # 매수/매도 색상
        .applymap(color_pnl, subset=['손익'])                  # 손익 색상
        # (a) 포인트(오픈포인트/청산포인트/차이포인트)는 소수점 2자리
        .format("{:,.2f}", subset=['오픈포인트','청산포인트','차이포인트'])
        # (b) 수량/수수료/손익은 소수점 없이
        .format("{:,.0f}", subset=['수량','수수료','손익'])
        # (c) 좌측 정렬: 포인트·금액 컬럼
        .set_properties(**{'text-align': 'right'}, 
                        subset=['오픈포인트','청산포인트','차이포인트','수수료','손익'])
    )

    # 6) HTML 변환 (인덱스 표시하므로 index=True)
    html_table = styled_df.to_html(index=True)

    # 7) 전체 손익 합계 (순손익 기준)
    total_pnl = result_df['손익'].sum(skipna=True)

    # 8) 출력
    print("<style>table{border-collapse:collapse;font-size:13px;width:40%;}"
        "th,td{border:1px solid #ddd;padding:8px;text-align:center;white-space:nowrap;}"
        "th{background-color:#f2f2f2;}</style>")

    print(html_table)

    if total_pnl > 0:
        total_color = 'red'
    elif total_pnl < 0:
        total_color = 'blue'
    else:
        total_color = 'black'  # 0원일 경우 등

    # 9) 손익 출력
    print(f'<p>총 손익(수수료 차감 후): <p style="color:{total_color}; font-weight:bold;">{total_pnl:,.0f} 원</p></p>')

if __name__ == "__main__":
    main()
