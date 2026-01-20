<?php
require $_SERVER['DOCUMENT_ROOT'] . "/modules/common/database.php";
session_start();

// 자주 쓰는 쿼리들 정의
$queries = [
    [
        'id'    => 'HIGH_BREAK_1245',
        'title' => '장초반 고점 돌파 (~12:45)',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT
                x.date,
                b.base_high4,
                GROUP_CONCAT(CONCAT(x.id, '@', x.time, '(H=', x.high, ')') ORDER BY x.time SEPARATOR ', ') AS hit_candles
                FROM (
                SELECT
                    CONCAT(f.date,' ',f.time) id, f.date, f.time, f.high, f.low,
                    @rn := IF(@prev_date = f.date, @rn + 1, 1) AS rn,
                    @prev_date := f.date
                FROM futures_5min f
                JOIN (SELECT @rn := 0, @prev_date := '') v
                WHERE f.time >= '08:45:00'
                ORDER BY f.date, f.time
                ) x
                JOIN (
                SELECT
                    date,
                    MAX(CASE WHEN rn <= 4 THEN high END) AS base_high4
                FROM (
                    SELECT
                    f.date, f.time, f.high,
                    @rn2 := IF(@prev_date2 = f.date, @rn2 + 1, 1) AS rn,
                    @prev_date2 := f.date
                    FROM futures_5min f
                    JOIN (SELECT @rn2 := 0, @prev_date2 := '') v2
                    WHERE f.time >= '08:45:00'
                    ORDER BY f.date, f.time
                ) t
                GROUP BY date
                ) b ON x.date = b.date
                WHERE x.time <= '12:45:00'
                AND x.rn > 4
                AND x.high > b.base_high4
                GROUP BY x.date, b.base_high4
                ORDER BY x.date
            ) z
        "
    ],
    [
        'id'    => 'LOW_BREAK_1245',
        'title' => '장초반 저점 이탈 (~12:45)',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT
                x.date,
                b.base_low4,
                GROUP_CONCAT(CONCAT(x.id, '@', x.time, '(L=', x.low, ')') ORDER BY x.time SEPARATOR ', ') AS hit_candles
                FROM (
                SELECT
                    CONCAT(f.date,' ',f.time) id, f.date, f.time, f.high, f.low,
                    @rn := IF(@prev_date = f.date, @rn + 1, 1) AS rn,
                    @prev_date := f.date
                FROM futures_5min f
                JOIN (SELECT @rn := 0, @prev_date := '') v
                WHERE f.time >= '08:45:00'
                ORDER BY f.date, f.time
                ) x
                JOIN (
                SELECT
                    date,
                    MIN(CASE WHEN rn <= 4 THEN low END) AS base_low4
                FROM (
                    SELECT
                    f.date, f.time, f.low,
                    @rn2 := IF(@prev_date2 = f.date, @rn2 + 1, 1) AS rn,
                    @prev_date2 := f.date
                    FROM futures_5min f
                    JOIN (SELECT @rn2 := 0, @prev_date2 := '') v2
                    WHERE f.time >= '08:45:00'
                    ORDER BY f.date, f.time
                ) t
                GROUP BY date
                ) b ON x.date = b.date
                WHERE x.time <= '12:45:00'
                AND x.rn > 4
                AND x.low < b.base_low4
                GROUP BY x.date, b.base_low4
                ORDER BY x.date
            ) z
        "
    ],
    [
        'id'    => 'early_high_vs_hour_high_hour_higher',
        'title' => '장초반 고점  < 1시간 고점',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date
                FROM (
                    SELECT
                        f.date,
                        f.time,
                        f.high,
                        @rn := IF(@prev_date = f.date, @rn + 1, 1) AS rn,
                        @prev_date := f.date
                    FROM futures_5min f
                    JOIN (SELECT @rn := 0, @prev_date := '') v
                    ORDER BY f.date, f.time
                ) t
                GROUP BY date
                HAVING
                    COUNT(CASE WHEN rn = 12 THEN 1 END) = 1
                    AND MAX(CASE WHEN rn <= 4 THEN high END) < MAX(CASE WHEN rn = 12 THEN high END)
            ) z
        "
    ],
    [
        'id'    => 'early_low_vs_hour_low_hour_lower',
        'title' => '장초반 저점  > 1시간 저점',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date
                FROM (
                    SELECT
                        f.date,
                        f.time,
                        f.low,
                        @rn := IF(@prev_date = f.date, @rn + 1, 1) AS rn,
                        @prev_date := f.date
                    FROM futures_5min f
                    JOIN (SELECT @rn := 0, @prev_date := '') v
                    ORDER BY f.date, f.time
                ) t
                GROUP BY date
                HAVING
                    COUNT(CASE WHEN rn = 12 THEN 1 END) = 1
                    AND MIN(CASE WHEN rn <= 4 THEN low END) > MIN(CASE WHEN rn = 12 THEN low END)
            ) z
        "
    ],
    [
        'id'    => 'first_60min_big_bull',
        'title' => '1호 60분봉 장대양봉',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT f.date
                FROM futures_60min f
                JOIN (
                    SELECT date, MIN(time) AS first_time
                    FROM futures_60min
                    GROUP BY date
                ) s ON f.date = s.date AND f.time = s.first_time
                WHERE (f.close - f.open) > 2
            ) z
        "
    ],
    [
        'id'    => 'first_60min_big_bear',
        'title' => '1호 60분봉 장대음봉',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT f.date
                FROM futures_60min f
                JOIN (
                    SELECT date, MIN(time) AS first_time
                    FROM futures_60min
                    GROUP BY date
                ) s ON f.date = s.date AND f.time = s.first_time
                WHERE (f.open - f.close) > 2
            ) z
        "
    ],
    [
        'id'    => 'first_60min_long_upper_shadow',
        'title' => '1호 60분봉 긴 윗꼬리',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT f.date
                FROM futures_60min f
                JOIN (
                    SELECT date, MIN(time) AS first_time
                    FROM futures_60min
                    GROUP BY date
                ) s ON f.date = s.date AND f.time = s.first_time
                WHERE (f.high - GREATEST(f.open, f.close)) > ABS(f.close - f.open)   -- 윗꼬리 > 몸통
                AND (LEAST(f.open, f.close) - f.low) <= ABS(f.close - f.open)        -- 아랫꼬리 ≤ 몸통
            ) z
        "
    ],
    [
        'id'    => 'first_60min_long_lower_only',
        'title' => '1호 60분봉 긴 아래꼬리',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT f.date
                FROM futures_60min f
                JOIN (
                    SELECT date, MIN(time) AS first_time
                    FROM futures_60min
                    GROUP BY date
                ) s ON f.date = s.date AND f.time = s.first_time
                WHERE (LEAST(f.open, f.close) - f.low) > ABS(f.close - f.open)       -- 아랫꼬리 > 몸통
                AND (f.high - GREATEST(f.open, f.close)) <= ABS(f.close - f.open)    -- 윗꼬리 ≤ 몸통
            ) z
        "
    ],
    [
        'id'    => 'first_60min_long_lower_only',
        'title' => '1호 60분봉 긴 윗꼬리 + 아랫꼬리',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT f.date
                FROM futures_60min f
                JOIN (
                    SELECT date, MIN(time) AS first_time
                    FROM futures_60min
                    GROUP BY date
                ) s ON f.date = s.date AND f.time = s.first_time
                WHERE (f.high - GREATEST(f.open, f.close)) > ABS(f.close - f.open)   -- 윗꼬리 > 몸통
                AND (LEAST(f.open, f.close) - f.low) > ABS(f.close - f.open)         -- 아랫꼬리 > 몸통
            ) z
        "
    ],
    // 장초반 대응 용. 60분 1호봉 이후 대응으로 변환
    // [
    //     'id'    => 'min1_above_sma5_0845_0859',
    //     'title' => '1분봉 연속 5선위 (08:45~08:59)',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_1min a
    //             JOIN (
    //                 SELECT date, count(*) 
    //                 FROM futures_1min 
    //                 WHERE time between 084500 AND 085900 AND close > sma_5 
    //                 GROUP BY date having count(*) > 11
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min1_above_sma5_0855_0859',
    //     'title' => '1분봉 연속 5선아래 (08:55~08:59)',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_1min a
    //             JOIN (
    //                 SELECT date, count(*) 
    //                 FROM futures_1min 
    //                 WHERE time between 084500 AND 085900 AND close < sma_5 
    //                 GROUP BY date having count(*) > 11
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_0900_big_bull',
    //     'title' => '09:00 5분봉 장대양봉 (1pt이상)',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM futures_5min
    //             WHERE time = 090000 AND close - open  > 1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_0900_big_bear',
    //     'title' => '09:00 5분봉 장대음봉 (1pt이상)',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM futures_5min
    //             WHERE time = 090000 AND close - open < -1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_continuous_bull',
    //     'title' => '5분봉 3연속 양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_5min a
    //             JOIN (
    //                 SELECT date, count(*) 
    //                 FROM futures_5min 
    //                 WHERE time between 084500 AND 085500 
    //                 AND close > open
    //                 GROUP BY date having count(*) = 3
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_continuous_bear',
    //     'title' => '5분봉 3연속 음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_5min a
    //             JOIN (
    //                 SELECT date, count(*) 
    //                 FROM futures_5min 
    //                 WHERE time between 084500 AND 085500 
    //                 AND close < open
    //                 GROUP BY date having count(*) = 3
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_down_sma5_early',
    //     'title' => '5분봉 5선 아래',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_5min a
    //             JOIN (
    //                 SELECT date, count(*) 
    //                 FROM futures_5min 
    //                 WHERE time between 084500 AND 090000 AND close < sma_5 
    //                 GROUP BY date having count(*) = 4
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_down_sma5_0900',
    //     'title' => '9:00 5분봉 시가 돌파 + 양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_5min a
    //             JOIN (
    //                 SELECT date
    //                 FROM futures_5min
    //                 WHERE time = 090000 AND close > sma_5 AND sma_5 > open
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min5_up_sma5_0900',
    //     'title' => '9:00 5분봉 시가 이탈 + 음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT distinct a.date 
    //             FROM futures_5min a
    //             JOIN (
    //                 SELECT date
    //                 FROM futures_5min
    //                 WHERE time = 090000 AND close < sma_5 AND sma_5 < open
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_up_min5_big_bull',
    //     'title' => '갭상승 + 5분 장대양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM rule_based_rowdata
    //             WHERE ret_5m > 0.99 AND gap_pos = 1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_up_min1_big_bull',
    //     'title' => '갭상승 + 1분 장대양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT *
    //             FROM rule_based_rowdata
    //             WHERE ret_1m > 0.99 AND gap_pos = 1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_down_min5_big_bull',
    //     'title' => '갭하락 + 5분 장대양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM rule_based_rowdata
    //             WHERE ret_5m > 0.99 AND gap_pos = 0
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_down_min1_big_bull',
    //     'title' => '갭하락 + 1분 장대양봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT *
    //             FROM rule_based_rowdata
    //             WHERE ret_1m > 0.99 AND gap_pos = 0
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_up_min5_big_bear',
    //     'title' => '갭상승 + 5분 장대음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM rule_based_rowdata
    //             WHERE ret_5m < -0.99 AND gap_pos = 1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_up_min1_big_bear',
    //     'title' => '갭상승 + 1분 장대음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT *
    //             FROM rule_based_rowdata
    //             WHERE ret_1m < -0.99 AND gap_pos = 1
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_down_min5_big_bear',
    //     'title' => '갭하락 + 5분 장대음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT * 
    //             FROM rule_based_rowdata
    //             WHERE ret_5m < -0.99 AND gap_pos = 0
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'gap_down_min1_big_bear',
    //     'title' => '갭하락 + 1분 장대음봉',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT *
    //             FROM rule_based_rowdata
    //             WHERE ret_1m < -0.99 AND gap_pos = 0
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min1_0901_open_above_sma20',
    //     'title' => '09:01 1분봉 20선 위',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT DISTINCT a.date 
    //             FROM futures_1min a
    //             JOIN (
    //                 SELECT date 
    //                 FROM futures_1min 
    //                 WHERE time = 090100 
    //                 AND open > sma_20
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'min1_0901_open_below_sma20',
    //     'title' => '09:01 1분봉 20선 아래',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT DISTINCT a.date 
    //             FROM futures_1min a
    //             JOIN (
    //                 SELECT date 
    //                 FROM futures_1min 
    //                 WHERE time = 090100 
    //                 AND open < sma_20
    //             ) b 
    //             ON b.date = a.date
    //         ) z
    //     "
    // ],
    // [
    //     'id'    => 'early_session_rise_specific_time_point',
    //     'title' => '9:30까지 6pt 이상 상승',
    //     'sql'   => "
    //         SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
    //         FROM (
    //             SELECT sp.date
    //             FROM
    //             (
    //                 -- 일자별 첫 번째 5분봉(시작 시가)
    //                 SELECT f.date, f.open AS open_start
    //                 FROM futures_5min f
    //                 JOIN (
    //                     SELECT date, MIN(time) AS first_time
    //                     FROM futures_5min
    //                     GROUP BY date
    //                 ) s ON f.date = s.date AND f.time = s.first_time
    //             ) sp
    //             JOIN
    //             (
    //                 -- 일자별 09:30:00 이하에서 가장 늦은 시점의 종가
    //                 SELECT f.date, f.close AS close_930
    //                 FROM futures_5min f
    //                 JOIN (
    //                     SELECT date, MAX(time) AS t_930
    //                     FROM futures_5min
    //                     WHERE time <= '10:00:00' -- 특정시간
    //                     GROUP BY date
    //                 ) x ON f.date = x.date AND f.time = x.t_930
    //             ) p ON sp.date = p.date
    //             WHERE p.close_930 - sp.open_start >= 6 -- 특정포인트
    //         ) z
    //     "
    // ],
    [
        'id'    => '2024.01~03',
        'title' => '2024년 1~3월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2024' 
                AND mm in ('01', '02', '03')
            ) z
        "
    ],
    [
        'id'    => '2024.04~06',
        'title' => '2024년 4~6월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2024' 
                AND mm in ('04', '05', '06')
            ) z
        "
    ],
    [
        'id'    => '2024.07~09',
        'title' => '2024년 7~9월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2024' 
                AND mm in ('07', '08', '09')
            ) z
        "
    ],
    [
        'id'    => '2024.10~12',
        'title' => '2024년 10~12월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2024' 
                AND mm in ('10', '11', '12')
            ) z
        "
    ],
    [
        'id'    => '2025.01~03',
        'title' => '2025년 1~3월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2025' 
                AND mm in ('01', '02', '03')
            ) z
        "
    ],
    [
        'id'    => '2025.04~06',
        'title' => '2025년 4~6월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2025' 
                AND mm in ('04', '05', '06')
            ) z
        "
    ],
    [
        'id'    => '2025.07~09',
        'title' => '2025년 7~9월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2025' 
                AND mm in ('07', '08', '09')
            ) z
        "
    ],
    [
        'id'    => '2025.10~12',
        'title' => '2025년 10~12월',
        'sql'   => "
            SELECT GROUP_CONCAT(DATE_FORMAT(z.date, '%Y-%m-%d') ORDER BY z.date SEPARATOR ', ') AS dates
            FROM (
                SELECT date 
                FROM calendar 
                WHERE yyyy='2025' 
                AND mm in ('10', '11', '12')
            ) z
        "
    ],

    // 여기 아래에 같은 형식으로 쿼리 계속 추가해서 쓰면 됩니다.
];

