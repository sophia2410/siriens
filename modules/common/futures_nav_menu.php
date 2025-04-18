<!-- modules/common/futures_nav_menu.php -->
<style>
#futures-nav {
  width: 200px;
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
    <li><a href="/strategy/strategy_filter.php">전략 조건 필터</a></li>
    <li><a href="/strategy/strategy_result_view.php">전략 실행 결과</a></li>
    <li><a href="/strategy/replay_analysis.php">리플레이 분석</a></li>
    <li><a href="/strategy/futures_trade_log.php">체결 로그</a></li>
    <li><a href="/strategy/futures_summary.php">일별 요약 리포트</a></li>
  </ul>
</div>
