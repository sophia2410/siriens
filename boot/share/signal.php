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
                        <h6 class="m-0 font-weight-bold text-primary">Signal Report</h6>
                    </div>

                    <!-- DataTales Example -->
                    <div class="card shadow mb-4">
                        <div class="input-group">
                            <input type="text" class="SEARCH form-control bg-light border-0 small"
                                placeholder="일자" aria-label="Search_Stock"
                                aria-describedby="basic-addon2" name="date" onkeydown="keyDown()">
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
                                    scrollX: false,
                                    scrollY: false,
                                    data: {
                                        api: {
                                        readData: { url: 'signal_script.php', method: 'get' }
                                        }
                                    },
                                    treeColumnOptions: {
                                        name: 'title',
                                        useIcon:false,
                                        useCascadingCheckbox: false
                                    },
                                    columns: [
                                        {
                                        header: '종목/뉴스 (뉴스클릭-팝업)',
                                        name: 'title',
                                        width: 700,
                                        resizable: true
                                        },
                                        {
                                        header: '매체',
                                        name: 'publisher',
                                        width: 100,
                                        resizable: true
                                        },
                                        {
                                        header: '작성자',
                                        name: 'writer',
                                        width: 100,
                                        resizable: true
                                        },
                                        {
                                        header: '키워드',
                                        name: 'keyword',
                                        width: 200,
                                        resizable: true
                                        },
                                        {
                                        header: '내용',
                                        name: 'content'
                                        },
                                        {
                                        header: '링크',
                                        name: 'link',
                                        hidden: true
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
                                        },
                                        focused: {
                                        border: '#e0e0e0',
                                        },
                                        disabled: {
                                        background: '#fff',
                                        text: '#4e73df'
                                        }
                                    }
                                    });
                                
                                grid.on('click', function(ev) {
                                    var link = grid.getColumnValues('link')
                                    //console.log(link[ev.rowKey]);
                                    //console.log(ev.rowKey +"/"+ ev.columnName);
                                    
                                    if (ev.columnName === 'title' && link[ev.rowKey] != null) {
                                        window.open(link[ev.rowKey],'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1500, height=1000");
                                    }
                                });

                                grid.on('onGridUpdated', function(ev) {
                                    var title = grid.getColumnValues('title')
                                    for(i=0;i<grid.getRowCount();i++)
                                        // title 이 종목인 경우 disable (font=red 처리)
                                        if(title[i].slice(-2)==='K)') {
                                            grid.disableCell(i, 'title');
                                        }
                                    }
                                );

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