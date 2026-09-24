import re
import sys
import configparser
from datetime import date
from urllib.parse import urlparse, parse_qs

import pymysql
import yt_dlp


DB_CONFIG_PATH = 'E:/Project/202410/www/boot/common/db/database_config.ini'

PLAYLIST_URL = (
    'https://www.youtube.com/watch?v=rHTpgVhpXIY'
    '&list=PLh6kUo7pqm_69KUuM0hOj-ClLo6GWdgUH'
)

START_YEAR = date.today().year


# =============================================================================
# DB
# =============================================================================

def get_db():
    cfg = configparser.ConfigParser()
    cfg.read(DB_CONFIG_PATH, encoding='utf-8')

    return pymysql.connect(
        host=cfg.get('database', 'host'),
        user=cfg.get('database', 'user'),
        password=cfg.get('database', 'password'),
        db=cfg.get('database', 'db'),
        charset=cfg.get('database', 'charset'),
        autocommit=False
    )


def create_import_table(cur):
    cur.execute("""
        CREATE TABLE IF NOT EXISTS market_issue_import (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            playlist_index INT DEFAULT NULL,
            video_id VARCHAR(100) NOT NULL,
            video_url VARCHAR(1000) DEFAULT NULL,
            raw_title VARCHAR(1000) DEFAULT NULL,
            issue_date DATE DEFAULT NULL,
            title VARCHAR(1000) DEFAULT NULL,
            tags VARCHAR(1000) DEFAULT NULL,

            parse_status ENUM(
                'READY',
                'DATE_ERROR',
                'TITLE_ERROR'
            ) NOT NULL DEFAULT 'READY',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uk_market_issue_import_video_id (video_id),
            KEY idx_market_issue_import_date (issue_date),
            KEY idx_market_issue_import_status (parse_status)
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb3
        COLLATE=utf8mb3_general_ci
    """)


# =============================================================================
# 문자열 정리
# =============================================================================

def remove_4byte_chars(text):
    """
    MySQL utf8mb3에 저장할 수 없는 4바이트 Unicode 문자 제거.
    주로 YouTube 제목의 이모지 제거 목적.

    한글/영문/숫자/일반 특수문자는 유지.
    """
    if text is None:
        return None

    return ''.join(
        ch for ch in str(text)
        if ord(ch) <= 0xFFFF
    )


def normalize_space(text):
    if text is None:
        return ''

    text = str(text).replace('\xa0', ' ')
    text = re.sub(r'[ \t]+', ' ', text)

    return text.strip()


# =============================================================================
# 제목 파싱
# =============================================================================

def extract_mmdd(raw_title):
    if not raw_title:
        return None

    patterns = [
        # [09/04 #당잠사]
        r'\[\s*(\d{1,2})\s*/\s*(\d{1,2})[^\]]*\]',

        # 09/04 #당잠사
        r'(?<!\d)(\d{1,2})\s*/\s*(\d{1,2})(?!\d)',
    ]

    for pattern in patterns:
        m = re.search(
            pattern,
            raw_title,
            flags=re.IGNORECASE
        )

        if not m:
            continue

        month = int(m.group(1))
        day = int(m.group(2))

        try:
            date(2000, month, day)
            return month, day
        except ValueError:
            continue

    return None


def extract_tags(raw_title):
    if not raw_title:
        return None

    tags = []

    for tag in re.findall(
        r'#([^\s#|ㅣ\]\[(),]+)',
        raw_title
    ):
        tag = tag.strip(' ,|ㅣ[]()')

        if not tag:
            continue

        # 당잠사 / MorningBriefing은 프로그램 식별용 태그이므로 저장 제외
        if tag.lower() in ('당잠사', 'morningbriefing'):
            continue

        if tag not in tags:
            tags.append(tag)

    return ','.join(tags) if tags else None


def normalize_title(raw_title):
    if raw_title is None:
        return ''

    title = str(raw_title)

    title = title.replace('\xa0', ' ')
    title = title.replace('ㅣ', '|')

    # [09/04 #당잠사] 형태 제거
    title = re.sub(
        r'^\s*\[\s*\d{1,2}\s*/\s*\d{1,2}[^\]]*\]\s*',
        '',
        title,
        flags=re.IGNORECASE
    )

    # 09/04 #당잠사 형태 제거
    title = re.sub(
        r'^\s*\d{1,2}\s*/\s*\d{1,2}\s*#?\s*당잠사\s*',
        '',
        title,
        flags=re.IGNORECASE
    )

    # 프로그램 식별용 해시태그 제거
    title = re.sub(
        r'#\s*(?:당잠사|MorningBriefing)\b',
        '',
        title,
        flags=re.IGNORECASE
    )

    # 나머지 해시태그는 tags 필드로 분리
    title = re.sub(
        r'#[^\s#|]+',
        '',
        title
    )

    # 구분자 정리
    title = re.sub(
        r'\s*\|\s*',
        ' | ',
        title
    )

    title = re.sub(
        r'[ \t]+',
        ' ',
        title
    )

    return title.strip(' |,\t\r\n')


# =============================================================================
# 연도 보정
# =============================================================================

class YearResolver:
    """
    플레이리스트가 최신 -> 과거 순서라는 전제.

    예:
      01/02 다음 항목이 12/31이면
      이전 연도로 이동.
    """

    def __init__(self, start_year):
        self.year = start_year
        self.prev_mmdd = None

    def resolve(self, month, day):
        mmdd = month * 100 + day

        if self.prev_mmdd is not None:
            # 최신 -> 과거로 내려가는데 날짜가 크게 증가하면 연말을 지난 것
            if mmdd > self.prev_mmdd + 300:
                self.year -= 1

        self.prev_mmdd = mmdd

        try:
            return date(
                self.year,
                month,
                day
            )
        except ValueError:
            return None


