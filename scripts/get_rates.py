import requests
import sys

def get_rates(base_currency, access_key):
    response = requests.get(f'http://api.exchangeratesapi.io/latest?base={base_currency}&access_key={access_key}')
    
    if response.status_code == 200:
        return response.json()
    else:
        raise Exception('API request failed with status code ' + str(response.status_code))

if __name__ == '__main__':
    base_currency = 'EUR'
    access_key = 'ca0df6aea55d7103c56fcb49387f1c74'
    try:
        rates = get_rates(base_currency, access_key)
        print(rates)
    except Exception as e:
        print(str(e), file=sys.stderr)
