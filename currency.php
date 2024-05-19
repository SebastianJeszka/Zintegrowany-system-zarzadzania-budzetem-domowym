<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego bazy danych.
session_start(); // Rozpoczęcie sesji, aby można było korzystać z zmiennych sesyjnych.

// Sprawdzenie, czy użytkownik jest zalogowany. Jeśli nie, przekierowanie do strony logowania.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Pobranie ID zalogowanego użytkownika.
$budgetAmount = 0; // Zmienna przechowująca kwotę budżetu.
$convertedAmount = 0; // Zmienna przechowująca przeliczoną kwotę.
$selectedCurrency = ''; // Zmienna przechowująca wybraną walutę.
$currency = ''; // Domyślna wartość waluty, może być zmieniona później.
$allowedCurrencies = ['PLN', 'USD', 'EUR', 'GBP', 'CHF', 'CNY']; // Tablica dozwolonych walut.

// Pobranie ID rodziny użytkownika.
$stmt = $conn->prepare("SELECT id_rodziny FROM uzytkownicy WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $family_id = $row['id_rodziny'];

   // Pobranie budżetu i waluty rodziny.
    $stmt = $conn->prepare("SELECT budzet, waluta FROM rodziny WHERE id = ?");
    $stmt->bind_param("i", $family_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $budgetAmount = $row['budzet'];
        $currency = $row['waluta']; // Pobrana waluta z bazy danych
    }
}
$stmt->close();

