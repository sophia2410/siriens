import re
import sys
import time
import configparser
from datetime import datetime, date
from urllib.parse import urlparse, parse_qs

import pymysql
import requests
from bs4 import BeautifulSoup


# =============================================================================
# 설정
# =============================================================================

DB_CONFIG_PATH = 'E:/Project/202410/www/boot/common/db/database_config.ini'

WOWTV_LIVE_URL = 'https://www.wowtv.co.kr/LiveCenter/Live/'
YOUTUBE_OEMBED_URL = 'https://www.youtube.com/oembed'

SOURCE_NAME = '한국경제TV'

REQUEST_TIMEOUT = 20
REQUEST_INTERVAL = 0.2

HEADERS = {
    'User-Agent': (
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        'AppleWebKit/537.36 (KHTML, like Gecko) '
        'Chrome/152.0.0.0 Safari/537.36'
    ),
    'Accept-Language': 'ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
}


# =============================================================================
# DB
# =============================================================================

def get_db():
    config = configparser.ConfigParser()
    config.read(DB_CONFIG_PATH, encoding='utf-8')

    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset'),
        autocommit=False
    )


# =============================================================================
# 문자열 처리
# =============================================================================

def normalize_text(text):
    if text is None:
        return ''

    text = str(text)
    text = text.replace('\xa0', ' ')
    text = re.sub(r'\s+', ' ', text)

    return text.strip()


def extract_issue_date(raw_title):
    """
    제목의 [09/04 #당잠사] 형식에서 날짜를 추출한다.
    """

    m = re.search(
        r'(\d{1,2})\s*/\s*(\d{1,2})\s*#\s*당잠사',
        raw_title or '',
        re.IGNORECASE
    )

    if not m:
        return None

    month = int(m.group(1))
    day = int(m.group(2))

    today = date.today()

    try:
        candidate = date(
            today.year,
            month,
            day
        )
    except ValueError:
        return None

    # 연초에 전년도 12월 자료를 읽는 경우 보정
    if (candidate - today).days > 45:
        try:
            candidate = date(
                today.year - 1,
                month,
                day
            )
        except ValueError:
            return None

    return candidate.strftime('%Y-%m-%d')


def parse_title_and_tags(raw_title):
    """
    예:
    [09/04 #당잠사] 월러 ... | 테슬라 ... | #테슬라 #엔비디아 #메타

    결과:
      title = 월러 ... | 테슬라 ...
      tags  = 테슬라,엔비디아,메타
    """

    if not raw_title:
        return '', None

    title = normalize_text(raw_title)

    # 앞부분 [09/04 #당잠사], [🔴09/04 #당잠사] 제거
    title = re.sub(
        r'^\s*\[[^\]]*당잠사[^\]]*\]\s*',
        '',
        title,
        flags=re.IGNORECASE
    ).strip()

    # 태그 추출
    raw_tags = re.findall(
        r'#([^\s#|ㅣ]+)',
        title
    )

    tags = []
    seen = set()

    for tag in raw_tags:
        tag = tag.strip().strip(',').strip()

        if not tag:
            continue

        if tag.lower() == '당잠사':
            continue

        if tag not in seen:
            seen.add(tag)
            tags.append(tag)

    # 제목에서 해시태그 제거
    title = re.sub(
        r'#[^\s#|ㅣ]+',
        '',
        title
    )

    # 끝에 남은 구분자 / 쉼표 / 공백 제거
    title = re.sub(
        r'[\s|ㅣ,]+$',
        '',
        title
    ).strip()

    # 유사한 세로 구분자를 DB에서는 | 로 통일
    title = re.sub(
        r'\s*[|ㅣ]\s*',
        ' | ',
        title
    )

    # 연속 공백 정리
    title = re.sub(
        r'\s+',
        ' ',
        title
    ).strip()

    tags_text = ','.join(tags) if tags else None

    return title, tags_text


# =============================================================================
# YouTube URL / video_id
# =============================================================================

def extract_video_id(url):
    if not url:
        return None

    url = url.strip()

    try:
        parsed = urlparse(url)
        host = parsed.netloc.lower()

        if 'youtu.be' in host:
            video_id = parsed.path.strip('/').split('/')[0]
            return video_id or None

        if 'youtube.com' in host:
            qs = parse_qs(parsed.query)

            if qs.get('v'):
                return qs['v'][0]

            path_parts = [
                x for x in parsed.path.split('/')
                if x
            ]

            for marker in ('shorts', 'embed', 'live'):
                if marker in path_parts:
                    idx = path_parts.index(marker)

                    if idx + 1 < len(path_parts):
                        return path_parts[idx + 1]

    except Exception:
        pass

    m = re.search(
        r'(?:v=|youtu\.be/|shorts/|embed/|live/)'
        r'([A-Za-z0-9_-]{6,})',
        url
    )

    if m:
        return m.group(1)

    return None


# =============================================================================
# YouTube oEmbed
# =============================================================================

def fetch_youtube_title(video_id):
    """
    yt-dlp 상세조회 대신 YouTube oEmbed API에서
    실제 YouTube 제목을 가져온다.

    영상 재생 정보가 필요 없기 때문에
    'page needs to be reloaded' 문제를 피하면서
    원래 제목의 |, 따옴표, 공백 등을 보존할 수 있다.
    """

    video_url = f'https://www.youtube.com/watch?v={video_id}'

    response = requests.get(
        YOUTUBE_OEMBED_URL,
        params={
            'url': video_url,
            'format': 'json'
        },
        headers=HEADERS,
        timeout=REQUEST_TIMEOUT
    )

    if response.status_code != 200:
        return None

    try:
        data = response.json()
    except Exception:
        return None

    title = data.get('title')

    if not title:
        return None

    return normalize_text(title)


