<?php
if($_SERVER["HTTP_HOST"] == 'localhost') {
    $PATH = "http://localhost/boot/";
} else {
    $PATH = "https://siriens.mycafe24.com/boot/";
}
?>

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-danger sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="stock.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-laugh-wink"></i>
                </div>
                <div class="sidebar-brand-text mx-3">sophia <sup>202410</sup></div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Heading -->
            <div class="sidebar-heading">
                Deep & Key
            </div>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>signalDeep/siriensSummary.php">
                    <i class="fas fa-fw fa-flag"></i>
                    <span>siriens Summary</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>signalDeep/marketIndex.php">
                    <i class="fas fa-fw fa-flag"></i>
                    <span>Market Index</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>signalDeep/marketReview.php">
                    <i class="fas fa-fw fa-flag"></i>
                    <span>Market Review</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Heading -->
            <div class="sidebar-heading">
                Search Data
            </div>

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/HotTheme.php">
                    <i class="fas fa-fw fa-fire"></i>
                    <span>Hot Theme</span></a>
            </li> -->

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/stock.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Stock</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/xrayTick_Stock.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Xray Tick Stock</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/xrayTick_Date.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Xray Tick Date</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>signalDeep/findKeyword.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Search Keyword</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>signalDeep/findMaxVolume.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Search MaxVolume</span></a>
            </li>

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/keyword.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Keyword</span></a>
            </li> -->
            
            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/opsidian_StockInfo.php">
                    <i class="fas fa-fw fa-book"></i>
                    <span>For Obsidian</span></a>
            </li> -->

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/schedule.php">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>Schedule</span></a>
            </li> -->

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">


            <!-- Heading -->
            <div class="sidebar-heading">
                siriens Data
            </div>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/mochaten.php?mainF=siriens&user=sophia">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>Mo.Cha.10 </span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>watchlist/aStarWatchlist.php">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>aStar WatchList </span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>watchlist/sophiaWatchlist.php">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>Sophia WatchList </span></a>
            </li>

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/nomadList.php">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>NOMAD WatchList </span></a>
            </li> -->

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/mochatenKeyword.php?user=sophia">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Mo.Cha.10 - Keyword </span></a>
            </li> -->

            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/mochatenKeywordStr.php?user=sophia">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Mo.Cha.10 - Day0 </span></a>
            </li> -->
            
            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            
            <!-- Heading -->
            <div class="sidebar-heading">
                Trading
            </div>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>watchlist/watchList.php">
                    <i class="fas fa-fw fa-gift"></i>
                    <span>0-Day Stock </span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>watchlist/scenario.php">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Scenario </span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensTrade.php">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Buy&Sell </span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensTrade_Review.php?user=sophia">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>Buy&Sell Review</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Heading -->
            <div class="sidebar-heading">
                Python UPLOAD
            </div>

            <!-- 인포스탁 구독 잠시 멈춤.. 필요시 부할 -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/infostock_Theme.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Infostock > Theme</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/infostock_Stock.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Infostock > Stock</span></a>
            </li> -->

            <!-- 인포스탁 데이터로 리포트 정리하던 작업 하지 않음 // 메뉴 정리 필요.. -->
            <!-- nouse_230913으로 이동 -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/infostockReport.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Infostock Report</span></a>
            </li> -->

            <!-- Nav Item - Tables -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/getSignalReport.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Siri > News&Evening</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensEvening.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Kiwoom > Evening</span></a>
            </li>

            <!-- Nav Item -->
            <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensReport.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>siriens Report(not yet) </span></a>
            </li>

            <!-- 나만의 이브닝을 정리하는데 많은 시행착오를 겪고 있는 중이다. 아래 그 흔적의 일부.. 기존에 했던 방식에서 다시 변경해본다. siriensReport -> siriensEvening 2023.09.23 -->
            <!-- nouse_230913으로 이동 -->
            <!-- Nav Item -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensReport.php">
                    <i class="fas fa-fw fa-chart-bar"></i>
                    <span>siriensReport </span></a>
            </li> -->
            
            <!-- Nav Item - Tables -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensReportView_Ver4.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>siriensReport Viewer</span></a>
            </li> -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriensReportView_Ver3.php">
                    <i class="fas fa-fw fa-newspaper"></i>
                    <span>Report Viewer Ver3</span></a>
            </li> -->

            <!-- 시그널이브닝과 인포스탁 데이터 비교 작업 하지 않음 // 메뉴 정리 필요.. -->
            <!-- nouse_230913으로 이동 -->
            <!-- Nav Item - Tables -->
            <!-- <li class="nav-item">
                <a class="nav-link" href="<?=$PATH?>siriens/siriVSinfostock.php">
                    <i class="fas fa-fw fa-dice"></i>
                    <span>SignalEV VS Infostock</span></a>
            </li> -->
            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">


            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

        </ul>
        <!-- End of Sidebar -->