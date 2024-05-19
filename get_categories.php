<?php
include 'config.php'; // Dołączenie pliku konfiguracji z informacjami o połączeniu z bazą danych.
session_start(); // Rozpoczęcie sesji PHP lub wznowienie istniejącej.

// Sprawdzenie, czy użytkownik jest zalogowany, poprzez sprawdzenie obecności 'user_id' w sesji.
if (!isset($_SESSION['user_id'])) {
    echo "Musisz być zalogowany, aby wyświetlić kategorie.";
    exit;
}
// Pobieranie ID rodziny z sesji użytkownika i domyślnej waluty (PLN), jeśli nie określono innej.
$id_rodziny = $_SESSION['id_rodziny'] ?? 0; // Pobieramy ID rodziny z sesji
$sqlRodzina = "SELECT waluta FROM rodziny WHERE id = ?";
$stmtRodzina = $conn->prepare($sqlRodzina);
$stmtRodzina->bind_param("i", $id_rodziny);
$stmtRodzina->execute();
$resultRodzina = $stmtRodzina->get_result()->fetch_assoc();
$waluta = $resultRodzina['waluta'] ?? 'PLN'; // Domyślna waluta to PLN, jeśli nie jest ustawiona
$stmtRodzina->close();

// Ustawienie domyślnego typu transakcji na 'wydatek' i pobranie wartości 'type' z POST, jeśli dostępna.
$type = $_POST['type'] ?? 'wydatek';
$tableName = $type === 'wydatek' ? 'kategorie_wydatkow' : 'kategorie_dochodow';

// Ustawienie domyślnego okresu czasu na 'month' i pobranie wartości 'timePeriod' z POST, jeśli dostępna.
$timePeriod = $_POST['timePeriod'] ?? 'month';
$currentDate = date('Y-m-d'); // Pobiera aktualną datę w formacie 'YYYY-MM-DD'

// Utworzenie filtru daty w zależności od wybranego okresu czasu.
if (isset($_POST['timePeriod'])) {
    switch ($timePeriod) {
        // Określenie zakresu daty dla różnych okresów: dzień, tydzień, miesiąc, rok.
        case 'day':
            $dateFilter = " AND DATE(t.data) = '{$currentDate}'";
            break;
        case 'week':
            $dateFilter = " AND DATE(t.data) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateFilter = " AND DATE(t.data) >= DATE_SUB('{$currentDate}', INTERVAL 1 MONTH)";
            break;
        case 'year':
            $dateFilter = " AND DATE(t.data) >= DATE_SUB('{$currentDate}', INTERVAL 1 YEAR)";
            break;
        default:
            $dateFilter = ""; // Jeśli timePeriod nie jest rozpoznawalny, nie stosujemy filtra
            break;
    }
}else {
    $dateFilter = ""; // Brak filtra daty, jeśli 'timePeriod' nie jest ustawione.
}

// Obliczenie całkowitej kwoty dla wybranego typu transakcji i okresu czasu.
$sqlTotal = "SELECT SUM(kwota) as total FROM transakcje t WHERE t.typ = ? {$dateFilter}";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("s", $type);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result()->fetch_assoc();
$total = $resultTotal['total'] ?? 0;
$stmtTotal->close();

// Pobieranie nazwy kategorii i sumy dla każdej kategorii z uwzględnieniem wybranego typu transakcji i filtra daty.
$sql = "SELECT k.nazwa, SUM(t.kwota) as suma_kategorii 
        FROM {$tableName} k
        INNER JOIN transakcje t ON k.id = t.id_kategorii
        WHERE t.typ = ? AND (t.id_rodziny = ? OR t.id_rodziny IS NULL)
        {$dateFilter}
        GROUP BY k.nazwa";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $type, $_SESSION['id_rodziny']);
$stmt->execute();
$result = $stmt->get_result();

$output = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $percent = ($total > 0) ? round(($row['suma_kategorii'] / $total) * 100, 2) : 0;
        $output .= "<p><span class=\"category-name\">" . htmlspecialchars($row['nazwa']) . ":</span> <span class=\"category-amount\">" . number_format((float)htmlspecialchars($row['suma_kategorii']), 2, ',', ' ') . " " . $waluta . "</span> - <span class=\"category-percentage\">" . $percent . "%</span></p>";
    }
} else {
    $output = "<p>Brak transakcji dla wybranych kategorii w tym okresie.</p>";
}
error_log("Type: " . $type . " | Time Period: " . $timePeriod);
error_log("SQL Query: " . $sql);

$stmt->close();
$conn->close();

echo $output; // Wyświetlenie wyników.
?>