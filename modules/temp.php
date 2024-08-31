function handleStocks($mysqli, $issue_id, $stocks, $date) {
    foreach ($stocks as $stock) {
        $code = $stock['code'] ?? '';
        $name = $stock['name'] ?? '';
        $sector = $stock['sector'] ?? ''; // Added to capture sector input
        $stock_comment = $stock['comment'] ?? '';
        $isLeader = isset($stock['is_leader']) ? '1' : '0'; // Adjusted to string
        $isWatchlist = isset($stock['is_watchlist']) ? '1' : '0'; // New field

        if ($code !== '' && $name !== '') {
            // Retrieve close_rate, volume, and amount from v_daily_price
            $priceQuery = "
                SELECT close_rate, volume, amount 
                FROM v_daily_price 
                WHERE code = ? AND date = ?
            ";
            $priceStmt = $mysqli->prepare($priceQuery);
            $priceStmt->bind_param('ss', $code, $date);
            $priceStmt->execute();
            $priceResult = $priceStmt->get_result();

            if ($priceRow = $priceResult->fetch_assoc()) {
                $close_rate = $priceRow['close_rate'] ?? null;
                $volume = $priceRow['volume'] ?? null;
                $trade_amount = $priceRow['amount'] ?? null;
            } else {
                // Set defaults if no data is found
                $close_rate = null;
                $volume = null;
                $trade_amount = null;
            }

            // Insert the data into market_issue_stocks
            $stmt = $mysqli->prepare("
                INSERT INTO market_issue_stocks 
                (issue_id, code, name, sector, close_rate, volume, trade_amount, stock_comment, is_leader, is_watchlist, date, create_dtime) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                'issssddssss', 
                $issue_id, 
                $code, 
                $name, 
                $sector,
                $close_rate, 
                $volume, 
                $trade_amount, 
                $stock_comment, 
                $isLeader, 
                $isWatchlist, 
                $date
            );

            if (!$stmt->execute()) {
                throw new Exception("market_issue_stocks 삽입 실패: " . $stmt->error);
            }
        } else {
            error_log("Skipped insertion due to missing code or name: code={$code}, name={$name}");
        }
    }
}
