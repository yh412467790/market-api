from pymongo import MongoClient
import requests
import csv

client = MongoClient()
db = client.market
db.command("dropDatabase")


def insert_api(bulk, data):
    for key, value in data["Time Series (Daily)"].items():
        date_chunks = key.split('-')
        bulk.insert({
            "date": key,
            "date_pieces": {"year": date_chunks[0], "month": date_chunks[1], "day": date_chunks[2]},
            "open": value["1. open"],
            "high": value["2. high"],
            "low": value["3. low"],
            "close": value["4. close"],
        })

    bulk.execute()


def sp_500():
    print("SP 500 (API)")
    r = requests.get("https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&outputsize=full&symbol=INX&apikey=0KEDXOP6GN0KTIY5")
    data = r.json()

    collection = db.sp_500
    bulk = collection.initialize_unordered_bulk_op()
    insert_api(bulk, data)


def dow():
    print("DOW (API)")
    r = requests.get("https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&outputsize=full&symbol=DJI&apikey=0KEDXOP6GN0KTIY5")
    data = r.json()

    collection = db.dow
    bulk = collection.initialize_unordered_bulk_op()
    insert_api(bulk, data)


def nasdaq():
    print("NASDAQ (API)")
    r = requests.get("https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&outputsize=full&symbol=NDAQ&apikey=0KEDXOP6GN0KTIY5")
    data = r.json()

    collection = db.nasdaq
    bulk = collection.initialize_unordered_bulk_op()
    insert_api(bulk, data)


def insert_yahoo(bulk, data):
    for item in data:
        date_chunks = item.get('Date').split('-')
        bulk.insert({
            "date": item.get('Date'),
            "date_pieces": {"year": date_chunks[0], "month": date_chunks[1], "day": date_chunks[2]},
            "open": item.get('Open'),
            "high": item.get('High'),
            "low": item.get('Low'),
            "close": item.get('Close'),
        })

    bulk.execute()


def yahoo_sp_500():
    print("SP 500 (Yahoo)")
    file = open('sp_500.csv', mode='rt', encoding="utf8")
    items = list(csv.DictReader(file, delimiter=','))

    collection = db.sp_500_yahoo
    bulk = collection.initialize_unordered_bulk_op()

    insert_yahoo(bulk, items)


def yahoo_nasdaq():
    print("NASDAQ (Yahoo)")
    file = open('nasdaq.csv', mode='rt', encoding="utf8")
    items = list(csv.DictReader(file, delimiter=','))

    collection = db.nasdaq_yahoo
    bulk = collection.initialize_unordered_bulk_op()

    insert_yahoo(bulk, items)


def yahoo_dow():
    print("DOW (Yahoo)")
    file = open('dow.csv', mode='rt', encoding="utf8")
    items = list(csv.DictReader(file, delimiter=','))

    collection = db.dow_yahoo
    bulk = collection.initialize_unordered_bulk_op()

    insert_yahoo(bulk, items)


yahoo_sp_500()
yahoo_dow()
yahoo_nasdaq()
sp_500()
dow()
nasdaq()

