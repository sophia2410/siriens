<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
    $PATH = "http://localhost/";
} else {
    $PATH = "https://siriens.mycafe24.com/";
}
?>
<style>
    /* 메뉴바 스타일 */
    #nav-menu {
        width: 100px; /* 메뉴바 너비를 100px */
        background-color: #e74c3c;
        padding: 10px 0;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        font-family: 'Roboto', sans-serif; /* 얇고 선명한 폰트 사용 */
        overflow-y: auto; /* 스크롤바 추가 */
    }

    #nav-menu ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }

    #nav-menu ul li {
        margin-bottom: 10px;
    }

    #nav-menu ul li a {
        display: flex;
        flex-direction: column; /* 아이콘과 텍스트를 세로로 정렬 */
        align-items: center;
        padding: 5px; /* 패딩을 줄여 공간 절약 */
        text-decoration: none;
        color: #ffffff; /* 텍스트 색상 흰색으로 설정 */
        font-size: 12px; /* 글씨 크기 줄임 */
        font-weight: 300; /* 얇은 글씨체 적용 */
        transition: background-color 0.3s, color 0.3s;
    }

    #nav-menu ul li a i {
        margin-bottom: 3px; /* 아이콘과 텍스트 간격 줄임 */
        font-size: 14px; /* 아이콘 크기 줄임 */
    }

    #nav-menu ul li a:hover {
        background-color: #c0392b; /* 호버 시 더 어두운 붉은색 */
        color: #ffffff;
    }

    /* Sidebar Heading Style */
    .sidebar-heading {
        font-size: 0.8rem; /* 폰트 크기를 줄임 */
        font-weight: 700; /* 폰트 두께 유지 */
        color: #EEEEEE; /* 색상을 부드럽게 변경 */
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2); /* 텍스트 그림자 강조 */
        padding: 5px; /* 패딩 줄여 공간 절약 */
        text-transform: uppercase; /* 대문자 변환 */
        margin-bottom: 5px; /* 간격 줄임 */
    }

    /* 얇고 가는 Sidebar Divider */
    .sidebar-divider {
        border: 0;
        height: 2px;
        background-color: rgba(255, 255, 255, 0.1); /* 얇고 가는 라인으로 설정 */
        margin: 5px 0; /* 간격 줄임 */
    }

    /* 아이콘과 텍스트 줄 간격 조절 */
    #nav-menu ul li a span {
        margin-top: 3px; /* 아이콘과 텍스트 간의 간격 줄임 */
        text-align: center; /* 텍스트 가운데 정렬 */
    }

    /* 링크된 상태 스타일 */
    #nav-menu ul li a.active {
        background-color: #b03a2e; /* 활성화된 링크의 배경색 */
        font-weight: bold; /* 활성화된 링크의 폰트 두께 증가 */
    }
</style>

<div id="nav-menu">
    <ul>
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">Market</div>

        <!-- <li><a href="<?=$PATH?>modules/market/market_report.php"><i class="fas fa-edit"></i> <span>마켓리포트</span></a></li> -->
        <li><a href="<?=$PATH?>modules/market/market_report_register.php"><i class="fas fa-edit"></i> <span>마켓리포트2</span></a></li>
        <li><a href="<?=$PATH?>modules/market/issue_register.php"><i class="fas fa-edit"></i> <span>이슈 등록</span></a></li>
        <li><a href="<?=$PATH?>modules/market/event_register_by_stock.php"><i class="fas fa-edit"></i> <span>이벤트 등록<br>[ by Stock ]</span></a></li>
        <!-- <li><a href="<?=$PATH?>modules/market/event_register.php"><i class="fas fa-edit"></i> <span>이벤트 등록</span></a></li> -->
        <li><a href="<?=$PATH?>modules/market/stock_event_list.php"><i class="fas fa-search"></i> <span>종목 조회</span></a></li>
        <li><a href="<?=$PATH?>modules/market/theme_report.php"><i class="fas fa-tags"></i> <span>테마 조회</span></a></li>
        <li><a href="<?=$PATH?>modules/market/keyword_group_list.php"><i class="fas fa-search"></i> <span>키워드 조회</span></a></li>
        <li><a href="<?=$PATH?>modules/market/xraytick_comment_register.php"><i class="fas fa-tags"></i> <span>연속매수<br>코멘트</span></a></li>
        <li><a href="<?=$PATH?>modules/market/xraytick_monthly.php"><i class="fas fa-search"></i> <span>연속매수<br>섹터-월별</span></a></li>
        <li><a href="<?=$PATH?>modules/market/xraytick_daily.php"><i class="fas fa-search"></i> <span>연속매수<br>섹터-일별</span></a></li>

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">View Data</div>

        <li><a href="<?=$PATH?>boot/watchlist/xrayTick_Analysis.php"><i class="fas fa-tags"></i> <span>XrayTick 분석</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/xrayTick_Stock.php"><i class="fas fa-book"></i> <span>종목 XrayTick</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/kiwoomRealtime.php"><i class="fas fa-book"></i> <span>종목 실시간</span></a></li>
        <li><a href="<?=$PATH?>boot/siriens/stock.php"><i class="fas fa-tags"></i> <span>종목 상세</span></a></li>
        <!-- <li><a href="<?=$PATH?>boot/siriens/mochaten.php?mainF=siriens&user=sophia"><i class="fas fa-tags"></i> <span>모차십</span></a></li> -->
        <!-- <li><a href="<?=$PATH?>boot/watchlist/0dayStocks.php"><i class="fas fa-tags"></i> <span>0일차 종목</span></a></li> -->
        <!-- <li><a href="<?=$PATH?>boot/watchlist/sophiaWatchlist.php"><i class="fas fa-tags"></i> <span>Sophia 관.종.</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/aStarWatchlist.php"><i class="fas fa-tags"></i> <span>aStar 관.종.</span></a></li> -->

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">Growth</div>

        <li><a href="<?=$PATH?>modules/growth/journal_feature_register.php"><i class="fas fa-tags"></i> <span>Hot종목 일지</span></a></li>
        <li><a href="<?=$PATH?>modules/growth/journal_trade_register.php"><i class="fas fa-tags"></i> <span>매매일지 등록</span></a></li>
        <li><a href="<?=$PATH?>modules/growth/thought_register.php"><i class="fas fa-tags"></i> <span>Thought</span></a></li>
        <li><a href="<?=$PATH?>modules/market/top_amount_daily.php"><i class="fas fa-tags"></i> <span>거래대금Top30</span></a></li>
        <li><a href="<?=$PATH?>modules/market/top_amount_weekly.php"><i class="fas fa-tags"></i> <span>거래대금Top30_W</span></a></li>
        <!-- <li><a href="<?=$PATH?>modules/growth/trade_register.php"><i class="fas fa-tags"></i> <span>매매기록</span></a></li> -->

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <!-- <li><a href="<?=$PATH?>boot/siriens/getSignalReport.php"><i class="fas fa-tags"></i> <span>이브닝 등록</span></a></li> -->
    </ul>
</div>
