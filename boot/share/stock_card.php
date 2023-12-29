<?php
    require($_SERVER['DOCUMENT_ROOT']."/boot/common/top.php");
	require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");
?>

<body id="page-top">

    <!-- Begin Page Content -->
    <div class="container-fluid">

    <?php
    if(isset($_GET['find_val'])){

        $stock_comment = "";
        $query = "SELECT CONCAT('#',comment,' ') comment 
                    FROM stock_comment 
                   WHERE code='".$_GET['find_val']."'
        " ;
        
        $result = $mysqli->query($query);
        
        while( $row = $result->fetch_array(MYSQLI_BOTH) ){
            $stock_comment .= $row['comment'];
        }

        if($stock_comment != "") {
            echo "<div class='row'><div class='card mb-4 col-lg-12 text-danger'>".$stock_comment."</div></div>";
        }

        $query = "SELECT  STR_TO_DATE(date, '%Y%m%d') date
                        , title
                        , content
                        , publisher
                        , writer
                        , link
                        , keyword 
                        , signal_id 
                    FROM signals_web a
                    WHERE EXISTS (SELECT 1 
                                    FROM signal_link c
                                   WHERE c.code =  '".$_GET['find_val']."'
                                     AND c.signal_id = a.signal_id)
                    order by date desc
        " ;

        /* Select queries return a resultset */
        $result = $mysqli->query($query);
        //printf("Select returned %d rows.\n", $result->num_rows);

        $i=1;
        while( $row = $result->fetch_array(MYSQLI_BOTH) ){
            if($i==1)   echo "<div class='row'>";
            else if($i%2 == 1) echo "</div><div class='row'>";

            echo "<div class='card mb-4 col-lg-6'>
                <!-- Card Header - Accordion -->
                <div class='card-header' class='d-block card-header'>
                    <font size='2.5em' class='m-0 font-weight-bold text-primary'>[".$row['keyword']."] ".$row['title']."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</font><font size='2em'>(".$row['date']."-".$row['publisher']."-".$row['writer'].")</font>
                    <button style='border:0' onclick=popupNews('".$row['link']."')>
                        <i class='fas fa-ellipsis-v fa-sm fa-hand-paper text-gray-400'>link</i>
                    </button>
                </div>
                <!-- Card Content - Collapse -->
                <div class='collapse show' id='collapseCard".$row['signal_id']."' style='font-size:0.9em;'>
                    <div class='card-body'>
                    ".$row['content']."
                    </div>
                </div>
            </div>";
            $i++;
        }

        echo "</div>";
    }
    ?>
    <script>
        function popupNews(link){
            window.open(link,'popupNews',"toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, copyhistory=no, width=1200, height=800");
        }
    </script>

    </div>
    <!-- /.container-fluid -->
</body>

</html>