# =============================================================================
# YouTube
# =============================================================================

def get_playlist_entries():
    opts = {
        'quiet': True,
        'no_warnings': True,
        'ignoreerrors': True,
        'extract_flat': 'in_playlist',
        'skip_download': True,
        'lazy_playlist': False,

        # YouTube가 번역된 영문 제목을 반환하지 않도록
        # 한국어 메타데이터를 우선 요청
        'extractor_args': {
            'youtube': {
                'lang': ['ko'],
            }
        },
    }

    with yt_dlp.YoutubeDL(opts) as ydl:
        info = ydl.extract_info(
            PLAYLIST_URL,
            download=False
        )

    if not info:
        raise RuntimeError(
            '플레이리스트 정보를 가져오지 못했습니다.'
        )

    return [
        entry
        for entry in (info.get('entries') or [])
        if entry
    ]


def normalize_video_id(entry):
    value = (
        entry.get('id')
        or entry.get('url')
    )

    if not value:
        return None

    value = str(value).strip()

    if value.startswith('http'):
        parsed = urlparse(value)
        qs = parse_qs(parsed.query)

        if qs.get('v'):
            return qs['v'][0]

        if 'youtu.be' in parsed.netloc:
            return parsed.path.strip('/').split('/')[0]

    return value


# =============================================================================
# INSERT / UPDATE
# =============================================================================

def upsert_row(cur, row):
    cur.execute("""
        INSERT INTO market_issue_import
        (
            playlist_index,
            video_id,
            video_url,
            raw_title,
            issue_date,
            title,
            tags,
            parse_status
        )
        VALUES
        (
            %s, %s, %s, %s,
            %s, %s, %s, %s
        )
        ON DUPLICATE KEY UPDATE
            playlist_index = VALUES(playlist_index),
            video_url      = VALUES(video_url),
            raw_title      = VALUES(raw_title),
            issue_date     = VALUES(issue_date),
            title          = VALUES(title),
            tags           = VALUES(tags),
            parse_status   = VALUES(parse_status)
    """, (
        row['playlist_index'],
        row['video_id'],
        row['video_url'],
        row['raw_title'],
        row['issue_date'],
        row['title'],
        row['tags'],
        row['parse_status'],
    ))


# =============================================================================
# 메인
# =============================================================================

def main():
    print('=' * 90)
    print('당잠사 과거 플레이리스트 -> market_issue_import')
    print('OCR 사용 안 함')
    print('market_issue 직접 등록 안 함')
    print('utf8mb3 호환을 위해 4바이트 문자(이모지) 제거')
    print('YouTube 메타데이터 언어: 한국어 우선')
    print('tags에서 당잠사 / MorningBriefing 제외')
    print('=' * 90)

    print()
    print('플레이리스트 읽는 중...')

    entries = get_playlist_entries()

    print(
        f'플레이리스트 항목: '
        f'{len(entries):,}건'
    )

    db = get_db()
    cur = db.cursor()

    create_import_table(cur)
    db.commit()

    resolver = YearResolver(
        START_YEAR
    )

    ready = 0
    date_error = 0
    title_error = 0
    skipped = 0
    removed_4byte = 0

    try:
        for idx, entry in enumerate(
            entries,
            1
        ):
            video_id = normalize_video_id(
                entry
            )

            original_title = (
                entry.get('title')
                or ''
            ).strip()

            raw_title = remove_4byte_chars(
                original_title
            )

            if raw_title != original_title:
                removed_4byte += 1

            raw_title = normalize_space(
                raw_title
            )

            if not video_id:
                skipped += 1
                continue

            mmdd = extract_mmdd(
                raw_title
            )

            issue_date = None
            title = normalize_title(
                raw_title
            )
            tags = extract_tags(
                raw_title
            )

            if not mmdd:
                status = 'DATE_ERROR'
                date_error += 1

            else:
                issue_date = resolver.resolve(
                    mmdd[0],
                    mmdd[1]
                )

                if issue_date is None:
                    status = 'DATE_ERROR'
                    date_error += 1

                elif not title:
                    status = 'TITLE_ERROR'
                    title_error += 1

                else:
                    status = 'READY'
                    ready += 1

            upsert_row(
                cur,
                {
                    'playlist_index': idx,
                    'video_id': video_id,
                    'video_url': (
                        'https://www.youtube.com/'
                        f'watch?v={video_id}'
                    ),
                    'raw_title': raw_title,
                    'issue_date': issue_date,
                    'title': title or None,
                    'tags': tags,
                    'parse_status': status,
                }
            )

            if idx % 100 == 0:
                db.commit()

                print(
                    f'{idx:,}/{len(entries):,} '
                    f'| READY {ready:,} '
                    f'| DATE_ERROR {date_error:,} '
                    f'| TITLE_ERROR {title_error:,} '
                    f'| 4BYTE 제거 {removed_4byte:,}'
                )

        db.commit()

        print()
        print('=' * 90)
        print('완료')
        print(f'전체          : {len(entries):,}')
        print(f'READY         : {ready:,}')
        print(f'DATE_ERROR    : {date_error:,}')
        print(f'TITLE_ERROR   : {title_error:,}')
        print(f'SKIP          : {skipped:,}')
        print(f'4바이트 제거  : {removed_4byte:,}')
        print('=' * 90)

    except Exception:
        db.rollback()
        raise

    finally:
        cur.close()
        db.close()


if __name__ == '__main__':
    try:
        main()

    except KeyboardInterrupt:
        print('\n중단되었습니다.')
        sys.exit(1)

    except Exception as e:
        print()
        print(
            f'ERROR: {e}'
        )
        sys.exit(1)
