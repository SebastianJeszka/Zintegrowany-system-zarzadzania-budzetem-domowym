<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego bazy danych
session_start(); // Rozpoczęcie sesji

$import_status_message = ''; // Zmienna przechowująca wiadomość o stanie importu
$import_status_class = ''; // Zmienna przechowująca klasę CSS dla wiadomości o stanie importu

// Sprawdzenie, czy transakcje zostały poprawnie dodane do bazy danych
if (isset($_SESSION['transactions_saved']) && $_SESSION['transactions_saved']) {
    $import_status_message = "Transakcje zostały poprawnie dodane do bazy.";
    $import_status_class = 'success'; // Klasa CSS dla pozytywnego komunikatu
    // Usunięcie danych o transakcjach i o zapisie z sesji
    unset($_SESSION['transactions_to_import']);
    unset($_SESSION['transactions_saved']);
} elseif (isset($_POST['cancel_import'])) {
    // Anulowanie importu i usunięcie danych o transakcjach z sesji
    unset($_SESSION['transactions_to_import']);
    $import_status_message = 'Import anulowany.';
    $import_status_class = 'info'; // Klasa CSS dla informacyjnego komunikatu
} elseif (isset($_SESSION['message'])) {
    // Wyświetlenie komunikatu przechowywanego w sesji
    $import_status_message = $_SESSION['message'];
    $import_status_class = $_SESSION['message_type'] ?? 'info'; // Domyślnie 'info', jeśli typ nie jest ustawiony
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
// Upewnij się, że użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Sprawdź, czy kliknięto przycisk anulowania
if (isset($_POST['cancel_import'])) {
    // Usuń dane transakcji z sesji
    unset($_SESSION['transactions_to_import']);
    $import_status_message = 'Import anulowany.';
    $import_status_class = 'info';
}
$user_id = $_SESSION['user_id']; // Pobranie ID zalogowanego użytkownika
$family_id = isset($_SESSION['id_rodziny']) ? $_SESSION['id_rodziny'] : null; // Pobranie ID rodziny z sesji, jeśli istnieje
$import_status_message = '';
$import_status_class = '';

// Inicjalizacja zmiennej przechowującej transakcje do importu
$transactions_to_import = isset($_SESSION['transactions_to_import']) ? $_SESSION['transactions_to_import'] : array();

// Obsługa przesyłania pliku CSV
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["bank_csv"])) {
    $file_tmp = $_FILES["bank_csv"]["tmp_name"]; // Ścieżka tymczasowa do pliku
    $file_ext = strtolower(pathinfo($_FILES["bank_csv"]["name"], PATHINFO_EXTENSION)); // Rozszerzenie pliku
    $extensions = array("csv"); // Dozwolone rozszerzenia plików
    $mime_types = array("text/csv", "text/plain", "application/csv", "text/comma-separated-values"); // Dozwolone typy MIME

    if ($_FILES["bank_csv"]["error"] === 0) {
        if (in_array($file_ext, $extensions) && in_array($_FILES["bank_csv"]["type"], $mime_types)) {
            // Przesyłamy plik CSV do skryptu Pythona i odbieramy dane
            $python_script_path = escapeshellarg('D:\\xampp\\htdocs\\zarzadzanie_budzetem\\scripts\\import_csv.py');
            $file_tmp = escapeshellarg($file_tmp);
            $cmd = "python \"$python_script_path\" \"$file_tmp\" \"$user_id\" \"$family_id\" 2>&1";
            $json_data = shell_exec($cmd);
            // to co otrzymuje z pythona
            // echo "<pre>";
            // var_dump($json_data);
            // echo "</pre>";
            
            if ($json_data) {
                $transactions = json_decode($json_data, true);
                if (is_array($transactions)) {
                    // Zapisujemy dane w sesji
                    $_SESSION['transactions_to_import'] = $transactions;
                    $import_status_message = 'Dane zostały wczytane. Proszę o weryfikację.';
                    $import_status_class = 'success';
                } else {
                    // Logujemy błąd
                    error_log("Błąd przy wykonywaniu skryptu Pythona. Polecenie: $cmd");
                    $import_status_message = 'Nie udało się przetworzyć pliku CSV.';
                    $import_status_class = 'error';
                }
            } else {
                $import_status_message = 'Błąd wykonania skryptu Python.';
                $import_status_class = 'error';
            }
        } else {
            $import_status_message = 'Nieprawidłowy format pliku. Proszę przesłać plik CSV.';
            $import_status_class = 'error';
        }
    } else {
        $import_status_message = 'Wystąpił błąd podczas przesyłania pliku.';
        $import_status_class = 'error';
    }
}
// Sprawdź, czy w sesji są już jakieś transakcje do zaimportowania
$transactions_to_import = isset($_SESSION['transactions_to_import']) ? $_SESSION['transactions_to_import'] : array();
checkTransactionsExistence($transactions_to_import, $conn, $family_id);
function checkTransactionsExistence(&$transactions_to_import, $conn, $family_id) {
// Dodanie sprawdzania, czy transakcje istnieją już w bazie danych
foreach ($transactions_to_import as $index => $transaction) {
    // Struktura tabeli transakcje zawiera kolumny: id, id_rodziny, id_kategorii, typ, kwota, data, opis
    $sql = "SELECT COUNT(*) as count FROM transakcje WHERE data = ? AND opis = ? AND kwota = ? AND id_rodziny = ?";
    $stmt = $conn->prepare($sql);

    $id_rodziny = $_SESSION['id_rodziny'] ?? null; // Wartość null, jeśli nie jest ustawione
    $stmt->bind_param("ssdi", $transaction['date'], $transaction['description'], $transaction['amount'], $id_rodziny);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $transactions_to_import[$index]['is_existing'] = ($row['count'] > 0);
    $stmt->close();
}
}
// Pobieranie mapowania ID na nazwy kategorii
$categories_map = array();



