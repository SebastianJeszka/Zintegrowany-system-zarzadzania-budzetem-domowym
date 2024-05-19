<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego z danymi do połączenia z bazą danych
session_start();

// Sprawdzenie, czy formularz został przesłany za pomocą metody POST i czy przycisk został naciśnięty
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_transactions'])) {
    $transactions = $_POST['transactions']; // Pobranie danych transakcji z formularza
    // Utworzenie nowego połączenia z bazą danych
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Sprawdzenie, czy połączenie z bazą danych się powiodło
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Rozpoczęcie transakcji z bazą danych
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
    try {
        foreach ($transactions as $transaction) {
            // Sprawdzenie, czy transakcja została zaznaczona do zapisu
            if (isset($transaction['accept'])) {
                // Przypisanie danych transakcji do zmiennych
                $family_id = $_SESSION['id_rodziny']; // ID rodziny z sesji
                $category_id = $transaction['category_id'];
                $amount = $transaction['amount'];
                $date = $transaction['date'];
                $description = $transaction['description'];
                $type = $transaction['transaction_type'] === 'wydatek' ? 'wydatek' : 'dochod';

                // Przygotowanie zapytania SQL do wstawienia transakcji
                $stmt = $conn->prepare("INSERT INTO transakcje (id_rodziny, id_kategorii, typ, kwota, data, opis) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisdss", $family_id, $category_id, $type, $amount, $date, $description);

                // Wykonanie zapytania
                if (!$stmt->execute()) {
                    // W przypadku błędu, wyrzucenie wyjątku
                    throw new Exception("Błąd zapisu transakcji: " . $stmt->error);
                }
                $stmt->close(); // Zamknięcie zapytania
            }
        }
        $conn->commit(); // Zatwierdzenie transakcji
        // Ustawienie informacji w sesji, aby wyświetlić komunikat na stronie przekierowania
        $_SESSION['transactions_saved'] = true;
        $_SESSION['message'] = "Transakcje zostały poprawnie dodane do bazy.";
        $_SESSION['message_type'] = 'success';
    } catch (Exception $e) {
        $conn->rollback(); // Wycofanie transakcji w przypadku błędu
        // Ustawienie komunikatu o błędzie w sesji
        $_SESSION['message'] = "Wystąpił błąd: " . $e->getMessage();
    }

    $conn->close(); // Zamknięcie połączenia z bazą danych
    // Przekierowanie do strony bank_import.php
    header("Location: bank_import.php");
    exit();
}
?>