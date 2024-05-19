<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego z ustawieniami bazy danych.
session_start(); // Rozpoczęcie sesji, aby można było korzystać z zmiennych sesyjnych.

// Sprawdzenie, czy użytkownik jest zalogowany. Jeśli nie, przekierowanie do strony logowania.
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id']; // Pobranie ID zalogowanego użytkownika z sesji.
$family_id = isset($_SESSION['id_rodziny']) ? $_SESSION['id_rodziny'] : null; // Pobranie ID rodziny z sesji, jeśli istnieje.

// Pobieranie waluty dla rodziny użytkownika z bazy danych.
$query_currency = "SELECT waluta FROM rodziny WHERE id = ?";
if ($stmt_currency = $conn->prepare($query_currency)) {
    $stmt_currency->bind_param("i", $family_id);
    $stmt_currency->execute();
    $stmt_currency->bind_result($currency); // Przypisanie wyniku zapytania do zmiennej $currency.
    $stmt_currency->fetch(); // Pobranie wyników zapytania.
    $stmt_currency->close(); // Zamknięcie zapytania.
}

$kategorie_dochodow = [];
$kategorie_wydatkow = [];

// Pobieranie unikalnych kategorii dochodów z bazy danych.
$query_dochody = "SELECT DISTINCT nazwa FROM kategorie_dochodow";
$result_dochody = $conn->query($query_dochody);
while($row = $result_dochody->fetch_assoc()) {
    $kategorie_dochodow[] = $row['nazwa']; // Dodanie nazwy kategorii do tablicy kategorii dochodów.
}

// Pobieranie unikalnych kategorii wydatków z bazy danych.
$query_wydatki = "SELECT DISTINCT nazwa FROM kategorie_wydatkow";
$result_wydatki = $conn->query($query_wydatki);
while($row = $result_wydatki->fetch_assoc()) {
    $kategorie_wydatkow[] = $row['nazwa'];
}

// Obsługa formularza po jego wysłaniu.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pobranie danych z formularza.
    $selected_type = $_POST['typ_transakcji'] ?? null;
    $selected_category = $_POST['kategoria'] ?? null;
    $sort_order = $_POST['sort_order'] ?? null;
    $date_range = $_POST['date_range'] ?? null;
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    
    
    // Przygotowanie bazowego zapytania SQL dla transakcji.
    $query = "SELECT t.typ, t.kwota, t.data, t.opis, 
              CASE 
                WHEN t.typ = 'wydatek' THEN (SELECT nazwa FROM kategorie_wydatkow WHERE id = t.id_kategorii)
                WHEN t.typ = 'dochod' THEN (SELECT nazwa FROM kategorie_dochodow WHERE id = t.id_kategorii)
              END as kategoria
              FROM transakcje t
              WHERE t.id_rodziny = ?";

    // Przygotowanie typów i wartości parametrów do zapytania.
    $param_type = 'i'; // Typ parametru (i - integer).
    $param_values = [$family_id]; // Tablica wartości parametrów.

    // Dodanie warunków do zapytania w zależności od wybranych opcji.
    // Warunki dla typu transakcji.
    if ($selected_type) {
        $query .= " AND t.typ = ?";
        $param_type .= 's'; // Dodanie typu parametru (s - string).
        $param_values[] = $selected_type;
    }
    // Warunki dla kategorii.
    if ($selected_category) {
        $query .= " AND (t.id_kategorii IN (SELECT id FROM kategorie_wydatkow WHERE nazwa = ?) OR t.id_kategorii IN (SELECT id FROM kategorie_dochodow WHERE nazwa = ?))";
        $param_type .= 'ss'; // Dodanie typów parametrów.
        $param_values[] = $selected_category;
        $param_values[] = $selected_category;
    }
    // Warunki dla zakresu dat.
    if ($date_range && $date_range !== 'custom') {
        // Logika do ustawiania zakresu dat na podstawie wybranej opcji (dzisiaj, ostatni tydzień, miesiąc, rok).
        $today = date('Y-m-d');
        switch ($date_range) {
            case 'day':
                $date_from = $today;
                $date_to = $today;
                break;
            case 'week':
                $date_from = date('Y-m-d', strtotime('-1 week'));
                $date_to = $today;
                break;
            case 'month':
                $date_from = date('Y-m-d', strtotime('-1 month'));
                $date_to = $today;
                break;
            case 'year':
                $date_from = date('Y-m-d', strtotime('-1 year'));
                $date_to = $today;
                break;
        }
        $query .= " AND t.data BETWEEN ? AND ?";
        $param_type .= 'ss'; // Dodanie typów parametrów.
        $param_values[] = $date_from;
        $param_values[] = $date_to;
    } elseif ($date_from && $date_to) {
        $query .= " AND t.data BETWEEN ? AND ?";
        $param_type .= 'ss'; // Dodanie typów parametrów.
        $param_values[] = $date_from;
        $param_values[] = $date_to;
    }

    // Dodanie sortowania do zapytania.
    if ($sort_order) {
        $query .= $sort_order === 'ASC' ? " ORDER BY t.kwota ASC" : " ORDER BY t.kwota DESC";
    }

    // Przygotowanie zapytania z użyciem dynamicznego przypisania parametrów.
    $stmt = $conn->prepare($query);
    $bind_names = array($param_type);
    for ($i = 0; $i < count($param_values); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $param_values[$i];
        $bind_names[] = &$$bind_name;
    }

    call_user_func_array(array($stmt, 'bind_param'), $bind_names);

    // Wykonanie zapytania i pobranie wyników.
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        // Zapisanie wyników do tablicy.
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        // Zapisanie danych do pliku JSON.
        $tempFile = 'D:\temp\data' . uniqid() . '.json';
        if (file_put_contents($tempFile, json_encode($results)) === false) {
            echo "Nie udało się zapisać danych do pliku JSON.";
            exit;
        }
    
        // Generowanie nazwy pliku raportu na podstawie wybranego formatu.
        $report_format = $_POST['report_format'] ?? 'pdf'; // Domyślnie format PDF.
        $output_file = "raport_" . time() . ($report_format == 'pdf' ? '.pdf' : ($report_format == 'excel' ? '.xlsx' : '.csv'));
        $output_path = 'D:\\xampp\\htdocs\\zarzadzanie_budzetem\\RAPORTY\\' . $output_file;
    
        // Wywołanie skryptu Python do generowania raportu.
        $escaped_output_path = escapeshellarg($output_path);
        $escaped_temp_file = escapeshellarg($tempFile);
        $command = "python scripts/generate_report.py $escaped_output_path $escaped_temp_file";
        exec($command, $output, $return_var);

        // Usunięcie pliku JSON po wygenerowaniu raportu.
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Sprawdzenie, czy generowanie raportu się powiodło.
        if ($result) {
            if ($return_var == 0) {
                // Sukces, przechowanie ścieżki do wygenerowanego raportu w sesji.
                $_SESSION['generated_report_path'] = $output_path;
                $_SESSION['generated_report_success'] = "Raport został wygenerowany pomyślnie.";
            } else {
                $_SESSION['generated_report_success'] = "Nie udało się wygenerować raportu.";
            }
}
        }}
    ?>


