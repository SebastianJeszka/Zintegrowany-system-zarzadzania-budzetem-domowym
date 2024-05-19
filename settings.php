<?php
// Dołączenie pliku konfiguracji bazy danych
include 'config.php';
// Rozpoczęcie sesji
session_start();

// Sprawdzenie, czy użytkownik jest zalogowany; jeśli nie, przekierowanie do strony logowania
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pobranie identyfikatora użytkownika z sesji
$user_id = $_SESSION['user_id'];
$family_code = null; // Zmienna na kod rodziny
$join_requests = []; // Tablica na prośby o dołączenie do rodziny

// Pobranie informacji o rodzinie użytkownika z bazy danych
$stmt = $conn->prepare("SELECT rodziny.kod_dolaczenia, rodziny.id, rodziny.id_zalozyciela FROM rodziny JOIN uzytkownicy ON uzytkownicy.id_rodziny = rodziny.id WHERE uzytkownicy.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $family_code = $row['kod_dolaczenia'];
    $family_id = $row['id'];
    // Sprawdzenie, czy zalogowany użytkownik jest założycielem rodziny
    $is_founder = ($user_id == $row['id_zalozyciela']); 

    if ($is_founder) {
        // Jeśli użytkownik jest założycielem, pobierz prośby o dołączenie do rodziny
        $requestsStmt = $conn->prepare("SELECT uzytkownicy.id, uzytkownicy.imie, uzytkownicy.nazwisko FROM prosby_dołączenia JOIN uzytkownicy ON prosby_dołączenia.id_uzytkownika = uzytkownicy.id WHERE prosby_dołączenia.id_rodziny = ? AND prosby_dołączenia.status_prosby = 'oczekujący'");
        $requestsStmt->bind_param("i", $family_id);
        $requestsStmt->execute();
        $requestsResult = $requestsStmt->get_result();

        while ($requestRow = $requestsResult->fetch_assoc()) {
            $join_requests[] = $requestRow; // Dodawanie prośby do tablicy
        }
    }
}
// Zamknięcie połączenia z bazą danych
$conn->close();
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Ustawienia</title>
    <link rel="stylesheet" href="css/settings.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <div class="settings-container">
            <h1>Ustawienia</h1>
            <div class="family-section">
                <!-- Sekcja rodziny -->
                <?php if ($family_code): ?>
                <p>Kod rodziny: <span class="family-code"><?php echo htmlspecialchars($family_code); ?></span></p>
                <?php endif; ?>

                <!-- Sekcja prośb o dołączenie do rodziny -->
                <?php if (!empty($join_requests) && $is_founder): ?>
                <div class="join-requests">
                    <h2>Prośby o dołączenie do rodziny</h2>
                    <ul>
                        <!-- Wyświetlenie listy prośb o dołączenie -->
                        <?php foreach ($join_requests as $request): ?>
                        <li id="request-<?php echo $request['id']; ?>">
                            <?php echo htmlspecialchars($request['imie']) . " " . htmlspecialchars($request['nazwisko']); ?>
                            <div class="button-container">
                                <!-- Przyciski umieszczone w kontenerze -->
                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'accept')"
                                    class="accept-request">Akceptuj</button>
                                <button onclick="handleRequest(<?php echo $request['id']; ?>, 'reject')"
                                    class="reject-request">Odrzuć</button>

                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>


        <footer>
            <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
                Informatyki - 2023</p>
        </footer>
        
        <script>
            // Skrypt JavaScript do obsługi interakcji z interfejsem użytkownika
            var sidebar = document.querySelector('.sidebar');
            var toggle = document.getElementById('toggleSidebar');

            toggle.addEventListener('click', function() {
                sidebar.classList.toggle('expanded');
            });

            function handleRequest(userId, action) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'handle_request.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (this.status == 200) {
                        var response = JSON.parse(this.responseText);
                        if (response.success) {
                            // Usuń element li z prośbą
                            var requestElement = document.getElementById('request-' + userId);
                            requestElement.parentNode.removeChild(requestElement);
                        } else {
                            // Obsługa błędu
                            console.error('Błąd:', response.message);
                        }
                    }
                };
                xhr.send('userId=' + userId + '&action=' + action);
            }
        </script>

</body>

</html>