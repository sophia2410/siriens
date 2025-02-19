function changeChartPage() {
    let currentUrlParams = getCurrentParams(); // 기존에 쓰시던 함수
    let plusXrayParam = '';

    // 만약 plusXrayTick이 체크되어 있으면 &plus_xray=Y를 추가
    if (document.getElementById('plusXrayTick').checked) {
        // 기존 URL에 plus_xray 파라미터가 중복으로 들어가지 않도록 정리
        // (아래는 간단 예시로 replace 하는 방식)
        currentUrlParams = currentUrlParams.replace(/&plus_xray=[^&]*/,'');
        plusXrayParam = '&plus_xray=Y';
    }

    // 라디오 버튼에 따라 iframeB.src 결정
    let chartview = '';
    if (document.getElementById('naverChart').checked) {
        chartview = 'viewChart.php'; 
    } else if (document.getElementById('highChart').checked) {
        if (document.getElementById('highchartview').checked) {
            chartview = 'xrayTick_HighchartView.php';
        } else {
            chartview = 'xrayTick_StockListHighchart.php';
        }
    } else if (document.getElementById('xrayTick').checked) {
        chartview = 'xrayTick_StockList.php';
    }

    // 최종적으로 iframeB.src 갱신
    iframeB.src = chartview + currentUrlParams + plusXrayParam;
}
