<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego bazy danych.
session_start(); // Rozpoczęcie sesji.

// Sprawdzenie, czy użytkownik jest zalogowany.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$family_id = isset($_SESSION['id_rodziny']) ? $_SESSION['id_rodziny'] : null;

// Pobieranie waluty dla rodziny użytkownika.
$query_currency = "SELECT waluta FROM rodziny WHERE id = ?";
if ($stmt_currency = $conn->prepare($query_currency)) {
    $stmt_currency->bind_param("i", $family_id);
    $stmt_currency->execute();
    $stmt_currency->bind_result($currency);
    $stmt_currency->fetch();
    $stmt_currency->close();
}

// Pobieranie unikalnych kategorii dla dochodów i wydatków.
$kategorie_dochodow = [];
$kategorie_wydatkow = [];

// Zapytanie do bazy danych dla kategorii dochodów
$query_dochody = "SELECT DISTINCT nazwa FROM kategorie_dochodow";
$result_dochody = $conn->query($query_dochody);
while($row = $result_dochody->fetch_assoc()) {
    $kategorie_dochodow[] = $row['nazwa'];
}

// Zapytanie do bazy danych dla kategorii wydatków
$query_wydatki = "SELECT DISTINCT nazwa FROM kategorie_wydatkow";
$result_wydatki = $conn->query($query_wydatki);
while($row = $result_wydatki->fetch_assoc()) {
    $kategorie_wydatkow[] = $row['nazwa'];
}

// Inicjalizacja zmiennych do filtrowania transakcji na podstawie kryteriów wybranych przez użytkownika.
$selected_type = isset($_POST['typ_transakcji']) ? $_POST['typ_transakcji'] : null;
$selected_category = isset($_POST['kategoria']) ? $_POST['kategoria'] : null;
$sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : null;
$date_range = isset($_POST['date_range']) ? $_POST['date_range'] : null;
$date_from = isset($_POST['date_from']) && $_POST['date_from'] ? $_POST['date_from'] : null;
$date_to = isset($_POST['date_to']) && $_POST['date_to'] ? $_POST['date_to'] : null;

// Bazowe zapytanie SQL do pobierania transakcji z uwzględnieniem filtrów.
$query = "SELECT t.typ, t.kwota, t.data, t.opis, 
          CASE 
            WHEN t.typ = 'wydatek' THEN (SELECT nazwa FROM kategorie_wydatkow WHERE id = t.id_kategorii)
            WHEN t.typ = 'dochod' THEN (SELECT nazwa FROM kategorie_dochodow WHERE id = t.id_kategorii)
          END as kategoria
          FROM transakcje t
          WHERE t.id_rodziny = ?";

// Przygotowanie parametrów zapytania.
$param_type = 'i'; // Typ parametru (i - integer).
$param_values = [$family_id]; // Lista parametrów.

// Dodanie warunków do zapytania na podstawie wybranych filtrów.
if ($selected_type) {
    $query .= " AND t.typ = ?";
    $param_type .= 's';
    $param_values[] = $selected_type;
}

if ($selected_category) {
    $query .= " AND (t.id_kategorii IN (SELECT id FROM kategorie_wydatkow WHERE nazwa = ?) OR t.id_kategorii IN (SELECT id FROM kategorie_dochodow WHERE nazwa = ?))";
    $param_type .= 'ss';
    $param_values[] = $selected_category;
    $param_values[] = $selected_category;
}

// Dodanie warunków dla daty, jeśli zostały wybrane
if ($date_range && $date_range !== 'custom') {
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
    $param_type .= 'ss';
    $param_values[] = $date_from;
    $param_values[] = $date_to;
} elseif ($date_from && $date_to) {
    // Dodanie warunków dla własnego zakresu dat
    $query .= " AND t.data BETWEEN ? AND ?";
    $param_type .= 'ss';
    $param_values[] = $date_from;
    $param_values[] = $date_to;
}

// Dodanie sortowania, jeśli zostało wybrane
if ($sort_order) {
    $query .= $sort_order === 'ASC' ? " ORDER BY t.kwota ASC" : " ORDER BY t.kwota DESC";
}

// Wykonanie przygotowanego zapytania.
$stmt = $conn->prepare($query);

// Dynamiczne przypisanie parametrów
$bind_names = array($param_type);
for ($i = 0; $i < count($param_values); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $param_values[$i];
    $bind_names[] = &$$bind_name;
}

