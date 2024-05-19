<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego zawierającego informacje o połączeniu z bazą danych.
session_start(); // Rozpoczęcie nowej sesji lub wznowienie istniejącej.

// Sprawdzenie, czy użytkownik jest zalogowany, poprzez sprawdzenie istnienia 'user_id' w sesji.
if (!isset($_SESSION['user_id'])) {
    // Jeśli użytkownik nie jest zalogowany, zwróć błąd jako odpowiedź JSON i zakończ wykonanie skryptu.
    echo json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany.']);
    exit();
}

// Zapytanie SQL do pobrania danych ze słownika oraz powiązanych nazw kategorii dochodów i wydatków.
$query = "
SELECT s.id, s.slowo_kluczowe, s.typ_transakcji, 
       wd.nazwa AS nazwa_wydatek, 
       dc.nazwa AS nazwa_dochod 
FROM slownik s
LEFT JOIN kategorie_wydatkow wd ON s.id_kategorii_wydatkow = wd.id
LEFT JOIN kategorie_dochodow dc ON s.id_kategorii_dochodow = dc.id";

$result = $conn->query($query); // Wykonanie zapytania.

$dictionary_entries = []; // Inicjalizacja tablicy na wyniki.
if ($result) {
    // Iteracja przez wyniki zapytania.
    while ($row = $result->fetch_assoc()) {
        // Dodanie do każdego wiersza nazwy kategorii w zależności od typu transakcji (wydatek lub dochód).
        $row['nazwa_kategorii'] = $row['typ_transakcji'] === 'wydatek' ? $row['nazwa_wydatek'] : $row['nazwa_dochod'];
        unset($row['nazwa_wydatek'], $row['nazwa_dochod']); // Usunięcie już niepotrzebnych kolumn dla czystości danych
        $dictionary_entries[] = $row; // Dodanie przetworzonego wiersza do tablicy wyników.
    }
    // Zwrócenie danych jako odpowiedź JSON z sukcesem.
    echo json_encode(['success' => true, 'data' => $dictionary_entries]);
} else {
    // Zwrócenie komunikatu o błędzie, jeśli zapytanie się nie powiodło.
    echo json_encode(['success' => false, 'message' => 'Nie udało się pobrać danych ze słownika.']);
}

$conn->close(); // Zamknięcie połączenia z bazą danych.
?>