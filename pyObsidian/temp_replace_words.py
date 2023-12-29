# md 파일에서 # 시장 정리 이후 텍스트를 읽어서 테이블에 등록하는 프로그램

# 필요한 모듈을 임포트합니다.
import re # 정규식을 사용하기 위한 모듈
import os # 파일과 디렉토리를 다루기 위한 모듈
import pymysql
import configparser
from datetime import datetime
from collections import Counter

# 설정 파일 읽기
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/PyObsidian/database_config.ini')

# MariaDB 연결
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

# 커서 생성
cursor = db.cursor()

def replace_words(content, words):

    new_content = []
    for line in content:
        for word in words:
            line = re.sub(r'(?<![[\가-힣a-zA-Z)\]\[])' + re.escape(word), '[[' + word + ']]', line) # 너무 느림 + 경고
        new_content.append(line)
    return new_content

# 처리 시작
start_time = datetime.now()
print(f"처리 시작 시간: {start_time}")

# 오늘 날짜와 가장 가까운 날짜를 구합니다.
query_date = f"SELECT MAX(date) FROM daily_price WHERE date <= (select DATE_FORMAT(DATE_ADD(now(), INTERVAL 0 DAY), '%Y%m%d'))"

cursor.execute(query_date)
closest_date = cursor.fetchone()[0].decode('utf-8')


# 쿼리를 실행하고 결과를 가져옵니다.

