<?php
session_start(); // Rozpoczęcie sesji
include 'config.php'; // Dołączenie pliku konfiguracyjnego bazy danych

// Sprawdzenie, czy użytkownik jest zalogowany
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Sprawdzenie, czy zalogowany użytkownik jest założycielem rodziny i czy budżet nie jest ustawiony
    $stmt = $conn->prepare("SELECT budzet, id_zalozyciela FROM rodziny WHERE id_zalozyciela = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
         // Ustawienie flagi, która decyduje o wyświetleniu monitu o ustawieniu budżetu
        if ($userId == $row['id_zalozyciela'] && is_null($row['budzet'])) {
            $_SESSION['prompt_for_budget'] = true;
        } else {
            $_SESSION['prompt_for_budget'] = false;
        }
    } else {
        $_SESSION['prompt_for_budget'] = false;
    }
    $stmt->close();

    if (isset($_SESSION['id_rodziny'])) {
        $familyId = $_SESSION['id_rodziny'];

    // Pobranie kategorii wydatków dla rodziny użytkownika
    $stmt = $conn->prepare("SELECT id, nazwa FROM kategorie_wydatkow WHERE id_rodziny IS NULL OR id_rodziny = ?");
    $stmt->bind_param("i", $familyId);
    $stmt->execute();
    $resultExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Pobranie kategorii dochodów dla rodziny użytkownika
    $stmt = $conn->prepare("SELECT id, nazwa FROM kategorie_dochodow WHERE id_rodziny IS NULL OR id_rodziny = ?");
    $stmt->bind_param("i", $familyId);
    $stmt->execute();
    $resultIncome = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $budgetAmount = 0;

    // Pobranie kwoty budżetu i waluty dla rodziny
    $stmt = $conn->prepare("SELECT budzet, waluta FROM rodziny WHERE id = ?");
    $stmt->bind_param("i", $familyId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $budgetAmount = $row['budzet'];
        $currencywaluta = $row['waluta'];
    } else {
        header("Location: login.php");
        exit();
    }
    $stmt->close();

}
} else {
    $_SESSION['prompt_for_budget'] = false;
    header("Location: login.php");
    exit();
}

$conn->close();

// Przygotowanie danych kategorii w formacie JSON do użycia w JavaScript
$categoriesJson = json_encode([
    'expenses' => $resultExpenses,
    'income' => $resultIncome
]);
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Zarządzanie Budżetem Domowym</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/home.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <div class="finance-dashboard">
            <div class="budget-section">
                <i class="material-icons">attach_money</i>
                <span>Twój budżet wynosi:</span>
                <span class="budget-amount"><?php echo htmlspecialchars(isset($budgetAmount) ? $budgetAmount : '0'); ?>
                    <?php echo htmlspecialchars($currencywaluta); ?></span>

            </div>

            <div class="switch-buttons">
                <button id="expensesButton" onclick="showCategories('wydatek')">Wydatki</button>
                <button id="incomeButton" onclick="showCategories('dochod')">Dochody</button>
            </div>

            <div class="time-filter-btn">
                <button data-time="day" onclick="filterByTime('day')">Dzień</button>
                <button data-time="week" onclick="filterByTime('week')">Tydzień</button>
                <button data-time="month" onclick="filterByTime('month')">Miesiąc</button>
                <button data-time="year" onclick="filterByTime('year')">Rok</button>

            </div>

            <div class="chart-container">
                <canvas id="myDoughnutChart"></canvas>

                <button class="add-transaction-button">
                    <i class="material-icons">add</i>
                </button>
            </div>

            <div class="categories-summary">

            </div>
        </div>
    </div>

    <div class="modal-backdrop"></div>

    <div id="budgetModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2>Witaj w aplikacji Zarządzanie budżetem domowym!</h2>
            <p>aplikacja do łatwego zarządzania dochodami i wydatkami</p>
            <button id="startBtn" onclick="showBudgetSetup()">Rozpocznij</button>
        </div>
    </div>

    <div id="addTransactionModal" class="modal-category">
        <div class="modal-content-category">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2>Dodaj Transakcję</h2>
            <form id="addTransactionForm">
                <input type="text" placeholder="Podaj kwotę" name="kwota">
                <select name="typ" id="transactionType">
                    <option value="wydatek">Wydatki</option>
                    <option value="dochod">Dochody</option>
                </select>
                <select name="id_kategorii" id="categorySelect">
                    <!-- Opcje kategorii będą dodawane dynamicznie przez JavaScript -->
                </select>
                <div id="dateSelectors">
                    <button type="button" id="todayButton">Dzisiaj</button>
                    <button type="button" id="yesterdayButton">Wczoraj</button>
                    <input type="date" id="transactionDate" name="data">
                </div>
                <textarea name="opis" placeholder="Komentarz"></textarea>
                <button type="submit">Dodaj</button>
            </form>
        </div>
    </div>
    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>
    <script type="text/javascript">
    var shouldShowBudgetModal = <?php echo $_SESSION['prompt_for_budget'] ? 'true' : 'false'; ?>;
    </script>
    <div id="categoriesData" data-wydatki='<?php echo json_encode($resultExpenses); ?>'
        data-dochody='<?php echo json_encode($resultIncome); ?>'>
    </div>

    <script src="main.js"></script>
    <script src="home.js"></script>
    <!-- <script src="categories.js"></script> -->
    <script>
    window.onload = function() {
        document.getElementById('expensesButton').click();
    };
    </script>
    <script type="text/javascript">
    // Pobierz id_rodziny z PHP i przekaż do JavaScript
    var idRodziny = <?php echo json_encode($_SESSION['id_rodziny'] ?? 'null'); ?>;
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        if (shouldShowBudgetModal) {
            showBudgetModal();
        }

        var sidebar = document.querySelector('.sidebar');
        var toggle = document.getElementById('toggleSidebar');

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });
    });
    </script>

</body>

</html>