$results = [];
$errors  = [];

// 각 쿼리 실행
foreach ($queries as $q) {
    $id  = $q['id'];
    $sql = trim($q['sql']);

    $res = $mysqli->query($sql);

    if ($res === false) {
        $errors[$id] = $mysqli->error;
        $results[$id] = '';
        continue;
    }

    $row = $res->fetch_assoc();
    $results[$id] = isset($row['dates']) ? $row['dates'] : '';
    $res->free();
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title>자주 쓰는 일자 조회</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", sans-serif;
            font-size: 14px;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        h1 {
            margin-top: 0;
            font-size: 20px;
        }
        .query-box {
            background: #fff;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .query-title {
            font-weight: bold;
            margin-bottom: 6px;
        }
        .textarea-row {
            display: flex;
            gap: 6px;
            align-items: flex-start;
        }
        textarea {
            width: 100%;
            min-height: 40px;
            font-family: "SF Mono", Menlo, Monaco, Consolas, "Courier New", monospace;
            font-size: 12px;
            padding: 6px;
            resize: vertical;
        }
        button.copy-btn {
            white-space: nowrap;
            padding: 6px 10px;
            cursor: pointer;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #fafafa;
        }
        button.copy-btn:hover {
            background: #eee;
        }
        .error {
            margin-top: 4px;
            font-size: 12px;
            color: #c00;
        }
        details {
            margin-top: 6px;
            font-size: 12px;
        }
        pre {
            white-space: pre-wrap;
            word-break: break-all;
            margin: 4px 0 0 0;
        }
    </style>
</head>
<body>

<h1>자주 쓰는 일자 조회</h1>
<p style="margin-bottom: 20px; color:#555;">
    아래 결과를 그대로 복사해서 차트 조회 일자에 붙여 넣어 사용하세요.
</p>

<?php foreach ($queries as $q): 
    $id     = $q['id'];
    $title  = $q['title'];
    $sql    = trim($q['sql']);
    $value  = isset($results[$id]) ? $results[$id] : '';
    $error  = isset($errors[$id]) ? $errors[$id] : '';
?>
    <div class="query-box">
        <div class="query-title"><?= htmlspecialchars($title) ?></div>
        <div class="textarea-row">
            <button class="copy-btn" data-target="ta_<?= htmlspecialchars($id) ?>">복사</button>
            <textarea id="ta_<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($value) ?></textarea>
        </div>

        <?php if ($error): ?>
            <div class="error">SQL 오류: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <details>
            <summary>SQL 보기</summary>
            <pre><?= htmlspecialchars($sql) ?></pre>
        </details>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('copy-btn')) return;

    var targetId = e.target.getAttribute('data-target');
    var ta = document.getElementById(targetId);
    if (!ta) return;

    ta.select();
    ta.setSelectionRange(0, 99999); // 모바일 대응

    try {
        var ok = document.execCommand('copy');
        if (ok) {
            var oldText = e.target.textContent;
            e.target.textContent = '복사됨';
            setTimeout(function() {
                e.target.textContent = oldText;
            }, 1000);
        }
    } catch (err) {
        alert('복사에 실패했습니다. 수동으로 Ctrl+C 해주세요.');
    }
});
</script>

</body>
</html>