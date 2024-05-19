<?php
include 'config.php';

// Sprawdzenie, czy metoda żądania to POST i czy istnieje pole 'nickname' w danych przesłanych przez formularz
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nickname'])) {
    // Oczyszczenie danych przesłanych przez użytkownika, aby zapobiec atakom SQL Injection
    $pseudonim = $conn->real_escape_string($_POST['nickname']);

    // Przygotowanie zapytania SQL do sprawdzenia, czy istnieje użytkownik o podanym pseudonimie
    $stmt = $conn->prepare("SELECT id FROM uzytkownicy WHERE pseudonim = ?");
    // Przypisanie zmiennej $pseudonim do parametru zapytania
    $stmt->bind_param("s", $pseudonim);
    $stmt->execute(); // Wykonanie zapytania
    // Zapisanie wyniku zapytania, aby móc sprawdzić, ile wierszy zostało zwróconych
    $stmt->store_result();

    // Sprawdzenie, czy zapytanie zwróciło jakiekolwiek wiersze (czy pseudonim jest zajęty)
    if ($stmt->num_rows > 0) {
        echo 'taken';
    } else {
        echo 'available';
    }

    $stmt->close();
}
$conn->close();