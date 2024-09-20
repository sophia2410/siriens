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
?>