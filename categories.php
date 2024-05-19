<?php

include 'config.php';
session_start();

// Upewnij się, że użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pobranie identyfikatora użytkownika i identyfikatora rodziny z sesji
$user_id = $_SESSION['user_id'];
$family_id = isset($_SESSION['id_rodziny']) ? $_SESSION['id_rodziny'] : null;

// Funkcja do pobierania kategorii wydatków
function getCategories($conn, $table, $family_id) {
    $stmt = $conn->prepare("SELECT nazwa FROM $table WHERE id_rodziny IS NULL OR id_rodziny = ?");
    $stmt->bind_param("i", $family_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['nazwa'];
    }
    $stmt->close();
    return $categories;
}

// Logika obsługi formularza dodawania nowej kategorii
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['newCategoryName'])) {
    $newCategoryName = $_POST['newCategoryName'];
    $categoryType = $_POST['categoryType'] === 'wydatki' ? 'kategorie_wydatkow' : 'kategorie_dochodow';
    $family_id = $_SESSION['id_rodziny'];
    // Zabezpiecz przed SQL Injection
    $newCategoryName = $conn->real_escape_string($newCategoryName);

    // Dodaj nową kategorię do bazy danych z id_rodziny
    $stmt = $conn->prepare("INSERT INTO $categoryType (nazwa, id_rodziny) VALUES (?, ?)");
    $stmt->bind_param("si", $newCategoryName, $family_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['message'] = 'Kategoria została dodana.';

    header('Location: categories.php');
    exit();
}

// Pobieranie kategorii wydatków i dochodów
$expenseCategories = getCategories($conn, 'kategorie_wydatkow', $family_id);
$incomeCategories = getCategories($conn, 'kategorie_dochodow', $family_id);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>kategorie</title>
    <!-- <link rel="stylesheet" href="css/style2.css"> -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/categories.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>

    <header>
        <h1>ZARZĄDZANIE BUDŻETEM DOMOWYM</h1>
        <div class="logo-container">
            <img src="logo.png" alt="BudgetMaster Logo">
        </div>
    </header>

    <div class="sidebar">
        <a href="#" id="toggleSidebar"><span class="material-icons">menu</span><span class="link-text">Ukryj
                menu</span></a>
        <a href="index.php"><span class="material-icons">home</span><span class="link-text">Strona główna</span></a>
        <a href="categories.php" class="categories"><span class="material-icons">category</span><span
                class="link-text">Kategorie</span></a>
        <a href="expense_forecast.php" class="payments"><span class="material-icons">payment</span><span
                class="link-text">Prognoza wydatków</span></a>
        <a href="currency.php" class="currency"><span class="material-icons">attach_money</span><span
                class="link-text">Waluta</span></a>
        <a href="bank_import.php" class="import"><span class="material-icons">import_export</span><span
                class="link-text">Import bankowy</span></a>
        <a href="history.php" class="history"><span class="material-icons">history</span><span
                class="link-text">Historia</span></a>
        <a href="report_generation.php" class="report"><span class="material-icons">assessment</span><span
                class="link-text">Generuj raport</span></a>
        <a href="settings.php" class="settings"><span class="material-icons">settings</span><span
                class="link-text">Ustawienia</span></a>
        <a href="login.php" class="logout"><span class="material-icons">logout</span><span class="link-text">Wyloguj
                się</span></a>
    </div>


    <div id="mainContent" class="content">
        <div class="categories-container">
            <h1>Kategorie</h1>
            <div class="buttons">
                <button id="wydatkiBtn">Wydatki</button>
                <button id="dochodyBtn">Dochody</button>
                <button id="addCategoryBtn">Dodaj nową kategorię</button>
            </div>
            <div id="kategorieWydatkow" style="display: block;">
                <ul>
                    <?php foreach ($expenseCategories as $category): ?>
                    <li>
                        <?php echo htmlspecialchars($category); ?>
                        <span class="delete-category"
                            data-category="<?php echo htmlspecialchars($category); ?>">&times;</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div id="kategorieDochodow" style="display: none;">
                <ul>
                    <?php foreach ($incomeCategories as $category): ?>
                    <li>
                        <?php echo htmlspecialchars($category); ?>
                        <span class="delete-category"
                            data-category="<?php echo htmlspecialchars($category); ?>">&times;</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="add-category-form">

            </div>
        </div>
    </div>

    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>

    <!-- Modal -->
    <div id="addCategoryModal" class="modal">
        <!-- Modal content -->
        <div class="modal-content">
            <span class="close">&times;</span>
            <form id="addCategoryForm" method="post" action="categories.php">
                <input type="text" name="newCategoryName" placeholder="Nazwa nowej kategorii" required>
                <select name="categoryType">
                    <option value="wydatki">Wydatki</option>
                    <option value="dochody">Dochody</option>
                </select>
                <button type="submit" name="submitCategory">Dodaj Kategorię</button>
            </form>
        </div>
    </div>
    <!-- Modal potwierdzenia usunięcia kategorii -->
    <div id="deleteConfirmationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Czy na pewno chcesz usunąć kategorię: <span id="categoryToDelete"></span>?</h2>
            <button id="confirmDelete">Usuń</button>
            <button id="cancelDelete">Anuluj</button>
        </div>
    </div>

    <script src="categories.js"></script>
</body>

</html>