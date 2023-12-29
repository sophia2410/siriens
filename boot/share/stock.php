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
                //require("/boot/nav_top.php");
                ?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Stock Detail</h6>
                    </div>

                    <div class="table-responsive">
                    <!-- DataTales Example -->
                    <div class="card shadow mb-2">
                        
                        <form id="form" method=post >
                        <input type=hidden name='winHeight'>
                        <div class="input-group">
                            <input type="text" class="SEARCH form-control bg-light border-0 small" style='height:30px;'
                                placeholder="종목코드" aria-label="Search_Stock"
                                aria-describedby="basic-addon2" name="stock" onkeydown="keyDown()">
                            <input type="text" class="SEARCH form-control bg-light border-0 small" style='height:30px;'
                                placeholder="종목명" aria-label="Search_Stock"
                                aria-describedby="basic-addon2" name="stock_nm" onkeydown="keyDown2()">
                            <div class="input-group-append">
                                <button class="btn btn-primary btn-sm" style='height:30px;' name="searchbt" type="button" onclick="getList()">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row container-fluid">
                                <div class="col-sm col-lg-12">
                                    <div id="grid_p"></div>
                                </div>
                            </div>
                            <div class="row container-fluid">
                                <div class="col-sm col-lg-8">
                                    <div id="grid"></div>
                                </div>
                                <div class="col-sm col-lg-4">
                                    <div id="grid_t"></div>
                                </div>
                            </div>

                            <div class="row container-fluid">
                                <iframe id="iframe" class="border-0" src='stock_card.php' width="100%"  onload="calcHeight()" sandbox="allow-scripts allow-popups"></iframe>
                            </div>
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

    
<script>
const grid = new tui.Grid({
    el: document.getElementById('grid'),
    data: {
        api: {
        readData: { url: 'issue_script.php', method: 'get' }
        },
        initialRequest:false
    },
    header: {
        height: 30
    },
    rowHeight: 30,
    bodyHeight: 200,
    scrollX: true,
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
        header: '상승률',
        name: 'rate',
        align:'right',
        width:70,
        sortable: true
        },
        {
        header: '거래대금',
        name: 'amount',
        align:'right',
        width:90,
        sortable: true
        },
        {
        header: '이슈',
        name: 'issue'
        }
    ]
    });

    const grid_p = new tui.Grid({
    el: document.getElementById('grid_p'),
    data: {
        api: {
        readData: { url: 'stock_script2.php', method: 'get' }
        },
        initialRequest:false
    },
    header: {
        height: 30
    },
    rowHeight: 30,
    bodyHeight: 90,
    scrollX: true,
    scrollY: false,
    columns: [
        {
        header: '일자',
        name: 'subject',
        align:'center',
        width:90
        },
        {
        header: 'D-15',
        name: 'D15',
        align:'right',
        width:90
        },
        {
        header: 'D14',
        name: 'D14',
        align:'right',
        width:90
        },
        {
        header: 'D-13',
        name: 'D13',
        align:'right',
        width:90
        },
        {
        header: 'D-12',
        name: 'D12',
        align:'right',
        width:90
        },
        {
        header: 'D-11',
        name: 'D11',
        align:'right',
        width:90
        },
        {
        header: 'D-10',
        name: 'D10',
        align:'right',
        width:90
        },
        {
        header: 'D-9',
        name: 'D09',
        align:'right',
        width:90
        },
        {
        header: 'D-8',
        name: 'D08',
        align:'right',
        width:90
        },
        {
        header: 'D-7',
        name: 'D07',
        align:'right',
        width:90
        },
        {
        header: 'D-6',
        name: 'D06',
        align:'right',
        width:90
        },
        {
        header: 'D-5',
        name: 'D05',
        align:'right',
        width:90
        },
        {
        header: 'D-4',
        name: 'D04',
        align:'right',
        width:90
        },
        {
        header: 'D-3',
        name: 'D03',
        align:'right',
        width:90
        },
        {
        header: 'D-2',
        name: 'D02',
        align:'right',
        width:90
        },
        {
        header: 'D-1',
        name: 'D01',
        align:'right',
        width:90
        }
    ]
    });
    
    const grid_t = new tui.Grid({
    el: document.getElementById('grid_t'),
    data: {
        api: {
        readData: { url: 'stock_script.php', method: 'get' }
        },
        initialRequest:false
    },
    scrollX: false,
    scrollY: false,
    header: {
        height: 30
    },
    rowHeight: 30,
    bodyHeight: 200,
    treeColumnOptions: {
        name: 'title',
        useCascadingCheckbox: true
    },
    columns: [
        {
        header: '종목',
        name: 'title',
        width: 300
        },
        {
        header: '일자',
        name: 'link_date',
        width: 80
        },
        {
        header: '이슈',
        name: 'keyword'
        }
    ]
    });

function getList(){
    if(form.stock.value == ''){
        getStockCode();
    } else{
        var opts = $(".SEARCH").serializeArray();
        grid.readData(1,opts,true);
        grid_t.readData(1,opts,true);
        grid_p.readData(1,opts,true);
        
        val = form.stock.value;
        iframe.src = "stock_card.php?find_val="+val;
    }
}

function keyDown(){
    if (event.key === 'Enter') {
        getList();
    }
}

function keyDown2(){
    form.stock.value = '';
    if (event.key === 'Enter') {
        getStockCode();
    }
}

function getStockCode(){
    val = form.stock_nm.value;
    window.open('stock_find_popup.php?find_val='+val,'findStock',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=400, height=500");
}

function calcHeight(){
    //find the height of the internal page

    if(form.winHeight.value == ''){
        //alert('document.body.scrollHeight');
        form.winHeight.value = document.body.scrollHeight;
    }
    
    var the_height=
    //document.getElementById('iframe').contentWindow.document.body.scrollHeight;
    form.winHeight.value - 530;
    //change the height of the iframe

    document.getElementById('iframe').height=
    the_height;
    //document.getElementById('the_iframe').scrolling = "no";
    document.getElementById('iframe').style.overflow = "hidden";
}
                                    
</script>

    <?php
        require($_SERVER['DOCUMENT_ROOT']."/boot/common/bottom.php");
    ?>

</form>
</body>
</html>