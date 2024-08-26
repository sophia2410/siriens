<?php
// 금액에 따른 CSS 클래스 반환p
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