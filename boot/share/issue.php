<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
?>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <?php
        require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_left_share.php");
        ?>
        

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <?php
                //require($_SERVER['DOCUMENT_ROOT']."/boot/common/nav_top.php");
                ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daily Issue</h6>
                    </div>

                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="input-group">
                            <input type="text" class="SEARCH form-control bg-light border-0 small"
                                placeholder="거래일자 20241224" aria-label="Search_Issue"
                                aria-describedby="basic-addon2" name="date" onkeydown="keyDown()">
                            <input type="text" class="SEARCH form-control bg-light border-0 small"
                                placeholder="종목명" aria-label="Search_Stock"
                                aria-describedby="basic-addon2" name="stock" onkeydown="keyDown()">
                            <input type="text" class="SEARCH form-control bg-light border-0 small"
                                placeholder="이슈" aria-label="Search_Issue"
                                aria-describedby="basic-addon2" name="issue" onkeydown="keyDown()">
                            <div class="input-group-append">
                                <button class="btn btn-primary btn-sm" type="button" onclick="getList()">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">

                            <div id="grid"></div>
        
                            <script>
                                const grid = new tui.Grid({
                                    el: document.getElementById('grid'),
                                    data: {
                                        api: {
                                        readData: { url: 'issue_script.php', method: 'get' }
                                        }
                                    },
                                    scrollX: false,
                                    scrollY: false,
                                    columns: [
                                        {
                                        header: '일자',
                                        name: 'date',
                                        sortingType: 'desc',
                                        align:'center',
                                        width:90,
                                        sortable: true
                                        },
                                        {
                                        header: '종목코드',
                                        name: 'code',
                                        width:80
                                        },
                                        {
                                        header: '종목명',
                                        name: 'name',
                                        width:150
                                        },
                                        {
                                        header: '상승률',
                                        name: 'rate',
                                        align:'right',
                                        width:70
                                        },
                                        {
                                        header: '거래대금',
                                        name: 'amount',
                                        align:'right',
                                        width:90
                                        },
                                        {
                                        header: '상승사유',
                                        name: 'issue'
                                        }
                                    ]
                                    });

                                    tui.Grid.applyTheme('clean', {
                                    cell: {
                                        normal: {
                                        background: '#fff',
                                        border: '#e0e0e0',
                                        showVerticalBorder: false,
                                        showHorizontalBorder: true
                                        },
                                        header: {
                                        background: '#e3e6f0',
                                        border: '#e0e0e0',
                                        showVerticalBorder: false
                                        }
                                    }
                                    });

                                function getList(){
                                    var opts = $(".SEARCH").serializeArray();
                                    grid.readData(1,opts,true);
                                }

                                function keyDown(){
                                    if (event.key === 'Enter') {
                                        getList();
                                    }
                                }
                                                                    
                            </script>

                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <?php
                require($_SERVER['DOCUMENT_ROOT']."/boot/common/footer.php");
            ?>

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <?php
        require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
    ?>

</body>

</html>