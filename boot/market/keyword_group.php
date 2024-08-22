<?php
require($_SERVER['DOCUMENT_ROOT']."/boot/common/db/connect.php");

// Fetch keyword groups
function fetch_keyword_groups($mysqli) {
    $query = "SELECT group_id, group_name FROM keyword_groups_master ORDER BY group_name ASC";
    $result = $mysqli->query($query);

    $keywordGroups = [];
    while ($row = $result->fetch_assoc()) {
        $keywordGroups[] = $row;
    }

    return $keywordGroups;
}

// Fetch keyword group history
function fetch_keyword_group_history($mysqli, $groupId) {
    $history = [];
    if ($groupId) {
        $query = "
            SELECT 
                mi.date, 
                mi.issue, 
                mi.theme, 
                mi.sector,
                mis.code, 
                mis.name, 
                mis.stock_comment 
            FROM 
                market_issues mi
            LEFT JOIN 
                market_issue_stocks mis ON mi.issue_id = mis.issue_id
            WHERE 
                mi.keyword_group_id = ?
            ORDER BY 
                mi.date DESC";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    return $history;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        if ($action === 'fetch_keyword_groups') {
            echo json_encode(fetch_keyword_groups($mysqli));
            exit;
        }

        if ($action === 'fetch_keyword_group_history' && isset($_GET['group_id'])) {
            $groupId = intval($_GET['group_id']);
            echo json_encode(fetch_keyword_group_history($mysqli, $groupId));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keyword Group History</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            height: 100%;
            display: flex;
        }

        #left-panel, #right-panel {
            padding: 20px;
            overflow-y: auto;
        }

        #left-panel {
            width: 50%;
            border-right: 1px solid #ddd;
        }

        #right-panel {
            width: 50%;
            padding-left: 20px;
        }

        .keyword-group {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
        }

        .keyword-group:hover {
            background-color: #f0f0f0;
        }

        h2 {
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f9f9f9;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div id="left-panel">
        <h2>Keyword Groups</h2>
        <div id="keyword-groups">
            <!-- Keyword groups will be dynamically loaded here -->
        </div>
    </div>

    <div id="right-panel">
        <h2>Group History</h2>
        <div id="group-history">
            <!-- Group history will be dynamically loaded here -->
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Load keyword groups
            $.get('?action=fetch_keyword_groups', function(data) {
                const keywordGroups = JSON.parse(data);
                keywordGroups.forEach(group => {
                    $('#keyword-groups').append(`<div class="keyword-group" data-group-id="${group.group_id}">${group.group_name}</div>`);
                });
            });

            // Load history for the selected keyword group
            $('#keyword-groups').on('click', '.keyword-group', function() {
                const groupId = $(this).data('group-id');
                
                $.get('?action=fetch_keyword_group_history', { group_id: groupId }, function(data) {
                    const history = JSON.parse(data);
                    let historyHtml = '';

                    if (history.length > 0) {
                        historyHtml += '<table>';
                        historyHtml += '<thead><tr><th>Date</th><th>Issue</th><th>Theme</th><th>Sector</th><th>Code</th><th>Name</th><th>Comment</th></tr></thead><tbody>';
                        history.forEach(item => {
                            historyHtml += `<tr>
                                <td>${item.date}</td>
                                <td>${item.issue}</td>
                                <td>${item.theme}</td>
                                <td>${item.sector}</td>
                                <td>${item.code}</td>
                                <td>${item.name}</td>
                                <td>${item.stock_comment}</td>
                            </tr>`;
                        });
                        historyHtml += '</tbody></table>';
                    } else {
                        historyHtml = '<p>No history available for this keyword group.</p>';
                    }

                    $('#group-history').html(historyHtml);
                });
            });
        });
    </script>

</body>
</html>
