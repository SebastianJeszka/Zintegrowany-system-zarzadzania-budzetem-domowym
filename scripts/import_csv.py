import sys
import pymysql
import pandas as pd
import json
from datetime import datetime
import logging

# Ustawienie konfiguracji logowania, aby zapisywać wszystkie wiadomości z poziomu INFO do pliku.
logging.basicConfig(filename='import_log.txt', level=logging.INFO, format='%(asctime)s %(message)s')

# Funkcja pomocnicza do zapisywania wiadomości do logu.
def log_message(message):
    logging.info(message)

# Dane do połączenia z bazą danych MySQL.
host = "localhost"
username = "root"
password = ""
database = "zarzadzanie_budzetem"

# Funkcja do dopasowywania kategorii transakcji na podstawie opisu.
def match_category(description, transaction_type, cursor):
    # Używamy LIKE %...% aby znaleźć pasujące słowo kluczowe w opisie transakcji.
    cursor.execute("""
        SELECT id_kategorii_wydatkow, id_kategorii_dochodow FROM slownik
        WHERE %s LIKE CONCAT('%%', slowo_kluczowe, '%%') AND typ_transakcji = %s
        LIMIT 1
    """, (description, transaction_type))
    
    result = cursor.fetchone()
    if result:
        # Zwraca ID kategorii w zależności od typu transakcji (wydatek/dochód).
        return result['id_kategorii_wydatkow'] if transaction_type == 'wydatek' else result['id_kategorii_dochodow']
    else:
        # Jeśli nie znaleziono pasującego słowa kluczowego, zwraca domyślne ID kategorii.
        return 11 if transaction_type == 'wydatek' else 4

# Główna funkcja przetwarzająca plik CSV i tworząca listę transakcji.
def process_csv(file_path, user_id, family_id, cursor):
    try:
        # Wstępne wczytanie pliku CSV do sprawdzenia formatu.
        sample_df = pd.read_csv(file_path, delimiter=';', encoding='utf-8', nrows=5)

        # Określenie typu banku na podstawie zawartości pliku.
        if 'Data waluty' in sample_df.columns and 'Tytulem' in sample_df.columns:
            bank_type = 'PKO'
            df = pd.read_csv(file_path, delimiter=';', encoding='utf-8', dtype=str)
        else:
            bank_type = 'mBank'
            # Dla mBanku wczytaj cały plik, pomijając odpowiednią liczbę wierszy
            df = pd.read_csv(file_path, delimiter=';', encoding='utf-8', skiprows=37, dtype=str)

        log_message(f"Typ banku: {bank_type}")
        log_message(f"Nagłówki kolumn: {df.columns.tolist()}")

        transactions = []
        for index, row in df.iterrows():
            # Ustalenie nazw kolumn w zależności od typu banku.
            date_column = 'Data waluty' if bank_type == 'PKO' else '#Data operacji'
            amount_column = 'Kwota operacji' if bank_type == 'PKO' else '#Kwota'
            description_column = 'Tytulem' if bank_type == 'PKO' else '#Opis operacji'
            secondary_description_column = 'Nadawca / Odbiorca' if bank_type == 'PKO' else '#Tytuł'

            if pd.notna(row[date_column]) and pd.notna(row[amount_column]):
                try:
                    date = datetime.strptime(row[date_column], '%d.%m.%Y').strftime('%Y-%m-%d')
                    amount = float(row[amount_column].replace(',', '.').replace(' ', ''))
                    if amount == 0.0:
                        continue
                except (ValueError, TypeError):
                    continue

                transaction_type = 'wydatek' if amount < 0 else 'dochod'
                amount = abs(amount)

                description = row[description_column] if pd.notna(row[description_column]) and row[description_column].strip() != '' else row[secondary_description_column] if pd.notna(row[secondary_description_column]) else ''

                # Specyficzna logika dla mBanku dotycząca opisu transakcji
                if bank_type == 'mBank':
                    full_title = row['#Tytuł'] if pd.notna(row['#Tytuł']) else ''
                    title_parts = full_title.split('DATA TRANSAKCJI:')
                    title = title_parts[0].strip() if title_parts else ''
                    if title:
                        description += f" - {title}"
                                # Logika dla PKO - używamy kolumny 'Tytułem' jako opis transakcji
                elif bank_type == 'PKO':
                    description = row['Tytulem'] if pd.notna(row['Tytulem']) and row['Tytulem'].strip() != '' else row['Nadawca / Odbiorca']

                # Dopasowanie kategorii transakcji na podstawie opisu.
                category_id = match_category(description, transaction_type, cursor)

                # Zbudowanie słownika reprezentującego transakcję.
                transaction = {
                    'user_id': user_id,
                    'family_id': family_id,
                    'date': date,
                    'description': description,
                    'amount': amount,
                    'transaction_type': transaction_type,
                    'category_id': category_id,
                }
                transactions.append(transaction)
    except Exception as e:
        log_message(f"Błąd przetwarzania: {str(e)}")
        return json.dumps({'error': str(e)})

    # Zwrócenie listy transakcji w formacie JSON.
    return json.dumps(transactions, ensure_ascii=False)

if __name__ == "__main__":
    file_path = sys.argv[1]
    user_id = sys.argv[2]
    family_id = sys.argv[3]

    # Połączenie z bazą danych.
    db = pymysql.connect(host=host, user=username, passwd=password, db=database, charset='utf8mb4')
    cursor = db.cursor(pymysql.cursors.DictCursor)

    try:
        processed_data = process_csv(file_path, user_id, family_id, cursor)
    finally:
        # Zamknięcie połączenia z bazą danych.
        cursor.close()
        db.close()

    # Wypisanie przetworzonych danych na standardowe wyjście.
    sys.stdout.buffer.write(processed_data.encode('utf-8'))
