<?php
include 'config.php'; // Dołącza plik konfiguracyjny z ustawieniami bazy danych.
session_start(); // Rozpoczyna sesję, aby móc korzystać z danych sesyjnych.

header('Content-Type: application/json'); // Ustawia typ zawartości odpowiedzi na JSON.

$response = [
    'success' => false,
    'message' => ''
]; // Inicjalizuje odpowiedź, która będzie zwrócona do klienta.

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Access Denied'; // Ustawia komunikat o braku dostępu, jeśli użytkownik nie jest zalogowany.
    echo json_encode($response); // Zwraca odpowiedź jako JSON.
    exit(); // Zakończenie skryptu.
}

// Sprawdza, czy żądanie jest typu POST i czy wymagane dane zostały przesłane.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['categoryName'], $_POST['categoryType'])) {
    $categoryName = $_POST['categoryName']; // Pobiera nazwę kategorii z danych formularza.
    $categoryType = $_POST['categoryType']; // Pobiera typ kategorii (wydatki/dochody) z danych formularza.
    $family_id = $_SESSION['id_rodziny']; // Pobiera ID rodziny z danych sesji.

    // Wybiera odpowiednią tabelę na podstawie typu kategorii.
    $tableName = $categoryType === 'wydatki' ? 'kategorie_wydatkow' : 'kategorie_dochodow';

    // Przygotowuje zapytanie SQL do usunięcia kategorii.
    $stmt = $conn->prepare("DELETE FROM $tableName WHERE nazwa = ? AND id_rodziny = ?");
    if ($stmt === false) {
        $response['message'] = 'Błąd podczas przygotowywania zapytania: ' . $conn->error;
    } else {
        $stmt->bind_param("si", $categoryName, $family_id); // Wiąże parametry z zapytaniem SQL.
        if ($stmt->execute()) { // Wykonuje zapytanie.
            if ($stmt->affected_rows > 0) { // Sprawdza, czy zapytanie wpłynęło na jakiekolwiek rekordy.
                $response['success'] = true; // Ustawia sukces operacji.
                $response['message'] = 'Kategoria została usunięta.'; // Ustawia komunikat o sukcesie.
            } else {
                $response['message'] = 'Nie znaleziono kategorii lub nie można jej usunąć.'; // Komunikat, gdy nie ma zmian.
            }
        } else {
            $response['message'] = 'Błąd podczas wykonania zapytania: ' . $stmt->error;
        }
        $stmt->close(); // Zamyka zapytanie.
    }
    $conn->close(); // Zamyka połączenie z bazą danych.
    echo json_encode($response); // Zwraca odpowiedź jako JSON.
}
?>