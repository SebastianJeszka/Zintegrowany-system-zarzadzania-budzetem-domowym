import json
import pandas as pd
import sys
from datetime import datetime
from statsmodels.tsa.arima.model import ARIMA
from pmdarima.arima.utils import ndiffs

# Słownik mapujący numery miesięcy na ich nazwy po polsku
nazwy_miesiecy = {
    1: "Styczen", 2: "Luty", 3: "Marzec", 4: "Kwiecien",
    5: "Maj", 6: "Czerwiec", 7: "Lipiec", 8: "Sierpien",
    9: "Wrzesien", 10: "Pazdziernik", 11: "Listopad", 12: "Grudzien"
}
# Pobierz ścieżkę do pliku JSON oraz wybrany miesiąc i rok ze skryptu PHP
json_file_path = sys.argv[1]
selected_year = int(sys.argv[2])
selected_month = int(sys.argv[3])

# Otwarcie i wczytanie danych z pliku JSON
with open(json_file_path, 'r') as file:
    data = json.load(file)
df = pd.DataFrame(data)

# Konwersja 'data' do formatu datetime i 'kwota' do liczby zmiennoprzecinkowej
df['data'] = pd.to_datetime(df['data'])
df['kwota'] = pd.to_numeric(df['kwota'], errors='coerce')

# Utworzenie serii czasowej z danych
df = df.set_index('data')
df = df.resample('M').sum()  # Suma miesięczna

# Ograniczenie danych do dnia przed wybranym miesiącem i rokiem
df = df[df.index < datetime(selected_year, selected_month, 1)]

# Określenie optymalnej liczby różnic (d) za pomocą funkcji ndiffs
d = ndiffs(df['kwota'], test='adf')

# Utworzenie i dopasowanie modelu ARIMA
model = ARIMA(df['kwota'], order=(1, d, 1))  # Możesz dostosować parametry (p, d, q)
fitted_model = model.fit()

# Prognozowanie na następne miesiące
forecast = fitted_model.forecast(steps=selected_month - df.index[-1].month + (12 * (selected_year - df.index[-1].year)))

# Uzyskanie nazwy miesiąca i wyświetlenie prognozowanej kwoty z ograniczeniem do dwóch miejsc po przecinku
nazwa_miesiaca = nazwy_miesiecy[selected_month]
print(f"Prognozowana kwota na {nazwa_miesiaca}: {forecast[-1]:.2f}")
