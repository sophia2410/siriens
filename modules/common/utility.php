<?php
// 금액에 따른 CSS 클래스 반환
function Utility_GetAmountClass($amountInBillion) {
    if ($amountInBillion >= 2000) {
        return 'amount-highest'; // 2000억 이상
    } elseif ($amountInBillion >= 1000) {
        return 'amount-high'; // 1000억 이상
    } elseif ($amountInBillion >= 500) {
        return 'amount-medium-high'; // 500억 이상
    } elseif ($amountInBillion >= 150) {
        return 'amount-medium'; // 150억 이상
    } elseif ($amountInBillion >= 100) {
        return 'amount-low'; // 100억 이상
    } else {
        return 'amount-lowest'; // 100억 미만
    }
}

// 등락률에 따른 등락률 CSS 클래스 반환
function Utility_GetCloseRateClass($closeRate) {
    if ($closeRate >= 29.5) {
        return 'close-rate-high'; // 상한가
    } else {
        return 'close-rate-lowest'; // 100억 미만
    }
}

// 등락률에 따른 종목명 CSS 클래스 반환
function Utility_GetStockNameClass($closeRate) {
    if ($closeRate >= 29.5) {
        return 'stock-name-high'; // 상한가
    } else {
        return 'stock-name-lowest'; // 100억 미만
    }
}

/**
 * 날짜 형식을 지정된 형식으로 변환하는 함수
 * 
 * @param string $dateString 변환할 날짜 문자열 (예: '2024-08-25')
 * @param string $format 원하는 날짜 형식 (기본값: 'Y-m-d')
 * @return string 변환된 날짜 문자열
 */
function Utility_FormatDate($dateString, $format = 'Y-m-d') {
    return date($format, strtotime($dateString));
}

?>