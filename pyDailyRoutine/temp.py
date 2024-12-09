async def fetch_and_announce_alerts():
    while True:
        current_time = datetime.now()
        start_time = current_time.replace(hour=9, minute=1, second=0, microsecond=0)
        end_time = current_time.replace(hour=15, minute=31, second=0, microsecond=0)

        if start_time <= current_time <= end_time:
            db = pymysql.connect(
                host=config.get('database', 'host'),
                user=config.get('database', 'user'),
                password=config.get('database', 'password'),
                db=config.get('database', 'db'),
                charset=config.get('database', 'charset'),
                cursorclass=pymysql.cursors.DictCursor
            )

            try:
                with db.cursor() as cursor:
                    # 현재 시간을 기준으로 1분 전 시간 계산
                    current_time = datetime.now()
                    one_minute_ago = current_time - timedelta(minutes=1)
                    test_datetime = one_minute_ago.strftime('%Y%m%d%H%M')

                    # 기존 텔레그램 알림 데이터 조회
                    cursor.execute('''
                    SELECT 
                        w.sector, s.code, s.name, last_min, minute_cnt,
                        ROUND(volume_sign_last_min * amount_last_min / 100, 0) AS amount_last_min,
                        ROUND(volume_sign_last_1min * amount_last_1min / 100, 0) AS amount_last_1min,
                        ROUND(amount_acc_day / 100, 0) amount_acc_day,
                        rate
                    FROM (
                        -- 내부 서브쿼리 로직 (생략, 기존 로직 유지)
                    ) g
                    JOIN kiwoom_stock s ON s.code = g.code
                    JOIN (SELECT code, MIN(group_label) sector FROM v_market_event GROUP BY code) w ON w.code = g.code
                    WHERE 
                        minute_cnt > 5
                        AND amount_last_min > 500
                        AND ROUND(volume_sign_last_min * amount_last_min / 100, 0) > 0
                        AND ROUND(volume_sign_last_1min * amount_last_1min / 100, 0) >= 0
                    ORDER BY sector, amount_acc_day DESC, rate DESC;
                    ''', (test_datetime,))
                    results = cursor.fetchall()

                    # 조건에 맞는 음성 알림 제외 대상 조회
                    cursor.execute('''
                    SELECT DISTINCT
                        jf.code
                    FROM 
                        journal_feature jf
                    WHERE 
                        jf.journal_date NOT IN (
                            SELECT DISTINCT snapshot_date 
                            FROM status_snapshot
                        )
                        AND jf.status != 'normal'
                    ''')
                    excluded_stocks = cursor.fetchall()
                    excluded_codes = {stock['code'] for stock in excluded_stocks}

                    # 그룹화 및 텔레그램 알림 전송
                    grouped_results = {}
                    audio_messages = []

                    for result in results:
                        sector = result['sector']
                        code = result['code']
                        if sector not in grouped_results:
                            grouped_results[sector] = []
                        grouped_results[sector].append(result)

                        # 음성 알림 생성 여부 결정
                        if code not in excluded_codes:
                            name = result['name']
                            amount_last_min = result['amount_last_min']
                            rate = result['rate']
                            audio_message = f"{name} {rate}% {amount_last_min}억"
                            audio_messages.append(audio_message)

                    # 텔레그램 알림 전송
                    for sector, items in grouped_results.items():
                        messages = []
                        for item in items:
                            name = item['name']
                            rate = item['rate']
                            amount_last_min = item['amount_last_min']
                            acc_amount = item['amount_acc_day']
                            message = f"[{name}] {rate}%, {amount_last_min}억/{acc_amount}억"
                            messages.append(message)

                        final_message = f"[{sector}]\n" + "\n".join(messages)
                        print(f"텔레그램 알림 전송: {final_message}")
                        await send_alert(bot, chat_id, final_message, parse_mode='HTML')

                    # 음성 알림 생성 및 재생
                    if audio_messages:
                        final_audio_message = "\n".join(audio_messages)
                        tts = gTTS(text=final_audio_message, lang='ko')
                        with tempfile.NamedTemporaryFile(delete=False, suffix=".mp3") as temp_audio_file:
                            tts.save(temp_audio_file.name)
                            audio_path = temp_audio_file.name

                        # 음성 파일 재생
                        await play_audio_async(audio_path)

            finally:
                db.close()

        await asyncio.sleep(60)
