<?php
// 거래금액에 따른 색상 표시
function getAmountStyle($amountInBillion) {
    if ($amountInBillion >= 2000) {
        return'color:#ff0000; font-weight: bold;'; // 2000억 이상
    } elseif ($amountInBillion >= 1000) {
        return'color:#ff7f00; font-weight: bold;'; // 1000억 이상
    } elseif ($amountInBillion >= 500) {
        return'color:#ffa500; font-weight: bold;'; // 500억 이상
    } elseif ($amountInBillion >= 150) {
        return'color:#fcb9b2;'; // 150억 이상
    } elseif ($amountInBillion >= 100) {
        return'color:#ffccd5;'; // 100 이상
    } else {
        return''; // 10억 미만
    }
}
?>