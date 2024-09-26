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
        margin-bottom: 17px;
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

        <li><a href="<?=$PATH?>modules/issues/market_report.php"><i class="fas fa-edit"></i> <span>마켓리포트</span></a></li>
        <li><a href="<?=$PATH?>modules/issues/issue_register_by_stock.php"><i class="fas fa-edit"></i> <span>이슈 등록 by Stock</span></a></li>
        <li><a href="<?=$PATH?>modules/issues/issue_register.php"><i class="fas fa-edit"></i> <span>이슈 등록</span></a></li>
        <!-- <li><a href="<?=$PATH?>modules/issues/issue_list.php"><i class="fas fa-list"></i> <span>이슈 조회</span></a></li> -->
        <li><a href="<?=$PATH?>modules/issues/stock_issue_list.php"><i class="fas fa-search"></i> <span>종목 이슈</span></a></li>
        <li><a href="<?=$PATH?>modules/issues/keyword_group_list.php"><i class="fas fa-search"></i> <span>키워드 그룹</span></a></li>
        <li><a href="<?=$PATH?>modules/issues/theme_report.php"><i class="fas fa-tags"></i> <span>테마 조회</span></a></li>

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">Xray-Tick</div>

        <li><a href="<?=$PATH?>boot/watchlist/xrayTick_Analysis.php"><i class="fas fa-tags"></i> <span>XrayTick 분석</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/xrayTick_Stock.php"><i class="fas fa-book"></i> <span>종목 XrayTick</span></a></li>

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">View Data</div>

        <li><a href="<?=$PATH?>boot/siriens/stock.php"><i class="fas fa-tags"></i> <span>종목 상세</span></a></li>
        <li><a href="<?=$PATH?>boot/siriens/mochaten.php?mainF=siriens&user=sophia"><i class="fas fa-tags"></i> <span>모차십</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/0dayStocks.php"><i class="fas fa-tags"></i> <span>0일차 종목</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/sophiaWatchlist.php"><i class="fas fa-tags"></i> <span>Sophia 관.종.</span></a></li>
        <li><a href="<?=$PATH?>boot/watchlist/aStarWatchlist.php"><i class="fas fa-tags"></i> <span>aStar 관.종.</span></a></li>

        <!-- Sidebar Divider -->
        <hr class="sidebar-divider">
        <!-- Sidebar Heading -->
        <div class="sidebar-heading">Get Data</div>

        <li><a href="<?=$PATH?>boot/siriens/getSignalReport.php"><i class="fas fa-tags"></i> <span>이브닝 등록</span></a></li>
    </ul>
</div>