# =============================================================================
# 한국경제TV 공식 페이지 수집
# =============================================================================

def fetch_dangjamsa_items():
    """
    1. 한국경제TV 공식 페이지에서 최신 당잠사 YouTube 링크를 얻는다.
    2. 각 video_id에 대해 YouTube oEmbed로 실제 제목을 다시 얻는다.
    3. oEmbed 실패 시 공식 페이지 제목으로 fallback 한다.
    """

    response = requests.get(
        WOWTV_LIVE_URL,
        headers=HEADERS,
        timeout=REQUEST_TIMEOUT
    )

    response.raise_for_status()

    if not response.encoding or response.encoding.lower() == 'iso-8859-1':
        response.encoding = response.apparent_encoding

    soup = BeautifulSoup(
        response.text,
        'html.parser'
    )

    raw_items = []

    for a in soup.find_all('a', href=True):
        page_title = normalize_text(
            a.get_text(' ', strip=True)
        )

        if '당잠사' not in page_title:
            continue

        if not re.search(
            r'\d{1,2}\s*/\s*\d{1,2}\s*#\s*당잠사',
            page_title,
            re.IGNORECASE
        ):
            continue

        href = a.get('href', '').strip()

        if href.startswith('//'):
            href = 'https:' + href
        elif href.startswith('/'):
            href = 'https://www.wowtv.co.kr' + href

        video_id = extract_video_id(href)

        if not video_id:
            continue

        raw_items.append({
            'page_title': page_title,
            'video_id': video_id,
            'video_url': (
                f'https://www.youtube.com/watch?v={video_id}'
            )
        })

    # video_id 중복 제거
    unique = {}

    for item in raw_items:
        unique[item['video_id']] = item

    raw_items = list(unique.values())

    items = []

    for idx, item in enumerate(raw_items, 1):
        video_id = item['video_id']
        page_title = item['page_title']

        youtube_title = None

        try:
            youtube_title = fetch_youtube_title(
                video_id
            )
        except Exception as e:
            print(
                f'[제목조회 경고] {video_id}: {e}'
            )

        # oEmbed의 실제 YouTube 제목을 우선 사용
        raw_title = youtube_title or page_title

        issue_date = extract_issue_date(
            raw_title
        )

        # oEmbed 제목의 날짜형식이 예상과 다를 경우
        # 공식 페이지 제목에서 한 번 더 시도
        if not issue_date:
            issue_date = extract_issue_date(
                page_title
            )

        if not issue_date:
            print(
                f'[SKIP] 날짜 추출 실패: '
                f'{video_id} | {raw_title}'
            )
            continue

        title, tags = parse_title_and_tags(
            raw_title
        )

        items.append({
            'issue_date': issue_date,
            'title': title,
            'tags': tags,
            'video_id': video_id,
            'video_url': item['video_url'],
            'title_source': (
                'YouTube'
                if youtube_title
                else 'WOWTV'
            )
        })

        if REQUEST_INTERVAL > 0:
            time.sleep(REQUEST_INTERVAL)

    # 날짜 최신순
    items.sort(
        key=lambda x: (
            x['issue_date'],
            x['video_id']
        )
    )

    return items


# =============================================================================
# DB 저장
# =============================================================================

def save_issue(cursor, item):
    sql = """
        INSERT INTO market_issue
        (
            issue_date,
            source_name,
            title,
            headline,
            tags,
            video_id,
            video_url
        )
        VALUES
        (
            %s,
            %s,
            %s,
            NULL,
            %s,
            %s,
            %s
        )
        ON DUPLICATE KEY UPDATE
            issue_date = VALUES(issue_date),
            source_name = VALUES(source_name),
            title = VALUES(title),
            tags = VALUES(tags),
            video_url = VALUES(video_url)
    """

    cursor.execute(
        sql,
        (
            item['issue_date'],
            SOURCE_NAME,
            item['title'],
            item['tags'],
            item['video_id'],
            item['video_url']
        )
    )


# =============================================================================
# 메인
# =============================================================================

def main():
    print('=' * 100)
    print('한국경제TV #당잠사 최신정보 수집')
    print('WOWTV 최신목록 + YouTube oEmbed 원본제목')
    print('=' * 100)

    items = fetch_dangjamsa_items()

    if not items:
        print('당잠사 영상을 찾지 못했습니다.')
        return

    print(
        f'당잠사 발견: {len(items)}건'
    )
    print(
        f'최신 일자: {items[0]["issue_date"]}'
    )
    print()

    db = get_db()
    cursor = db.cursor()

    saved = 0
    errors = 0

    try:
        for i, item in enumerate(items, 1):
            try:
                save_issue(
                    cursor,
                    item
                )

                db.commit()
                saved += 1

                print(
                    f'[{i}] 저장/갱신: '
                    f'{item["issue_date"]} | '
                    f'{item["video_id"]} | '
                    f'{item["title"]} | '
                    f'tags={item["tags"]} | '
                    f'source={item["title_source"]}'
                )

            except Exception as e:
                db.rollback()
                errors += 1

                print(
                    f'[{i}] ERROR '
                    f'{item["video_id"]}: {e}'
                )

    finally:
        cursor.close()
        db.close()

    print()
    print('=' * 100)
    print(
        f'완료 - 저장/갱신 {saved}건, '
        f'오류 {errors}건'
    )
    print('=' * 100)


if __name__ == '__main__':
    try:
        main()

    except KeyboardInterrupt:
        print('\n사용자에 의해 중단되었습니다.')
        sys.exit(1)
