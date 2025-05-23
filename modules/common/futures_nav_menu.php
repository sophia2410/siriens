<!-- modules/common/futures_nav_menu.php -->

<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
    $PATH = "http://localhost/";
} else {
    $PATH = "https://siriens.mycafe24.com/";
}
?>

<style>
#futures-nav {
  width: 150px;
  height: 100vh;
  background-color: #2c3e50;
  color: #ecf0f1;
  font-family: 'Roboto', sans-serif;
  padding-top: 20px;
  position: fixed;
  top: 0;
  left: 0;
}

#futures-nav ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

#futures-nav li {
  padding: 12px 20px;
  border-bottom: 1px solid #34495e;
}

#futures-nav li a {
  color: #ecf0f1;
  text-decoration: none;
  display: block;
}

#futures-nav li a:hover {
  background-color: #34495e;
}
</style>

<div id="futures-nav">
  <ul>
    <li><strong>📊 선물 전략</strong></li>
    <li><a href="<?=$PATH?>modules/futures/strategy_filter.php">전략 조건 필터</a></li>
    <li><a href="<?=$PATH?>modules/futures/strategy_calendar.php">전략 성과 달력</a></li>
    <li><a href="<?=$PATH?>modules/futures/futures_chart.php">선물 차트</a></li>

    <li><a href="<?=$PATH?>modules/futures/futures_snapshot_match.php">패턴 분석</a></li>
    <li><a href="<?=$PATH?>modules/futures/futures_snapshot_filter.php">스냅샷 필터</a></li>
    <li><a href="<?=$PATH?>modules/futures/futures_snapshot_analysis.php">스냅샷분석</a></li>
    <li><a href="<?=$PATH?>modules/futures/futures_snapshot_compare.php">스냅샷 검증</a></li>

    
    <li><a href="<?=$PATH?>modules/futures/futures_bt_ui.php">백테스트등록</a></li>

    <li><a href="<?=$PATH?>modules/growth/journal_trade_register.php">시장 리뷰</a></li>

    
    
    <!-- <li><a href="<?=$PATH?>modules/futures/futures_snapshot_view.php">스냅샷</a></li> -->
    <!-- <li><a href="<?=$PATH?>modules/futures/futures_trade_analysis.php">선물매매 분석</a></li> -->
    <!-- <li><a href="/futures/futures_trade_log.php">체결 로그</a></li> -->
    <!-- <li><a href="/futures/futures_summary.php">일별 요약 리포트</a></li> -->
  </ul>
</div>
