<?php
include 'config.php'; // Dołącza plik konfiguracyjny z ustawieniami połączenia z bazą danych.
session_start(); // Rozpoczyna sesję, aby można było korzystać z zapisanych wcześniej danych sesji.

// Upewnij się, że użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Przechowuje ID zalogowanego użytkownika.
$family_id = null; // Inicjalizuje zmienną, która będzie przechowywała ID rodziny użytkownika.

// Pobiera ID rodziny z sesji lub z bazy danych, jeśli nie jest dostępne w sesji.
if (isset($_SESSION['id_rodziny'])) {
    $family_id = $_SESSION['id_rodziny'];
} else {
    $stmt = $conn->prepare("SELECT id_rodziny FROM uzytkownicy WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $family_id = $row['id_rodziny'];
        $_SESSION['id_rodziny'] = $family_id; // Zapisuje ID rodziny w sesji.
    }
    $stmt->close();
}

if ($family_id === null) {
    die("Nie znaleziono id rodziny dla użytkownika."); // Zakończenie skryptu, jeśli nie można znaleźć ID rodziny.
}

// Funkcja zwracająca dane transakcji w formacie JSON dla danej rodziny.
function get_transactions_as_json($conn, $family_id) {
    $stmt = $conn->prepare("SELECT * FROM transakcje WHERE typ = 'wydatek' AND id_rodziny = ?");
    $stmt->bind_param("i", $family_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = array();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row; // Zapisuje każdą transakcję do tablicy.
    }
    $stmt->close();

    return json_encode($transactions); // Zwraca dane transakcji w formacie JSON.
}

// Obsługuje przesłanie formularza.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

$transactions_json = get_transactions_as_json($conn, $family_id); // Pobiera dane transakcji jako JSON.
    
if ($transactions_json) {
    $tmpfname = tempnam(sys_get_temp_dir(), 'data_') . '.json'; // Tworzy tymczasowy plik do przechowywania danych JSON.
    file_put_contents($tmpfname, $transactions_json); // Zapisuje dane JSON do tymczasowego pliku.

    $script_path = 'D:\\xampp\\htdocs\\zarzadzanie_budzetem\\scripts\\forecast_expenses.py';
    $selected_year = $_POST['year']; // Rok wybrany przez użytkownika.
    $selected_month = $_POST['month']; // Miesiąc wybrany przez użytkownika.
    $command = escapeshellcmd("python \"$script_path\" \"$tmpfname\" \"$selected_year\" \"$selected_month\""); // Tworzy komendę do wykonania.
    $output = shell_exec($command); // Wykonuje komendę i przechowuje wyjście.
 
    echo $output; // Wyświetla wynik skryptu Pythona.

    unlink($tmpfname); // Usuwa tymczasowy plik.
    
} else {
    echo "Brak danych do przewidywania wydatków dla wybranego okresu."; // Komunikat, gdy brak danych.
}
} else {
}

$conn->close(); // Zamyka połączenie z bazą danych.
?>