query = f"""
        SELECT name
        FROM (
                SELECT 'CJCGV' name UNION 
                SELECT 'CJENM' UNION 
                SELECT 'CJ바이오사이언스' UNION 
                SELECT 'CSA코스믹' UNION 
                SELECT 'HLD&I' UNION 
                SELECT 'JYPEnt.' UNION 
                SELECT 'KGETS' UNION 
                SELECT 'KH건설' UNION 
                SELECT 'KH전자' UNION 
                SELECT 'KH필룩스' UNION 
                SELECT 'LSELECTRIC' UNION 
                SELECT 'SMC&C' UNION 
                SELECT 'SMLifeDesign' UNION 
                SELECT 'THEE&M' UNION 
                SELECT 'THEMIDONG' UNION 
                SELECT 'YGPLUS' UNION 
                SELECT '리더스기술투자' UNION 
                SELECT '미래에셋대우스팩5호' UNION 
                SELECT '블레이드Ent' UNION 
                SELECT '블루베리NFT' UNION 
                SELECT '비보존제약' UNION 
                SELECT '비보존헬스케어' UNION 
                SELECT '신세계I&C' UNION 
                SELECT '에이프로젠H&G' UNION 
                SELECT '에이프로젠KIC' UNION 
                SELECT '에이프로젠MED' UNION 
                SELECT '지앤비에스에코' UNION 
                SELECT '포스코ICT' UNION 
                SELECT 'APS홀딩스' UNION 
                SELECT 'BNGT' UNION 
                SELECT 'EV수성' UNION 
                SELECT 'KPX생명과학' UNION 
                SELECT 'NHN한국사이버결제' UNION 
                SELECT 'OCI' UNION 
                SELECT 'SNT중공업' UNION 
                SELECT 'WI' UNION 
                SELECT '가온미디어' UNION 
                SELECT '노터스' UNION 
                SELECT '다나와' UNION 
                SELECT '다믈멀티미디어' UNION 
                SELECT '다올인베스트먼트' UNION 
                SELECT '대우조선해양' UNION 
                SELECT '대한그린파워' UNION 
                SELECT '동국제강' UNION 
                SELECT '디딤' UNION 
                SELECT '디아크' UNION 
                SELECT '디엑스앤브이엑스' UNION 
                SELECT '마이크로프랜드' UNION 
                SELECT '마인즈랩' UNION 
                SELECT '바른전자' UNION 
                SELECT '브이티지엠피' UNION 
                SELECT '블루베리 NFT' UNION 
                SELECT '삼강엠앤티' UNION 
                SELECT '삼영화학' UNION 
                SELECT '센트랄모텍' UNION 
                SELECT '솔루에타' UNION 
                SELECT '슈프리마아이디' UNION 
                SELECT '쌍용차' UNION 
                SELECT '씨앤투스성진' UNION 
                SELECT '에스맥' UNION 
                SELECT '에스케이증권7호스팩' UNION 
                SELECT '에이텍티앤' UNION 
                SELECT '엔에스' UNION 
                SELECT '엘비루셈' UNION 
                SELECT '엘비세미콘' UNION 
                SELECT '엠피대산' UNION 
                SELECT '영창케미칼' UNION 
                SELECT '원방테크' UNION 
                SELECT '원익피앤이' UNION 
                SELECT '이엔드디' UNION 
                SELECT '일진머티리얼즈' UNION 
                SELECT '조선내화' UNION 
                SELECT '중앙디앤엠' UNION 
                SELECT '지앤비에스엔지니어링' UNION 
                SELECT '지엔원에너지' UNION 
                SELECT '코아스템' UNION 
                SELECT '코프라' UNION 
                SELECT '큐로' UNION 
                SELECT '크로바하이텍' UNION 
                SELECT '크리스탈지노믹스' UNION 
                SELECT '파나진' UNION 
                SELECT '포스코 ICT' UNION 
                SELECT '포스코케미칼' UNION 
                SELECT '프로스테믹스' UNION 
                SELECT '피에스엠씨' UNION 
                SELECT '한국조선해양' UNION 
                SELECT '한국프랜지' UNION 
                SELECT '현대건설기계' UNION 
                SELECT '현대그린푸드' UNION 
                SELECT '현대두산인프라코어' UNION 
                SELECT '현대일렉트릭' UNION 
                SELECT '현대중공업' UNION 
                SELECT '참존글로벌' UNION 
                SELECT '코리아에스이' UNION 
                SELECT 'IBKS제12호스팩' UNION 
                SELECT 'cj헬로' UNION 
                SELECT 'GMR 머티리얼' UNION 
                SELECT 'GMR머티리얼즈' UNION 
                SELECT 'GV' UNION 
                SELECT 'HDC아이콘트롤스' UNION 
                SELECT 'KG동부제철' UNION 
                SELECT 'KMH하이텍' UNION 
                SELECT 'KTB투자증권' UNION 
                SELECT 'KTH' UNION 
                SELECT 'LG상사' UNION 
                SELECT 'POSCO' UNION 
                SELECT 'S&T모티브' UNION 
                SELECT 'skc코오롱pi' UNION 
                SELECT 'SK바이오랜드' UNION 
                SELECT 'W홀딩컴퍼니' UNION 
                SELECT '강원' UNION 
                SELECT '갤럭시아컴즈' UNION 
                SELECT '글로스퍼랩스' UNION 
                SELECT '금호산업' UNION 
                SELECT '나노메딕스' UNION 
                SELECT '나노스' UNION 
                SELECT '나인컴플렉스' UNION 
                SELECT '남영비비안' UNION 
                SELECT '네오팩트' UNION 
                SELECT '넥스트BT' UNION 
                SELECT '넥스트사이언스' UNION 
                SELECT '넥슨지티' UNION 
                SELECT '녹십자랩셀' UNION 
                SELECT '녹십자셀' UNION 
                SELECT '뉴지랩' UNION 
                SELECT '뉴프라이드' UNION 
                SELECT '대림산업' UNION 
                SELECT '대호피앤씨' UNION 
                SELECT '데일리블록체인' UNION 
                SELECT '데코앤이' UNION 
                SELECT '동부제철' UNION 
                SELECT '동양네트웍스' UNION 
                SELECT '동양물산' UNION 
                SELECT '두산건설' UNION 
                SELECT '두산솔루스' UNION 
                SELECT '두산인프라코어' UNION 
                SELECT '두산중공업' UNION 
                SELECT '두올산업' UNION 
                SELECT '디에스티' UNION 
                SELECT '디에이치피코리아' UNION 
                SELECT '디오스텍' UNION 
                SELECT '디지탈옵틱' UNION 
                SELECT '디케이디앤아이' UNION 
                SELECT '디피씨' UNION 
                SELECT '라이브파이낸셜' UNION 
                SELECT '라이브플렉스' UNION 
                SELECT '럭슬' UNION 
                SELECT '로고스바이오' UNION 
                SELECT '루미마이크로' UNION 
                SELECT '마이크로텍' UNION 
                SELECT '매직마이크로' UNION 
                SELECT '맥스로텍' UNION 
                SELECT '메디파트너생명공학' UNION 
                SELECT '메디포럼제약' UNION 
                SELECT '메탈라이프' UNION 
                SELECT '미래SCI' UNION 
                SELECT '바른테크놀로지' UNION 
                SELECT '바이오리더스' UNION 
                SELECT '바이오제네틱스' UNION 
                SELECT '백광소재' UNION 
                SELECT '버추얼텍' UNION 
                SELECT '보령제약' UNION 
                SELECT '부산가스' UNION 
                SELECT '블라썸엠앤씨' UNION 
                SELECT '블러썸엠앤씨' UNION 
                SELECT '비티원' UNION 
                SELECT '삼광글라스' UNION 
                SELECT '삼본전자' UNION 
                SELECT '상상인더스트리' UNION 
                SELECT '샘코' UNION 
                SELECT '서연전자' UNION 
                SELECT '선데이토즈' UNION 
                SELECT '세미콘라이트' UNION 
                SELECT '세원' UNION 
                SELECT '세원셀론텍' UNION 
                SELECT '센트럴바이오' UNION 
                SELECT '소리바다' UNION 
                SELECT '솔트웍스' UNION 
                SELECT '슈퍼스비앤피' UNION 
                SELECT '슈펙스비앤피' UNION 
                SELECT '스카이이앤엠' UNION 
                SELECT '스타모빌리티' UNION 
                SELECT '신스타임즈' UNION 
                SELECT '신화실업' UNION 
                SELECT '실리콘웍스' UNION 
                SELECT '쎄미시스코' UNION 
                SELECT '씨엠에스에듀' UNION 
                SELECT '씨트리' UNION 
                SELECT '씨티젠' UNION 
                SELECT '씨티케이코스메틱스' UNION 
                SELECT '아이씨케이' UNION 
                SELECT '아이에이네트웍스' UNION 
                SELECT '아이엠텍' UNION 
                SELECT '아이원스' UNION 
                SELECT '알이피' UNION 
                SELECT '액트' UNION 
                SELECT '에스모' UNION 
                SELECT '에스모 머티리얼즈' UNION 
                SELECT '에스모머티리얼즈' UNION 
                SELECT '에스엔텍' UNION 
                SELECT '에스엔텍비엠' UNION 
                SELECT '에이아이비트' UNION 
                SELECT '에이치엔티' UNION 
                SELECT '에이치엘비' UNION 
                SELECT '에이치엘비생명과학' UNION 
                SELECT '에이치엘비파워' UNION 
                SELECT '에이프로KIC' UNION 
                SELECT '에이프로젠KIC' UNION 
                SELECT '에코마이스터' UNION 
                SELECT '엔케이물산' UNION 
                SELECT '연이정보통신' UNION 
                SELECT '영인프런티어' UNION 
                SELECT '영흥철강' UNION 
                SELECT '옴니텔' UNION 
                SELECT '옵토팩' UNION 
                SELECT '우노앤컴퍼니' UNION 
                SELECT '우리들제약' UNION 
                SELECT '우리들휴브레인' UNION 
                SELECT '우리조명' UNION 
                SELECT '우림기계' UNION 
                SELECT '우성사료' UNION 
                SELECT '웅진코웨이' UNION 
                SELECT '위니아딤채' UNION 
                SELECT '위지웍스튜디오' UNION 
                SELECT '유니맥스글로벌' UNION 
                SELECT '이그잭스' UNION 
                SELECT '이노와이즈' UNION 
                SELECT '이더블유케이' UNION 
                SELECT '이디티' UNION 
                SELECT '이베스트투자' UNION 
                SELECT '이에스브이' UNION 
                SELECT '이에스에이' UNION 
                SELECT '이엑스티' UNION 
                SELECT '이지웰페어' UNION 
                SELECT '이테크건설' UNION 
                SELECT '인터파크' UNION 
                SELECT '인터파크홀딩스' UNION 
                SELECT '인프라웨어' UNION 
                SELECT '자안바이오' UNION 
                SELECT '전파기지국' UNION 
                SELECT '제낙스' UNION 
                SELECT '제이스테판' UNION 
                SELECT '제이엘케이인스펙션' UNION 
                SELECT '제이콘텐트리' UNION 
                SELECT '제일제강' UNION 
                SELECT '젬백스지오' UNION 
                SELECT '조이맥스' UNION 
                SELECT '중앙오션' UNION 
                SELECT '지코' UNION 
                SELECT '지트리비앤티' UNION 
                SELECT '청호컴넷' UNION 
                SELECT '초록뱀' UNION 
                SELECT '카리스국보' UNION 
                SELECT '케이씨씨글라스' UNION 
                SELECT '케이알피앤이' UNION 
                SELECT '코너스톤네트웍스' UNION 
                SELECT '코닉글로리' UNION 
                SELECT '코렌' UNION 
                SELECT '코오롱머티리얼' UNION 
                SELECT '크리스탈' UNION 
                SELECT '테라셈' UNION 
                SELECT '티탑스' UNION 
                SELECT '파마리서치프로덕트' UNION 
                SELECT '파수닷컴' UNION 
                SELECT '팍스넷' UNION 
                SELECT '팜스웰바이오' UNION 
                SELECT '팬엔터프라이즈' UNION 
                SELECT '포비스티앤씨' UNION 
                SELECT '포티스' UNION 
                SELECT '퓨전' UNION 
                SELECT '퓨전데이타' UNION 
                SELECT '퓨쳐스트림네트웍스' UNION 
                SELECT '피앤이솔루션' UNION 
                SELECT '필로시스헬스케어' UNION 
                SELECT '필룩스' UNION 
                SELECT '필링크' UNION 
                SELECT '하이셈' UNION 
                SELECT '한국바이오젠' UNION 
                SELECT '한국제지' UNION 
                SELECT '한국특수형강' UNION 
                SELECT '한류AI센터' UNION 
                SELECT '한솔시큐어' UNION 
                SELECT '한일네트웍스' UNION 
                SELECT '한진중공업' UNION 
                SELECT '한프' UNION 
                SELECT '한화갤러리아타임월드' UNION 
                SELECT '한화케미칼' UNION 
                SELECT '해마로푸드서비스' UNION 
                SELECT '해태제과제품' UNION 
                SELECT '현대사료' UNION 
                SELECT '현대에이치씨엔' UNION 
                SELECT '현대엘리베이터' UNION 
                SELECT '현대중공업지주' UNION 
                SELECT '현성바이탈' UNION 
                SELECT '현진소재' UNION 
                SELECT '화신테크' UNION 
                SELECT '화이브라더스코리아' UNION 
                SELECT '휘닉스소재' UNION 
                SELECT '휠라코리아' UNION 
                SELECT '어반리튬' UNION 
                SELECT '비엔지티' UNION 
                SELECT '애경유화' UNION 
                SELECT '엠젠플러스' UNION 
                SELECT 'AJ렌터카' UNION 
                SELECT '메리츠종금증권' UNION 
                SELECT '에이프로젠 KIC' UNION 
                SELECT '에이프로젠 MED' UNION 
                SELECT 'SG충방' UNION 
                SELECT '대동공업' UNION 
                SELECT '한국테크놀로지그룹' UNION 
                SELECT '빅히트' UNION 
                SELECT '포스코강판' UNION 
                SELECT 'GS홈쇼핑' UNION 
                SELECT 'SK머티리얼즈' UNION 
                SELECT '에이치엘비제약' UNION 
                SELECT '롯데푸드' UNION 
                SELECT '만도' UNION 
                SELECT '넷게임즈' UNION 
                SELECT '경남바이오파마' UNION 
                SELECT 'OQP' UNION 
                SELECT '민앤지' UNION 
                SELECT '천랩' UNION 
                SELECT '큐브앤컴퍼니' UNION 
                SELECT '감마누' UNION 
                SELECT '한컴MDS' UNION 
                SELECT '코디엠' UNION 
                SELECT '자안코스메틱' UNION 
                SELECT '아이엠이연이' UNION 
                SELECT '엔에스쇼핑' UNION 
                SELECT '게임빌' UNION 
                SELECT '광진윈텍' UNION 
                SELECT '케이티비네트워크' UNION 
                SELECT '마이더스AI' UNION 
                SELECT 'KMH' UNION 
                SELECT '현대사료' UNION 
                SELECT '백광소재' UNION 
                SELECT '알비케이그룹' UNION 
                SELECT '우리들제약'  UNION 
				SELECT 'LG엔솔'  ) A
        ORDER BY name desc
        """
cursor.execute(query)
fetch_words = [item[0] for item in cursor.fetchall()]
words = [word.decode('utf-8') for word in fetch_words]


# 저장할 폴더 지정
md_folder = 'D:/Obsidian/Trader Sophia/♣ Signal Report/siri'

# 폴더 안에 있는 모든 md 파일의 이름을 리스트로 가져옵니다.
md_files = os.listdir(md_folder)

# 리스트에 있는 각 파일에 대해 반복적으로 처리합니다.
for md_file in md_files:

    # 파일의 경로와 이름을 결합합니다.
    md_path = os.path.join(md_folder, md_file)
    print(md_path)

    # md 파일 읽기
    with open(md_path, 'r', encoding='utf-8') as f:
        content = f.readlines()
        print(content)
        
    # 단어 치환
    content = replace_words(content, words)
        
    # 치환 결과 md 파일 저장
    with open(md_path,'w',encoding='utf-8') as f:
        f.writelines(content)

# 처리 종료
end_time = datetime.now()
print(f"처리 종료 시간: {end_time}")
