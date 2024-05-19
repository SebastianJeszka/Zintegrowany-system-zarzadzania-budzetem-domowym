<?php
include 'config.php'; // Dołączenie konfiguracji bazy danych
session_start(); // Rozpoczęcie sesji

$response = []; // Tablica na odpowiedź

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Pobranie danych z POST
    $slowoKluczowe = $_POST['slowoKluczowe'];
    $idKategorii = (int)$_POST['kategoria'];
    $typTransakcji = $_POST['typTransakcji']; // Oczekiwanie na 'dochod' lub 'wydatek'

    // Zerowanie wartości dla kolumn kategorii
    $idKategoriiDochod = NULL;
    $idKategoriiWydatek = NULL;

    // Sprawdź, czy typ transakcji to 'dochod' lub 'wydatek' i odpowiednio przypisz ID kategorii
    if ($typTransakcji == 'wydatek') {
        $idKategoriiWydatek = $idKategorii;
    } elseif ($typTransakcji == 'dochod') {
        $idKategoriiDochod = $idKategorii;
    } else {
        // Obsługa nieprawidłowego typu transakcji
        $response['success'] = false;
        $response['message'] = "Nieprawidłowy typ transakcji.";
        echo json_encode($response);
        exit;
    }

    // Przygotowanie zapytania do bazy danych
    $querySlownik = "INSERT INTO slownik (slowo_kluczowe, typ_transakcji, id_kategorii_dochodow, id_kategorii_wydatkow) VALUES (?, ?, ?, ?)";
    if ($stmtSlownik = $conn->prepare($querySlownik)) {
        // Przypisanie zmiennych do zapytania
        $stmtSlownik->bind_param("ssii", $slowoKluczowe, $typTransakcji, $idKategoriiDochod, $idKategoriiWydatek);
        if ($stmtSlownik->execute()) {
            // Udane dodanie słowa kluczowego
            $response['success'] = true;
            $response['data'] = [
                'id' => $conn->insert_id, // Zwrócenie ID nowo dodanego wpisu
                'slowo_kluczowe' => $slowoKluczowe,
                'typ_transakcji' => $typTransakcji,
                'id_kategorii_dochodow' => $idKategoriiDochod,
                'id_kategorii_wydatkow' => $idKategoriiWydatek
            ];
        } else {
            // Błąd podczas wykonania zapytania
            $response['success'] = false;
            $response['message'] = "Wystąpił błąd podczas dodawania słowa kluczowego: " . $stmtSlownik->error;
        }
        $stmtSlownik->close();
    } else {
        // Błąd podczas przygotowania zapytania
        $response['success'] = false;
        $response['message'] = "Wystąpił błąd: " . $conn->error;
    }

    $conn->close(); // Zamknięcie połączenia z bazą danych
    echo json_encode($response); // Zwrócenie odpowiedzi jako JSON
}
?>