call_user_func_array(array($stmt, 'bind_param'), $bind_names);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Historia</title>
    <!-- <link rel="stylesheet" href="css/style2.css"> -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/history.css">
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
        <div class="history-container">
            <h1>Historia</h1>
            <form method="post" action="">
                <select name="typ_transakcji" id="typ_transakcji" onchange="updateCategories()">
                    <option value="">Wybierz typ</option>
                    <option value="dochod">Dochód</option>
                    <option value="wydatek">Wydatek</option>
                </select>
                <select name="kategoria" id="kategoria">
                    <option value="">Wybierz kategorię</option>
                    <!-- Opcje zostaną wypełnione przez JavaScript -->
                </select>
                <select name="sort_order">
                    <option value="">Sortuj według kwoty</option>
                    <option value="ASC">Rosnąco</option>
                    <option value="DESC">Malejąco</option>
                </select>
                <!-- Wybór zakresu dat -->
                <select name="date_range" id="date_range" onchange="toggleDateInputs()">
                    <option value="custom">Wybierz zakres dat</option>
                    <option value="day">Dzień</option>
                    <option value="week">Tydzień</option>
                    <option value="month">Miesiąc</option>
                    <option value="year">Rok</option>
                </select>


                <!-- Filtrowanie po dacie od do -->
                <div class="input-group">
                    <label for="date_from">Od:</label>
                    <input type="date" name="date_from" id="date_from" disabled>
                </div>
                <div class="input-group">
                    <label for="date_to">Do:</label>
                    <input type="date" name="date_to" id="date_to" disabled>
                </div>
                <input type="submit" value="Filtruj">
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Typ transakcji</th>
                        <th>Kwota</th>
                        <th>Data</th>
                        <th>Opis</th>
                        <th>Kategoria</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['typ']) ?></td>
                        <td><?= htmlspecialchars($row['kwota']) . ' ' . $currency ?></td>
                        <!-- Tutaj wyświetlamy kwotę z walutą -->
                        <td><?= htmlspecialchars($row['data']) ?></td>
                        <td><?= htmlspecialchars($row['opis']) ?></td>
                        <td><?= htmlspecialchars($row['kategoria']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>
    <script>
    // Definicja funkcji do aktualizacji opcji w selektorze kategorii na podstawie wybranego typu transakcji.
    function updateCategories() {
        // Pobranie wybranego typu transakcji z elementu <select>
        var typTransakcji = document.getElementById('typ_transakcji').value;
        var selectKategoria = document.getElementById('kategoria');
        selectKategoria.innerHTML = ''; // Usunięcie obecnych opcji z selektora.

        // Dodanie domyślnej opcji zachęcającej do wyboru.
        var defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.text = 'Wybierz kategorię';
        selectKategoria.appendChild(defaultOption);

        // Na podstawie typu transakcji dodaj opcje kategorii dochodów lub wydatków.
        if (typTransakcji === 'dochod') {
            // Pętla PHP dodająca opcje dla kategorii dochodów.
            <?php foreach($kategorie_dochodow as $kategoria): ?>
            var option = document.createElement('option');
            option.value = '<?php echo $kategoria; ?>';
            option.text = '<?php echo $kategoria; ?>';
            selectKategoria.appendChild(option);
            <?php endforeach; ?>
        } else if (typTransakcji === 'wydatek') {
            // Pętla PHP dodająca opcje dla kategorii wydatków.
            <?php foreach($kategorie_wydatkow as $kategoria): ?>
            var option = document.createElement('option');
            option.value = '<?php echo $kategoria; ?>';
            option.text = '<?php echo $kategoria; ?>';
            selectKategoria.appendChild(option);
            <?php endforeach; ?>
        }
    }

    // Funkcja do włączania i wyłączania pól dat na podstawie wybranego zakresu.
    function toggleDateInputs() {
        var range = document.getElementById('date_range').value;
        var dateFrom = document.getElementById('date_from');
        var dateTo = document.getElementById('date_to');

        // Włączenie pól dat tylko gdy użytkownik wybrał opcję 'custom'.
        var isCustomDateRange = range === 'custom';
        dateFrom.disabled = !isCustomDateRange;
        dateTo.disabled = !isCustomDateRange;

        // Czyszczenie wartości pól dat, gdy zakres dat nie jest niestandardowy.
        if (!isCustomDateRange) {
            dateFrom.value = '';
            dateTo.value = '';
        }
    }

    // Obsługa zdarzenia załadowania DOM.
    document.addEventListener('DOMContentLoaded', function() {

        toggleDateInputs(); // Inicjalizacja stanu pól dat przy ładowaniu strony.

        // Obsługa rozwijanego menu bocznego.
        var sidebar = document.querySelector('.sidebar');
        var toggle = document.getElementById('toggleSidebar');

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded'); // Przełączenie widoczności menu bocznego.
        });


    });
    </script>
</body>

</html>