// Sprawdzenie, czy wybrana waluta jest wśród dozwolonych walut.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['currency'])) {
    $selectedCurrency = $_POST['currency'];
    
    // Upewnij się, że waluta jest jedną z dozwolonych
    if (in_array($selectedCurrency, $allowedCurrencies)) {
        // Twój klucz API
        $accessKey = 'ca0df6aea55d7103c56fcb49387f1c74';
        
        // Pobieranie aktualnych kursów wymiany walut z zewnętrznego API.
        $apiResponse = file_get_contents("http://api.exchangeratesapi.io/latest?access_key={$accessKey}&symbols=PLN,USD,EUR,GBP,CHF,CNY");
        $rates = json_decode($apiResponse, true)['rates'];
        
         // Przeliczanie kwoty budżetu na EUR, jeśli aktualna waluta budżetu nie jest EUR, w celu ułatwienia konwersji.
         if ($currency !== 'EUR') {
            $rateToEur = $rates[$currency]; // kurs z aktualnej waluty budżetu na EUR
            $budgetInEur = $budgetAmount / $rateToEur;
        } else {
            $budgetInEur = $budgetAmount; // Jeśli waluta jest już EUR, nie ma potrzeby przeliczania.
        }
        
        // Przeliczanie z EUR na wybraną walutę.
        if ($selectedCurrency !== 'EUR') {
            $rateFromEur = $rates[$selectedCurrency]; // kurs z EUR na wybraną walutę
            $convertedAmount = $budgetInEur * $rateFromEur;
        } else {
            $convertedAmount = $budgetInEur; // Jeśli wybrana waluta to EUR, nie ma potrzeby ponownego przeliczania.
        }
        
        // Rozpoczęcie transakcji w bazie danych.
        $conn->begin_transaction();
        
         // Aktualizacja budżetu i waluty w bazie danych.
         $updateStmt = $conn->prepare("UPDATE rodziny SET budzet = ?, waluta = ? WHERE id = ?");
         $updateStmt->bind_param("dsi", $convertedAmount, $selectedCurrency, $family_id);
         $updateStmt->execute();

          // Sprawdź, czy aktualizacja się powiodła
          if ($updateStmt->affected_rows > 0) {
            // Jeśli tak, zatwierdź transakcję
            $conn->commit();
            echo "Budżet zaktualizowany.";
        } else {
            // Jeśli nie, wycofaj transakcję
            $conn->rollback();
            echo "Aktualizacja budżetu nie powiodła się.";
        }

        // Sprawdzenie, czy aktualizacja się powiodła i zatwierdzenie transakcji.
        if ($updateStmt->affected_rows > 0) {
            $updateStmt->close();
            
            // Pobierz wszystkie transakcje dla rodziny
            $transactionStmt = $conn->prepare("SELECT id, kwota FROM transakcje WHERE id_rodziny = ?");
            $transactionStmt->bind_param("i", $family_id);
            $transactionStmt->execute();
            $transactionsResult = $transactionStmt->get_result();

            if ($transactionsResult->num_rows > 0) {
                while ($transaction = $transactionsResult->fetch_assoc()) {
                    $transactionId = $transaction['id'];
                    $transactionAmount = $transaction['kwota'];

                    // Przelicz kwotę transakcji
                    if ($currency !== 'EUR') {
                        $transactionInEur = $transactionAmount / $rateToEur;
                    } else {
                        $transactionInEur = $transactionAmount;
                    }

                    if ($selectedCurrency !== 'EUR') {
                        $convertedTransactionAmount = $transactionInEur * $rateFromEur;
                    } else {
                        $convertedTransactionAmount = $transactionInEur;
                    }

                    // Zaktualizuj kwotę transakcji w bazie danych
                    $updateTransactionStmt = $conn->prepare("UPDATE transakcje SET kwota = ? WHERE id = ?");
                    $updateTransactionStmt->bind_param("di", $convertedTransactionAmount, $transactionId);
                    if (!$updateTransactionStmt->execute()) {
                        echo "Błąd przy aktualizacji transakcji: " . mysqli_error($conn);
                        $conn->rollback();
                        $updateTransactionStmt->close();
                        exit;
                    }

                    // Sprawdź, czy aktualizacja się powiodła
                    if ($updateTransactionStmt->affected_rows == 0) {
                        // Jeśli aktualizacja się nie powiodła, wycofaj transakcję
                        $conn->rollback();
                        echo "Aktualizacja transakcji nie powiodła się. Żadne wiersze nie zostały zaktualizowane.";
                        $updateTransactionStmt->close();
                        exit;
                    }

                    $updateTransactionStmt->close();
                }
            }

            $transactionStmt->close();
            // Jeśli wszystkie aktualizacje się powiodły, zatwierdź transakcję
            $conn->commit();

            // Wyświetl kwotę po przeliczeniu
            echo "Kwota po przeliczeniu: " . number_format($convertedAmount, 2) . " $selectedCurrency";
        } else {
            // Jeśli nie, wycofaj transakcję
            $conn->rollback();
            echo "Aktualizacja budżetu nie powiodła się.";
            $updateStmt->close();
        }
    } else {
        echo "Wybrana waluta nie jest obsługiwana.";
    }
    // Po zakończeniu wszystkich operacji, przekieruj z powrotem na tę samą stronę
    header('Location: currency.php');
    exit;
}
// Obsługa formularza konwersji waluty - zbiera dane z formularza i wykonuje przeliczenie walut.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['convert'])) {
    $amount = $_POST['amount'];
    $fromCurrency = $_POST['fromCurrency'];
    $toCurrency = $_POST['toCurrency'];

    // Sprawdzenie, czy wybrane waluty są dozwolone.
    if (in_array($fromCurrency, $allowedCurrencies) && in_array($toCurrency, $allowedCurrencies)) {
        // Klucz API do pobierania kursów walut.
        $accessKey = 'ca0df6aea55d7103c56fcb49387f1c74';

        // Pobranie kursów wymiany i przeliczenie kwoty.
        $apiResponse = file_get_contents("http://api.exchangeratesapi.io/latest?access_key={$accessKey}&symbols=PLN,USD,EUR,GBP,CHF,CNY");
        $rates = json_decode($apiResponse, true)['rates'];

        // Przeliczanie z fromCurrency na toCurrency
        if ($fromCurrency !== 'EUR') {
            $rateToEur = $rates[$fromCurrency]; // kurs z fromCurrency na EUR
            $amountInEur = $amount / $rateToEur;
        } else {
            $amountInEur = $amount; // Kwota jest już w EUR
        }

        if ($toCurrency !== 'EUR') {
            $rateFromEur = $rates[$toCurrency]; // kurs z EUR na toCurrency
            $convertedAmount = $amountInEur * $rateFromEur;
        } else {
            $convertedAmount = $amountInEur; // Użytkownik wybrał EUR, więc nie trzeba przeliczać
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Ustawienia</title>
    <link rel="stylesheet" href="css/currency.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body>

    <header>
        <h1>ZARZĄDZANIE BUDŻETEM DOMOWYM</h1>
        <div class="logo-container">
            <img src="logo.png" alt="BudgetMaster Logo">
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
        <div class="currency-container">
            <div class="budget-section">
                <i class="material-icons">attach_money</i>
                <span>Twój budżet wynosi:</span>
                <span class="budget-amount"><?php echo htmlspecialchars(isset($budgetAmount) ? $budgetAmount : '0'); ?>
                    <?php echo $currency; ?></span>
            </div>

            <div id="currency-calculator-container" class="currency-calculator">
                <form action="currency.php" method="post">
                    <div class="form-group">
                        <label for="amount">Kwota:</label>
                        <input type="number" id="amount" name="amount" placeholder="Wpisz kwotę" required>
                    </div>
                    <div class="form-group">
                        <label for="fromCurrency">Z waluty:</label>
                        <select id="fromCurrency" name="fromCurrency" required>
                            <option value="PLN" selected>PLN</option>
                            <option value="EUR">EUR</option>
                            <option value="CHF">CHF</option>
                            <option value="USD">USD</option>
                            <option value="GBP">GBP</option>
                            <option value="CNY">CNY</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="toCurrency">Na walutę:</label>
                        <select id="toCurrency" name="toCurrency" required>
                            <option value="PLN">PLN</option>
                            <option value="EUR" selected>EUR</option>
                            <option value="CHF">CHF</option>
                            <option value="USD">USD</option>
                            <option value="GBP">GBP</option>
                            <option value="CNY">CNY</option>
                        </select>
                    </div>
                    <button type="submit" name="convert" id="convert">Przelicz</button>
                </form>
                <div id="conversionResult">
                    <?php if(isset($convertedAmount)): ?>
                    <p style="color:#333;">Przewalutowana wartość: <?php echo number_format($convertedAmount, 2); ?>
                        <?php echo $selectedCurrency; ?></p>

                    <?php endif; ?>
                </div>
            </div>

            <h2 class="currency-title">Wybierz domyślną walutę</h2>
            <div class="button-container">
                <form action="currency.php" method="post">
                    <button type="submit" name="currency" value="PLN" class="first-group">Złotówka (PLN)</button>
                    <button type="submit" name="currency" value="USD" class="first-group">Dolar (USD)</button>
                    <button type="submit" name="currency" value="EUR" class="first-group">Euro (EUR)</button>
                    <button type="submit" name="currency" value="GBP" class="second-group">Funt (GBP)</button>
                    <button type="submit" name="currency" value="CHF" class="second-group">Frank (CHF)</button>
                    <button type="submit" name="currency" value="CNY" class="second-group">Juan (CNY)</button>
                </form>
            </div>

        </div>
    </div>

    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        var sidebar = document.querySelector('.sidebar');
        var toggle = document.getElementById('toggleSidebar');

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });

    });
    </script>
</body>

</html>