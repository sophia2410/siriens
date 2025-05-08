
# db/database.py
import pymysql
import configparser
import os

def get_db_connection():
    config = configparser.ConfigParser()
    config.read(os.path.join(os.path.dirname(__file__), 'config.ini'))
    conn = pymysql.connect(
        host=config.get('database', 'host'),
        port=int(config.get('database', 'port')),
        user=config.get('database', 'user'),
        password=config.get('database', 'password'),
        db=config.get('database', 'db'),
        charset=config.get('database', 'charset'),
        cursorclass=pymysql.cursors.Cursor
    )
    return conn