import pymysql
import configparser
from datetime import datetime

# Read the configuration file for database connection settings
config = configparser.ConfigParser()
config.read('E:/Project/202410/www/boot/common/db/database_config.ini')

# Function to create a database connection
def create_db_connection():
    return pymysql.connect(
        host=config.get('database', 'host'),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset')
    )

# Function to retrieve the most recent date from the calendar table
def get_latest_date(cursor):
    query = "SELECT MAX(date) date FROM calendar a WHERE date <= now()"
    # query = "SELECT date AS date FROM calendar a WHERE date = '2024-08-12'"
    cursor.execute(query)
    result = cursor.fetchone()
    return result[0].strftime('%Y-%m-%d') if result else None

# Function to update the market_issue_stocks table with data from v_daily_price
def update_market_issue_stocks(cursor, latest_date):
    update_query = """
        UPDATE market_issue_stocks mis
        JOIN daily_price dp ON mis.code = dp.code AND mis.date = dp.date
        SET mis.high_rate = dp.high_rate,
            mis.close_rate = dp.close_rate,
            mis.volume = dp.volume,
            mis.trade_amount = ROUND(dp.amount/ 100000000, 2)
        WHERE mis.date = %s
    """
    cursor.execute(update_query, (latest_date,))
    return cursor.rowcount  # Return the number of rows affected

# Main function to perform the update
def main():
    try:
        # Establish the database connection
        connection = create_db_connection()
        cursor = connection.cursor()

        # Get the latest date from the calendar table
        latest_date = get_latest_date(cursor)
        if latest_date:
            rows_updated = update_market_issue_stocks(cursor, latest_date)
            connection.commit()
            print(f"Successfully updated {rows_updated} rows in market_issue_stocks for date {latest_date}.")
        else:
            print("No valid date found in calendar table.")

    except pymysql.Error as e:
        print(f"Error: {e}")
    finally:
        if connection and connection.open:
            cursor.close()
            connection.close()
            print("Database connection closed.")

if __name__ == "__main__":
    main()
