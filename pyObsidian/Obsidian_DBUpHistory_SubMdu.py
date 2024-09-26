import re

def get_market_summary(md_path, md_date, md_file, cursor, db):

    # SQL 쿼리를 작성합니다. 파일명일자보다 작거나 같은 가장 큰 날짜를 calendar 테이블에서 조회합니다.
    sql = f"select max(date) from calendar where date < '{md_date}'"

    # SQL 쿼리를 실행하고 결과를 가져옵니다.
    cursor.execute(sql)
    result = cursor.fetchone()

    # 결과가 존재하면, 그 값을 date 변수에 저장합니다. 그렇지 않으면, 에러 메시지를 출력하고 프로그램을 종료합니다.
    if result:
        date = result[0].strftime('%Y-%m-%d')
    else:
        print("No date found for the given file name.")
        exit()

    # md 파일을 열고 읽습니다.
    with open(md_path, 'r', encoding='utf-8') as f:
        md_text = f.read()

    # md 파일에서 # 시장 정리 이후의 텍스트를 추출합니다. # 시장 정리는 한 줄에만 있고, 그 다음 줄부터 텍스트가 시작된다고 가정합니다.
    # market_summary = re.search(r'# 시장 정리\n(.*)', md_text, re.DOTALL)
    # market_summary = re.search(r'# 시장 정리\n(.*?)\n# 주요 뉴스', md_text, re.DOTALL)
    market_summary = re.search(r'# 시장 정리\n(.*?)(\n# 주요 뉴스|$)', md_text, re.DOTALL)

    # market_summary 데이터가 존재하면, 그 값을 group(1) 메소드로 가져옵니다. 그렇지 않으면, 데이터 없다는 메시지를 출력하고 스킵합니다.
    if market_summary:
        market_summary = market_summary.group(1)
    else:
        print(f"No market summary data found for {md_file}. Skipping this file.")
        return

    # 텍스트를 줄 단위로 분리합니다.
    lines = market_summary.split('\n')

    # 해당 일자에 등록된 히스토리가 있으면 삭제, 재등록 합니다.
    sql_query = f"DELETE FROM stock_history WHERE date='{date}'"
    print(f"Executing: {sql_query}")
    cursor.execute(sql_query)
    # 각 줄에 대해 반복적으로 처리합니다.

    for i, line in enumerate(lines):
        
        # 라인번호를 3자리로 채우기
        line_number = str(i + 1).zfill(3)
        
        # 날짜와 라인번호를 이어붙이기
        id = md_date + line_number

        line = line.replace('\u200B', '') #'zero-width space’ 특수문자 제거
        line = line.replace('---', '') #'---’ 제거
        line = re.sub(' +', ' ', line) # 공백이 하나 이상인 부분을 하나의 공백으로 치환

        # 줄이 비어 있지 않으면, stock_history 테이블에 date와 history 컬럼에 값을 삽입하기 위한 SQL 쿼리를 작성합니다.
        if line.strip(): #빈줄 제거 처리
            sql = f"INSERT INTO stock_history (id, date, history, report_date, remark, create_dtime) VALUES (%s, %s, %s,  %s, %s, now())"
            # SQL 쿼리를 실행하고 데이터베이스에 반영합니다.
            # print(f"Executing: {sql % (id, date, line, md_date, md_file)}")
            cursor.execute(sql, (id, date, line, md_date, md_file))
            db.commit()

            # 프로그램이 성공적으로 완료되었음을 출력합니다.
            print(f"The program has successfully inserted the line '{line}' of {md_file} into the stock_history table.")