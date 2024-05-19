<?php
session_start(); // Rozpoczęcie sesji, aby uzyskać dostęp do zmiennych sesji.

// Sprawdzenie, czy użytkownik jest zalogowany, sprawdzając obecność 'user_id' w sesji.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'config.php'; // Załaduj konfigurację bazy danych, która zawiera dane potrzebne do połączenia z bazą danych.

$userId = $_SESSION['user_id']; // Pobierz ID zalogowanego użytkownika z sesji.
$errors = []; // Tablica do przechowywania błędów, które mogą wystąpić podczas wykonywania skryptu.
$familyDetails = []; // Tablica do przechowywania szczegółów rodzin, których użytkownik jest założycielem.

// Utworzenie nowego połączenia z bazą danych
$conn = new mysqli($dbServername, $dbUsername, $dbPassword, $dbName);

// Sprawdź połączenie
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Przygotowanie i wykonanie zapytania SQL do pobrania informacji o rodzinach, których użytkownik jest założycielem.
$stmt = $conn->prepare("SELECT * FROM rodziny WHERE id_zalozyciela = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// Sprawdzenie, czy zapytanie zwróciło jakiekolwiek wyniki.
if ($result->num_rows > 0) {
    // Pobierz szczegóły każdej rodziny, której użytkownik jest założycielem
    while ($row = $result->fetch_assoc()) {
        $familyDetails[] = $row;
    }
} else {
    // Jeśli użytkownik nie jest założycielem żadnej rodziny, dodaj odpowiedni błąd do tablicy $errors.
    $errors[] = "Nie jesteś założycielem żadnej rodziny.";
}

$conn->close(); // Zamknięcie połączenia z bazą danych.
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Panel Administracyjny Rodziny</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="admin-container">
        <h1>Panel Administracyjny Rodziny</h1>
        <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
            <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($familyDetails as $family): ?>
        <div class="family-details">
            <h2>Rodzina: <?php echo htmlspecialchars($family['nazwa']); ?></h2>
            <p>Kod Dołączenia: <?php echo htmlspecialchars($family['kod_dolaczenia']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <script src="main.js"></script>
</body>

</html>