<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Generowanie raportów</title>
    <!-- <link rel="stylesheet" href="css/style2.css"> -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/report_generation.css">
</head>

<body>

    <header>
        <h1>ZARZĄDZANIE BUDŻETEM DOMOWYM</h1>
        <div class="logo-container">
            <img src="logo.png" alt="BudgetMaster Logo"> <!-- Upewnij się, że ścieżka do obrazu jest prawidłowa -->
        </div>
    </header>

    <div class="sidebar">
        <a href="#" id="toggleSidebar"><span class="material-icons">menu</span><span class="link-text">Ukryj
                menu</span></a>
        <a href="index.php"><span class="material-icons">home</span><span class="link-text">Strona główna</span></a>
        <a href="categories.php" class="categories"><span class="material-icons">category</span><span
                class="link-text">Kategorie</span></a>
        <a href="expense_forecast.php" class="payments"><span class="material-icons">payment</span><span
                class="link-text">Prognoza wydatków</span></a>
        <a href="currency.php" class="currency"><span class="material-icons">attach_money</span><span
                class="link-text">Waluta</span></a>
        <a href="bank_import.php" class="import"><span class="material-icons">import_export</span><span
                class="link-text">Import bankowy</span></a>
        <a href="history.php" class="history"><span class="material-icons">history</span><span
                class="link-text">Historia</span></a>
        <a href="report_generation.php" class="report"><span class="material-icons">assessment</span><span
                class="link-text">Generuj raport</span></a>
        <a href="settings.php" class="settings"><span class="material-icons">settings</span><span
                class="link-text">Ustawienia</span></a>
        <a href="login.php" class="logout"><span class="material-icons">logout</span><span class="link-text">Wyloguj
                się</span></a>
    </div>

    <div id="mainContent" class="content">
        <div class="report-container">
            <h1> Generowanie Raportów </h1>
            <form method="post" action="" class="report-form">
                <!-- Transaction Type Selector -->
                <select name="typ_transakcji" id="typ_transakcji" class="select-transaction-type"
                    onchange="updateCategories()">
                    <option value="">Wybierz typ</option>
                    <option value="dochod">Dochód</option>
                    <option value="wydatek">Wydatek</option>
                </select>

                <!-- Category Selector -->
                <select name="kategoria" id="kategoria" class="select-category">
                    <option value="">Wybierz kategorię</option>
                    <!-- Options will be filled by JavaScript -->
                </select>

                <!-- Sort Order Selector -->
                <select name="sort_order" class="select-sort-order">
                    <option value="">Sortuj według kwoty</option>
                    <option value="ASC">Rosnąco</option>
                    <option value="DESC">Malejąco</option>
                </select>

                <!-- Date Range Selector -->
                <select name="date_range" id="date_range" class="select-date-range" onchange="toggleDateInputs()">
                    <option value="custom">Wybierz zakres dat</option>
                    <option value="day">Dzień</option>
                    <option value="week">Tydzień</option>
                    <option value="month">Miesiąc</option>
                    <option value="year">Rok</option>
                </select>
                <!-- Date Filtering Inputs -->
                <div class="input-group date-from">
                    <label for="date_from">Od:</label>
                    <input type="date" name="date_from" id="date_from" class="input-date-from" disabled>
                </div>
                <div class="input-group date-to">
                    <label for="date_to">Do:</label>
                    <input type="date" name="date_to" id="date_to" class="input-date-to" disabled>
                </div>

                <!-- Report Format Selector -->
                <select name="report_format" id="report_format" class="select-report-format">
                    <option value="">Wybierz format raportu</option>
                    <option value="pdf">PDF</option>
                    <option value="csv">CSV</option>
                    <option value="excel">Excel</option>
                </select>

                <!-- Submit Button -->
                <input type="submit" value="Generuj Raport" class="submit-report">
            </form>

            <div class="report-container">
                <!-- ... -->
                <?php
        if (isset($_SESSION['generated_report_success'])) {
            // Wyświetl komunikat o sukcesie i link do pliku raportu
            echo '<div class="alert alert-success">' . $_SESSION['generated_report_success'] . '</div>';
            if (isset($_SESSION['generated_report_path'])) {
                $reportPath = str_replace('D:\\xampp\\htdocs', '', $_SESSION['generated_report_path']);
                echo '<a href="' . $reportPath . '" target="_blank">Kliknij tutaj, aby pobrać raport</a>';
                // Po wyświetleniu komunikatu, usuń te dane z sesji
                unset($_SESSION['generated_report_success']);
                unset($_SESSION['generated_report_path']);
            }
        }
        ?>
                <!-- Formularz i reszta strony ... -->
            </div>
        </div>


        <footer>
            <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
                Informatyki - 2023</p>
        </footer>
        <script>
        // Funkcja JavaScript do aktualizacji kategorii
        function updateCategories() {
            var typTransakcji = document.getElementById('typ_transakcji').value;
            var selectKategoria = document.getElementById('kategoria');
            selectKategoria.innerHTML = ''; // Czyszczenie obecnych opcji
            var defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.text = 'Wybierz kategorię';
            selectKategoria.appendChild(defaultOption);

            if (typTransakcji === 'dochod') {
                <?php foreach($kategorie_dochodow as $kategoria): ?>
                var option = document.createElement('option');
                option.value = '<?php echo $kategoria; ?>';
                option.text = '<?php echo $kategoria; ?>';
                selectKategoria.appendChild(option);
                <?php endforeach; ?>
            } else if (typTransakcji === 'wydatek') {
                <?php foreach($kategorie_wydatkow as $kategoria): ?>
                var option = document.createElement('option');
                option.value = '<?php echo $kategoria; ?>';
                option.text = '<?php echo $kategoria; ?>';
                selectKategoria.appendChild(option);
                <?php endforeach; ?>
            }
        }

        function toggleDateInputs() {
            var range = document.getElementById('date_range').value;
            var dateFrom = document.getElementById('date_from');
            var dateTo = document.getElementById('date_to');

            // Włączanie inputów dat, tylko gdy wybrana jest opcja 'custom'
            var isCustomDateRange = range === 'custom';
            dateFrom.disabled = !isCustomDateRange;
            dateTo.disabled = !isCustomDateRange;

            // Czyszczenie wartości, gdy wybrany jest zakres dat inny niż 'custom'
            if (!isCustomDateRange) {
                dateFrom.value = '';
                dateTo.value = '';
            }
        }


        document.addEventListener('DOMContentLoaded', function() {

            toggleDateInputs(); // Ustawienie początkowego stanu pól daty

            var sidebar = document.querySelector('.sidebar');
            var toggle = document.getElementById('toggleSidebar');

            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('expanded');
            });


        });
        </script>
</body>

</html>