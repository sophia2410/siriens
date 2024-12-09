import pymysql
import configparser

# Load database credentials from the configuration file
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# MySQL connection setup
db = pymysql.connect(
    host=config.get('database', 'host'),
    user=config.get('database', 'user'),
    password=config.get('database', 'password'),
    db=config.get('database', 'db'),
    charset=config.get('database', 'charset')
)

cursor = db.cursor()

# 0. Date range settings
sql = "SELECT '2024-03-01' start_date, max(date) end_date FROM calendar a WHERE date <= now()"
sql = "SELECT '2024-03-01' start_date, date FROM calendar a WHERE date = '2024-10-31'" 
cursor.execute(sql)
result = cursor.fetchone()
start_date = result[0]
end_date = result[1].strftime('%Y-%m-%d')

# Function to check for SQL execution errors
def check_error(step):
    error = cursor.fetchone()
    if error:
        print(f"[ERROR at {step}]: {error}")
        return True
    return False

# 1. Fetch comm_cd table data into an array
comm_query = "SELECT cd, nm, nm_sub1 FROM comm_cd WHERE l_cd = 'XR000' ORDER BY cd"
cursor.execute(comm_query)
comm_data = cursor.fetchall()

# 2. Fetch dates from the calendar table based on the date range
date_query = "SELECT date FROM calendar WHERE date BETWEEN %s AND %s"
cursor.execute(date_query, (start_date, end_date))
dates = cursor.fetchall()

# Batch process for each date
for date_row in dates:
    current_date = date_row[0]
    print(f"[DEBUG] Processing Date: {current_date}")

    # Iterate through comm_cd data
    for comm_row in comm_data:
        cd, nm, nm_sub1 = comm_row
        # Decode byte strings to normal strings
        nm = nm.decode('utf-8')
        nm_sub1 = nm_sub1.decode('utf-8')

        print(f"[DEBUG] Comm_cd Data: {comm_row}")

        # Parse nm_sub1 (trade days, occurrences, min amount)
        trade_days, occurrences, min_amt = map(int, nm.split(','))
        tot_amt_condition = min_amt * 100000000  # Convert to billion unit

        print(f"[DEBUG] Parsed nm_sub1: trade_days={trade_days}, occurrences={occurrences}, min_amt={min_amt}, tot_amt_condition={tot_amt_condition}")


        # Query to fetch stock data dynamically
        main_query = """
            SELECT A.code, A.occurrence_days
            FROM (
                SELECT ks.code, COUNT(DISTINCT ks.date) AS occurrence_days
                FROM xraytick_summary ks
                JOIN (
                    SELECT date
                    FROM calendar
                    WHERE date <= %s
                    ORDER BY date DESC
                    LIMIT %s
                ) rd ON ks.date = rd.date
                WHERE ks.tot_amt >= %s
                GROUP BY ks.code
                HAVING COUNT(DISTINCT ks.date) >= %s
            ) A
            JOIN xraytick_summary xs ON A.code = xs.code
            WHERE xs.date = %s
            AND xs.tot_amt >= %s
        """
        cursor.execute(main_query, (current_date, trade_days, tot_amt_condition, occurrences, current_date, tot_amt_condition))
        result = cursor.fetchall()
        row_count = 0

        print(f"[DEBUG] Main Query Result Count: {len(result)}")

        # Insert or update extracted stocks
        for row in result:
            code, occurrence_days = row

            # Fetch stock name and sector
            stock_query = """
                SELECT s.name, ss.sector 
                FROM stock s
                LEFT JOIN stock_sector ss ON s.code = ss.code
                WHERE s.code = %s
            """
            cursor.execute(stock_query, (code,))
            stock_row = cursor.fetchone()
            stock_name = stock_row[0].decode('utf-8') if stock_row else 'Unknown'
            stock_sector = stock_row[1].decode('utf-8') if stock_row and stock_row[1] else 'Other'

            print(f"[DEBUG] Stock Data: stockName={stock_name}, stockSector={stock_sector}")

            # Count existing records
            count_query = """
                SELECT IFNULL(MAX(stock_count), 0) AS max_stock_count 
                FROM xraytick_extracted_stocks 
                WHERE comm_cd = %s 
                AND extract_date BETWEEN DATE_SUB(%s, INTERVAL 1 MONTH) AND %s 
                AND code = %s
            """
            cursor.execute(count_query, (cd, current_date, current_date, code))
            max_stock_count = cursor.fetchone()[0]
            stock_count = max_stock_count + 1 if max_stock_count > 0 else 1  # Add 1 to existing count

            # Insert or update stock data
            insert_query = """
                INSERT INTO xraytick_extracted_stocks (code, occurrence_days, comm_cd, extract_date, stock_count)
                VALUES (%s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE occurrence_days = VALUES(occurrence_days), stock_count = VALUES(stock_count)
            """
            cursor.execute(insert_query, (code, occurrence_days, cd, current_date, stock_count))
            row_count += 1

            print(f"[INFO] Stock: {stock_name}, Sector: {stock_sector}, Date: {current_date}, Saved (Count: {stock_count}).")

        print(f"[INFO] Date: {current_date}, Condition: {cd} - {row_count} stocks saved.")

# Commit the transactions and close the database connection
db.commit()
cursor.close()
db.close()

print("[INFO] Batch process completed successfully.")
