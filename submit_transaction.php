<?php
// Ustawienie nagłówka odpowiedzi jako JSON
header('Content-Type: application/json');
// Dołączenie pliku konfiguracyjnego z danymi do połączenia z bazą danych
include 'config.php';

// Sprawdzenie, czy skrypt został wywołany metodą POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Pobranie danych z formularza
    $kwota = $_POST['kwota'];
    $typ = $_POST['typ'];
    $id_kategorii = $_POST['id_kategorii'];
    $data = $_POST['data'];
    $opis = $_POST['opis'];
    $id_rodziny = $_POST['id_rodziny'] ?? null;

    // Rozpoczęcie transakcji
    $conn->begin_transaction();
    // Przygotowanie zapytania SQL do wstawienia transakcji
    $stmt = $conn->prepare("INSERT INTO transakcje (kwota, typ, id_kategorii, data, opis, id_rodziny) VALUES (?, ?, ?, ?, ?, ?)");
    // Powiązanie parametrów z zapytaniem
    $stmt->bind_param("sssssi", $kwota, $typ, $id_kategorii, $data, $opis, $id_rodziny);

    // Wykonanie zapytania
    if ($stmt->execute()) {
        // Konwersja kwoty na wartość zmiennoprzecinkową
        $kwota = floatval($kwota);
        // Aktualizacja budżetu rodziny w zależności od typu transakcji
        if ($typ === 'wydatek') {
            $query = "UPDATE rodziny SET budzet = budzet - ? WHERE id = ?";
        } elseif ($typ === 'dochod') {
            $query = "UPDATE rodziny SET budzet = budzet + ? WHERE id = ?";
        } else {
            // W przypadku błędnego typu transakcji, zwrócenie błędu
            echo json_encode(["success" => false, "message" => "Nieprawidłowy typ transakcji."]);
            $conn->rollback(); // Wycofanie transakcji
            exit();
        }
        // Przygotowanie zapytania do aktualizacji budżetu
        if ($updateStmt = $conn->prepare($query)) {
            $updateStmt->bind_param("di", $kwota, $id_rodziny);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Zatwierdzenie transakcji
        $conn->commit();
        // Zwrócenie pozytywnej odpowiedzi
        echo json_encode(["success" => true, "message" => "Transakcja została dodana i budżet zaktualizowany."]);
    } else {
        // W przypadku niepowodzenia, zwrócenie błędu i wycofanie transakcji
        echo json_encode(["success" => false, "message" => "Nie udało się dodać transakcji."]);
        $conn->rollback();
    }
    // Zamknięcie zapytania i połączenia z bazą danych
    $stmt->close();
    $conn->close();
} else {
    // W przypadku żądania innego niż POST, zwrócenie błędu
    echo json_encode(["success" => false, "message" => "Nieprawidłowe żądanie."]);
}
?>