// Pobieranie mapowania ID na nazwy kategorii
$categories_map = array('wydatek' => [], 'dochod' => []);

// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pobranie nazw kategorii wydatków i dochodów
$sql = "SELECT id, nazwa FROM kategorie_wydatkow";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories_map['wydatek'][$row['id']] = $row['nazwa'];
    }
}

$sql = "SELECT id, nazwa FROM kategorie_dochodow";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories_map['dochod'][$row['id']] = $row['nazwa'];
    }
}

// Pobranie kategorii wydatków
$expense_categories = [];
$sql = "SELECT id, nazwa FROM kategorie_wydatkow";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $expense_categories[$row['id']] = $row['nazwa'];
    }
}

// Pobranie kategorii dochodów
$income_categories = [];
$sql = "SELECT id, nazwa FROM kategorie_dochodow";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $income_categories[$row['id']] = $row['nazwa'];
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Import bankowy</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/bank_import.css">
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
        <div class="bank-container">
            <h1>Import bankowy</h1>
            <form action="bank_import.php" method="post" enctype="multipart/form-data">
                <label for="bank_select">Wybierz bank:</label>
                <div class="bank-selection">
                    <input type="radio" id="pekao_sa" name="bank_name" value="pekao_sa" checked>
                    <label for="pekao_sa" class="bank-button">Pekao SA</label>

                    <input type="radio" id="mbank" name="bank_name" value="mbank">
                    <label for="mbank" class="bank-button">mBank</label>
                </div>

                <label for="bank_csv">Wybierz plik CSV:</label>
                <input type="file" id="bank_csv" name="bank_csv" accept=".csv">

                <button type="submit">Importuj dane bankowe</button>
                <button type="button" class="slownik" id="zarzadzajSlownikiem">Zarządzaj słownikiem</button>
            </form>

            <?php if (!empty($transactions_to_import)): ?>
            <div class="import-results">
                <h1 style="margin-top:25px;">Wyniki importu</h1>
                <form action="save_imported_transactions.php" method="post">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Opis</th>
                                <th>Kwota</th>
                                <th>Typ</th>
                                <th>Kategoria</th>
                                <th>Akceptuj</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions_to_import as $index => $transaction): ?>
                            <tr class="<?php echo $transaction['is_existing'] ? 'existing-transaction' : ''; ?>">
                                <td>
                                    <!-- Pole do edycji daty -->
                                    <input type="date" name="transactions[<?php echo $index; ?>][date]"
                                        value="<?php echo htmlspecialchars($transaction['date']); ?>" />
                                </td>
                                <td>
                                    <!-- Pole do edycji opisu -->
                                    <input type="text" name="transactions[<?php echo $index; ?>][description]"
                                        value="<?php echo htmlspecialchars($transaction['description']); ?>" />
                                </td>
                                <td>
                                    <!-- Pole do edycji kwoty -->
                                    <input type="number" step="0.01" name="transactions[<?php echo $index; ?>][amount]"
                                        value="<?php echo htmlspecialchars($transaction['amount']); ?>" />
                                </td>
                                <td>
                                    <!-- Select do wyboru typu -->
                                    <select name="transactions[<?php echo $index; ?>][transaction_type]"
                                        class="transaction-type" data-index="<?php echo $index; ?>">
                                        <option value="wydatek"
                                            <?php echo $transaction['transaction_type'] == 'wydatek' ? 'selected' : ''; ?>>
                                            Wydatek</option>
                                        <option value="dochod"
                                            <?php echo $transaction['transaction_type'] == 'dochod' ? 'selected' : ''; ?>>
                                            Dochód</option>
                                    </select>
                                </td>
                                <td>
                                    <!-- Select do wyboru kategorii -->
                                    <select name="transactions[<?php echo $index; ?>][category_id]"
                                        class="category-select" data-index="<?php echo $index; ?>">
                                        <?php
            // Wybieramy odpowiednią listę kategorii na podstawie typu transakcji
            $categories = $transaction['transaction_type'] == 'wydatek' ? $expense_categories : $income_categories;
            foreach ($categories as $category_id => $category_name) {
                // Sprawdzamy, czy category_id z transakcji pasuje do category_id z kategorii
                $selected = intval($transaction['category_id']) === intval($category_id) ? 'selected' : '';
                echo "<option value=\"$category_id\" $selected>$category_name</option>";
            }
            ?>
                                    </select>
                                </td>
                                <td>
                                    <!-- Checkbox akceptacji -->
                                    <input type="checkbox" name="transactions[<?php echo $index; ?>][accept]"
                                        <?php echo !$transaction['is_existing'] ? 'checked' : ''; ?> />
                                </td>
                            </tr>
                            <?php endforeach; ?>


                        </tbody>
                    </table>
                    <div class="form-actions">
                        <button type="submit" name="submit_transactions">Zapisz wybrane transakcje</button>
                        <button type="submit" name="cancel_import" class="cancel-button"
                            formaction="bank_import.php">Anuluj</button>
                    </div>
                </form>
            </div>
            <?php elseif (isset($import_status_message)): ?>
            <div class="status-message <?php echo htmlspecialchars($import_status_class); ?>">
                <?php echo htmlspecialchars($import_status_message); ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>


    <div id="modalSlownik" class="modal">
        <!-- Modal content -->
        <div class="modal-content">
            <span class="close">&times;</span>
            <h1 style="text-align:center;color: #333;"> Zarządzanie słownikiem </h1>
            <form id="formSlownik" action="save_dictionary.php" method="post">
                <label for="slowoKluczowe">Słowo kluczowe:</label>
                <input type="text" id="slowoKluczowe" name="slowoKluczowe" required>

                <label for="typTransakcji">Typ transakcji:</label>
                <select id="typTransakcji" name="typTransakcji" required>
                    <option value="wydatek">Wydatek</option>
                    <option value="dochod">Dochód</option>
                </select>

                <label for="kategoria">Kategoria:</label>
                <select id="kategoria" name="kategoria" required>
                    <!-- Opcje kategorii będą wypełniane przez JavaScript -->
                </select>

                <input type="submit" value="Zapisz">
            </form>

            <table id="slownikTable">
                <thead>
                    <tr>
                        <th>Słowo Kluczowe</th>
                        <th>Typ Transakcji</th>
                        <th>Kategoria</th>

                    </tr>
                </thead>
                <tbody>
                    <!-- Wiersze tabeli będą dodawane tutaj za pomocą JavaScript -->
                </tbody>
            </table>


        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Selektory elementów DOM
        var sidebar = document.querySelector('.sidebar');
        var toggle = document.getElementById('toggleSidebar');
        var modal = document.getElementById("modalSlownik");
        var btn = document.getElementById("zarzadzajSlownikiem");
        var span = document.getElementsByClassName("close")[0];
        var form = document.getElementById('formSlownik');
        var tableBody = document.getElementById('slownikTable').getElementsByTagName('tbody')[0];

        // Funkcja wypełniająca select kategorii w zależności od typu transakcji
        function fillCategorySelect(selectElement, defaultValue, transactionType) {
            selectElement.innerHTML = ''; // Czyszczenie obecnych opcji

            // Wybór odpowiednich kategorii na podstawie typu transakcji
            var categories = transactionType === 'wydatek' ? <?php echo json_encode($expense_categories); ?> :
                <?php echo json_encode($income_categories); ?>;

            // Tworzy nowe opcje dla select
            Object.keys(categories).forEach(function(id) {
                var option = document.createElement('option');
                option.value = id;
                option.textContent = categories[id];
                selectElement.appendChild(option);
            });

            // Ustaw domyślną wartość, jeśli jest podana
            if (defaultValue) {
                selectElement.value = defaultValue;
            }
        }

        // Przełączanie widoczności sidebaru
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });

        // Otwieranie modala zarządzania słownikiem
        btn.onclick = function() {
            modal.style.display = "block";
            loadDictionary(); // Załaduj dane do tabeli w modalu
        }

        // Zamykanie modala
        span.onclick = function() {
            modal.style.display = "none";
        }

        // Zapobieganie standardowemu wysyłaniu formularza i obsługa AJAX
        form.addEventListener("submit", function(event) {
            event.preventDefault(); // Zapobiega standardowemu wysyłaniu formularza

            var formData = new FormData(this);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "save_dictionary.php", true);

            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        addRowToDictionaryTable(response.data);
                        alert("Słowo kluczowe zostało pomyślnie dodane do bazy.");
                    } else {
                        alert("Wystąpił błąd: " + response.message);
                    }
                } else {
                    alert("Wystąpił błąd podczas dodawania słowa kluczowego.");
                }
            };

            xhr.send(formData);
        });

        // Ładowanie danych słownika
        function loadDictionary() {
            fetch('get_dictionary.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        tableBody.innerHTML = ''; // Wyczyść obecną zawartość tabeli
                        data.data.forEach(entry => {
                            addRowToDictionaryTable(entry); // Dodaj każdy wpis do tabeli
                        });
                    } else {
                        alert(data.message); // Pokaż wiadomość o błędzie
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }


        // Upewnij się, że ta funkcja jest wywoływana przy otwarciu modala.
        btn.onclick = function() {
            modal.style.display = "block";
            loadDictionary(); // Załaduj słownik przy otwarciu modala
        }

        // Dodawanie wpisu do tabeli słownika
        function addRowToDictionaryTable(entry) {
            var newRow = tableBody.insertRow();
            newRow.innerHTML = `
        <td>${entry.slowo_kluczowe}</td>
        <td>${entry.typ_transakcji}</td>
        <td>${entry.nazwa_kategorii}</td>
        
    `;
        }

        // Obsługa zmiany typu transakcji i dynamiczne wypełnianie select kategorii
        document.getElementById('typTransakcji').addEventListener('change', function() {
            var type = this.value;
            var categorySelect = document.getElementById('kategoria');
            categorySelect.innerHTML = '';

            var categories = type === 'wydatek' ? <?php echo json_encode($expense_categories); ?> :
                <?php echo json_encode($income_categories); ?>;
            Object.keys(categories).forEach(function(id) {
                var option = document.createElement('option');
                option.value = id;
                option.textContent = categories[id];
                categorySelect.appendChild(option);
            });
        });

        // Obsługa zmiany typu transakcji dla każdego wyboru kategorii
        document.querySelectorAll('.transaction-type').forEach(function(typeSelect) {
            typeSelect.addEventListener('change', function() {
                var index = this.getAttribute('data-index');
                var categorySelect = document.querySelector('.category-select[data-index="' +
                    index + '"]');
                var selectedType = this.value;
                fillCategorySelect(categorySelect, null, selectedType);
            });
        });

        // Automatyczne wypełnienie kategorii dla domyślnego typu transakcji
        var defaultType = document.getElementById('typTransakcji').value;
        var categorySelect = document.getElementById('kategoria');
        fillCategorySelect(categorySelect, null, defaultType);

        // Otwieranie modala
        var modal = document.getElementById("modalSlownik");
        var btn = document.getElementById("zarzadzajSlownikiem");
        btn.onclick = function() {
            modal.style.display = "block";
            loadDictionary(); // Załaduj słownik przy otwarciu modala
        }

        // Zamykanie modala
        var span = document.getElementsByClassName("close")[0];
        span.onclick = function() {
            modal.style.display = "none";
        }

        // Obsługa wysyłania formularza
        document.getElementById("formSlownik").addEventListener("submit", function(event) {
            event.preventDefault(); // Zapobiega standardowemu wysyłaniu formularza

            var formData = new FormData(this);
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "save_dictionary.php", true);

        });

    });
    </script>


</body>

</html>