<?php
session_start(); // Rozpoczęcie sesji, aby uzyskać dostęp do zmiennych sesji
include 'config.php'; // Dołączenie pliku z ustawieniami połączenia z bazą danych

// Sprawdzenie, czy formularz został wysłany i czy ID użytkownika jest dostępne w sesji
if (isset($_POST['amount'], $_SESSION['user_id'])) {
    $amount = $_POST['amount']; // Pobranie kwoty z formularza
    $userId = $_SESSION['user_id']; // Pobranie ID użytkownika z sesji

    // Sprawdzenie, czy zalogowany użytkownik jest założycielem rodziny
    $stmt = $conn->prepare("SELECT id_zalozyciela FROM rodziny WHERE id_zalozyciela = ?");
    $stmt->bind_param("i", $userId); // Przypisanie ID użytkownika do zapytania
    $stmt->execute(); // Wykonanie zapytania
    $result = $stmt->get_result(); // Pobranie wyników zapytania
    $stmt->close(); // Zamknięcie zapytania

    // Jeśli zalogowany użytkownik nie jest założycielem rodziny, wyświetlenie komunikatu o braku uprawnień
    if ($result->num_rows == 0) {
        echo "Nie masz uprawnień do zmiany budżetu.";
        $conn->close(); // Zamknięcie połączenia z bazą danych
        exit; // Zakończenie skryptu
    }

    // Kontynuowanie aktualizacji budżetu, jeśli użytkownik jest założycielem rodziny
    if (is_numeric($amount)) { // Sprawdzenie, czy podana kwota jest liczbą
        // Aktualizacja budżetu rodziny w bazie danych
        $stmt = $conn->prepare("UPDATE rodziny SET budzet = ? WHERE id_zalozyciela = ?");
        $stmt->bind_param("di", $amount, $userId); // Przypisanie kwoty i ID użytkownika do zapytania

        if ($stmt->execute()) { // Sprawdzenie, czy aktualizacja się powiodła
            echo "Budżet zaktualizowany"; // Wyświetlenie komunikatu o sukcesie
        } else {
            echo "Błąd: " . $stmt->error; // Wyświetlenie błędu, jeśli aktualizacja się nie powiodła
        }

        $stmt->close(); // Zamknięcie zapytania
    } else {
        echo "Podana wartość budżetu nie jest liczbą."; 
    }
} else {
    echo "Brak wymaganych danych.";
}

$conn->close(); // Zamknięcie połączenia z bazą danych
?>