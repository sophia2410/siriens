.group-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.sector-card {
    background-color: white;
    border: 1px solid #ddd;
    padding: 10px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    min-width: 300px;
    max-width: 100%;
    margin-bottom: 20px;
}

.theme-row h4 {
    font-size: 1.2em;
    margin-bottom: 10px;
    color: #888;
}

.stock-item {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.stock-item:last-child {
    border-bottom: none;
}

.stock-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.stock-name {
    font-weight: bold;
}

.stock-change {
    font-weight: bold;
    color: #f44336; /* 상승 종목은 빨간색 */
}

.stock-amount {
    color: #666;
}
