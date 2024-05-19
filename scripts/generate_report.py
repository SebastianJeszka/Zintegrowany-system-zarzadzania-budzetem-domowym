import sys
import json
from fpdf import FPDF, XPos, YPos
import xlsxwriter
import sys
import json
import csv

class PDF(FPDF):
    def __init__(self):
        super().__init__()
        self.add_font('DejaVu', '', 'D:/xampp/htdocs/zarzadzanie_budzetem/scripts/fonts/DejaVuSansCondensed.ttf')
        self.add_font('DejaVu', 'B', 'D:/xampp/htdocs/zarzadzanie_budzetem/scripts/fonts/DejaVuSans-Bold.ttf')  # Pogrubiona
        self.add_font('DejaVu', 'I', 'D:/xampp/htdocs/zarzadzanie_budzetem/scripts/fonts/DejaVuSans-Oblique.ttf')  # Kursywa
        self.set_font('DejaVu', '', 14)
        self.set_margins(10, 10, 10)  # Marginesy po 10mm z każdej strony
        self.set_auto_page_break(auto=True, margin=10)  # Automatyczne łamanie strony z marginesem dolnym 10mm

    def header(self):
        self.set_font('DejaVu', 'B', 16)
        self.cell(0, 10, 'Raport Transakcji', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.ln(10)  # Dodajemy trochę przestrzeni po nagłówku

    def footer(self):
        self.set_y(-15)
        self.set_font('DejaVu', 'I', 8)
        self.cell(0, 10, 'Strona ' + str(self.page_no()), new_x=XPos.RIGHT, new_y=YPos.TOP)

    def table_header(self, header):
        self.set_fill_color(200, 220, 255)  # Ustawienie koloru tła dla nagłówków
        self.set_font('DejaVu', 'B', 12)  # Użycie czcionki pogrubionej dla nagłówków
        available_width = self.w - 2*self.l_margin  # Dostępna szerokość strony
        column_widths = [available_width / 5] * 5  # Pięć równych kolumn
        for column, width in zip(header, column_widths):
            self.cell(width, 10, column, border=1, fill=True, new_x=XPos.RIGHT, new_y=YPos.TOP)
        self.ln(10)  # Dodatkowy odstęp po nagłówkach

    def table_body(self, data):
        self.set_font('DejaVu', '', 10)  # Użycie normalnej czcionki dla treści tabeli
        available_width = self.w - 2*self.l_margin  # Dostępna szerokość strony
        column_widths = [available_width / 5] * 5  # Pięć równych kolumn dla danych
        for row in data:
            for i, field in enumerate(['typ', 'kwota', 'data', 'opis', 'kategoria']):
                self.cell(column_widths[i], 6, str(row.get(field, '')), border=1)
            self.ln(6)  # Przejście do nowego wiersza po każdym wierszu danych

            

# Funkcja do generowania PDF
def generate_pdf(data, output_file):
    pdf = PDF()
    pdf.add_page()
    
    # Dodanie nagłówka tabeli
    header = ['Typ transakcji', 'Kwota', 'Data', 'Opis', 'Kategoria']
    pdf.table_header(header)
    
    # Dodanie ciała tabeli
    pdf.table_body(data)
    
    # Zapisanie pliku PDF
    pdf.output(output_file)

# Funkcja do generowania raportu Excel
def generate_excel(data, output_file):
    workbook = xlsxwriter.Workbook(output_file)
    worksheet = workbook.add_worksheet()

    bold_centered = workbook.add_format({'bold': True, 'align': 'center'})
    money = workbook.add_format({'num_format': '0.00', 'align': 'center'})
    centered = workbook.add_format({'align': 'center'})

    worksheet.write_row('A1', ['Typ transakcji', 'Kwota', 'Data', 'Opis', 'Kategoria'], bold_centered)

    worksheet.set_column('A:A', 20, centered)  # Typ transakcji
    worksheet.set_column('B:B', 15, money)     # Kwota
    worksheet.set_column('C:C', 15, centered)  # Data
    worksheet.set_column('D:D', 30, centered)  # Opis
    worksheet.set_column('E:E', 20, centered)  # Kategoria

    row_num = 1
    for row in data:
        worksheet.write(row_num, 0, row['typ'], centered)
        worksheet.write(row_num, 1, row['kwota'], money)
        worksheet.write(row_num, 2, row['data'], centered)
        worksheet.write(row_num, 3, row['opis'], centered)
        worksheet.write(row_num, 4, row['kategoria'], centered)
        row_num += 1

    workbook.close()

# Funkcja do generowania raportu CSV z kodowaniem iso-8859-2
def generate_csv(data, output_file):
    with open(output_file, mode='w', newline='', encoding='iso-8859-2') as file:
        writer = csv.writer(file, delimiter=';')  # Użyj średnika jako separatora
        headers = ['Typ transakcji', 'Kwota', 'Data', 'Opis', 'Kategoria']
        writer.writerow(headers)
        for row in data:
            writer.writerow([row['typ'], row['kwota'], row['data'], row['opis'], row['kategoria']])



# Główna funkcja, która interpretuje argumenty i wywołuje odpowiednią funkcję generowania raportu
def generate_report(output_file, data):
    if output_file.endswith('.xlsx'):
        generate_excel(data, output_file)
    elif output_file.endswith('.pdf'):
        generate_pdf(data, output_file)
    elif output_file.endswith('.csv'):
        generate_csv(data, output_file)
    else:
        print("Nieobsługiwany format pliku.")


# Punkt wejścia skryptu
if __name__ == "__main__":
    if len(sys.argv) == 3:
        output_file = sys.argv[1]
        data_file = sys.argv[2]

        with open(data_file, 'r', encoding='utf-8') as file:
            data = json.load(file)

        generate_report(output_file, data)
    else:
        print("Nieprawidłowa liczba argumentów. Oczekiwano ścieżki do pliku wyjściowego i ścieżki do pliku danych JSON.")
