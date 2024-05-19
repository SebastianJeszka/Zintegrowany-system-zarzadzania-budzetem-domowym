<?php
// Dołączenie pliku konfiguracyjnego z ustawieniami bazy danych
include 'config.php';

// Tablica do przechowywania komunikatów o błędach
$errors = [];

// Sprawdzenie, czy formularz został wysłany metodą POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Oczyszczanie i przypisywanie danych z formularza do zmiennych
    $imie = $conn->real_escape_string($_POST['firstName']);
    $nazwisko = $conn->real_escape_string($_POST['lastName']);
    $pseudonim = $conn->real_escape_string($_POST['nickname']);
    $haslo = $conn->real_escape_string($_POST['password']);
    $familyOption = $_POST['familyOption'];
    $familyName = isset($_POST['familyName']) ? $conn->real_escape_string($_POST['familyName']) : null;
    $familyCode = isset($_POST['familyCode']) ? $conn->real_escape_string($_POST['familyCode']) : null;

    // Sprawdzenie, czy pseudonim jest już zajęty
    $stmt = $conn->prepare("SELECT id FROM uzytkownicy WHERE pseudonim = ?");
    $stmt->bind_param("s", $pseudonim);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Nazwa użytkownika jest zajęta, wybierz inną.";
    }
    $stmt->close();

    // Kontynuacja tylko jeśli nie ma błędów
    if (count($errors) === 0) {
        // Rozpoczęcie transakcji
        $conn->begin_transaction();
        try {
            // Wstawienie nowego użytkownika do bazy danych
            $hashed_password = password_hash($haslo, PASSWORD_DEFAULT);
            $insertUserSql = "INSERT INTO uzytkownicy (imie, nazwisko, pseudonim, haslo) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertUserSql);
            $stmt->bind_param("ssss", $imie, $nazwisko, $pseudonim, $hashed_password);
            $stmt->execute();
            $user_id = $conn->insert_id; // Pobranie ID nowo utworzonego użytkownika
            $stmt->close();

            // Logika dla tworzenia nowej rodziny lub dołączania do istniejącej
            if ($familyOption === 'new' && $familyName) {
                // Generowanie unikalnego kodu dołączenia dla rodziny
                $joinCode = sprintf("%05d", mt_rand(1, 99999));
                $insertFamilySql = "INSERT INTO rodziny (nazwa, kod_dolaczenia, id_zalozyciela) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($insertFamilySql);
                $stmt->bind_param("ssi", $familyName, $joinCode, $user_id);
                $stmt->execute();
                $family_id = $conn->insert_id; // Pobranie ID nowo utworzonej rodziny
                $stmt->close();

                // Aktualizacja rekordu użytkownika o ID rodziny
                $updateUserSql = "UPDATE uzytkownicy SET id_rodziny = ? WHERE id = ?";
                $stmt = $conn->prepare($updateUserSql);
                $stmt->bind_param("ii", $family_id, $user_id);
                $stmt->execute();
                $stmt->close();
            } elseif ($familyOption === 'join' && $familyCode) {
                // Logika dołączania do istniejącej rodziny
                $stmt = $conn->prepare("SELECT id FROM rodziny WHERE kod_dolaczenia = ?");
                $stmt->bind_param("s", $familyCode);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $familyRow = $result->fetch_assoc();
                    $family_id = $familyRow['id'];

                    // Wstawienie prośby o dołączenie do rodziny
                    $insertRequestSql = "INSERT INTO prosby_dołączenia (id_rodziny, id_uzytkownika) VALUES (?, ?)";
                    $stmt = $conn->prepare($insertRequestSql);
                    $stmt->bind_param("ii", $family_id, $user_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Błąd, jeśli nie znaleziono rodziny o podanym kodzie
                    $errors[] = "Nie znaleziono rodziny o takim kodzie.";
                }
            }

            // Zatwierdzenie transakcji i przekierowanie do strony logowania
            $conn->commit();
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            // Wycofanie transakcji w przypadku błędu i zapisanie komunikatu o błędzie
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

// Zamknięcie połączenia z bazą danych
$conn->close();

// Wyświetlenie komunikatów o błędach, jeśli takie wystąpiły
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Rejestracja</title>
    <!-- Dołączenie stylów CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>

<body>
    <div class="register-container">
        <h1> Rejestracja </h1>
        <!-- Formularz rejestracyjny -->
        <form action="register.php" method="post">
            <!-- Pola formularza dla danych użytkownika -->
            <input type="text" name="firstName" placeholder="imie" required>
            <input type="text" name="lastName" placeholder="nazwisko" required>
            <input type="text" name="nickname" placeholder="nazwa użytkownika" required>
            <!-- Wyświetlenie błędu jeśli pseudonim zajęty -->
            <?php if (in_array("Nazwa użytkownika jest zajęta, wybierz inną.", $errors)): ?>
            <div style="color: red;">Nazwa użytkownika jest zajęta, wybierz inną.</div>
            <?php endif; ?>
            <!-- Pole hasła z opcją pokazania/ukrycia -->
            <div class="input-container">
                <input type="password" name="password" placeholder="hasło" id="password" required>
                <span id="togglePassword" style="cursor: pointer;">👁️</span>
            </div>

            <!-- Opcje dotyczące rodziny -->
            <div class="radio-group">
                <div class="radio-option">
                    <input type="radio" id="joinFamily" name="familyOption" value="join" onchange="toggleFamilyCodeInput()" checked>
                    <label for="joinFamily"> Dołącz do rodziny</label>
                </div>
                <div class="radio-option">
                    <input type="radio" id="newFamily" name="familyOption" value="new" onchange="toggleFamilyCodeInput()">
                    <label for="newFamily"> Dodaj nową rodzinę</label>
                </div>
            </div>

            <!-- Pole kodu rodziny -->
            <div id="familyCodeInput">
                <input type="text" name="familyCode" placeholder="Podaj kod rodziny" pattern="\d{5}" title="Kod rodziny musi składać się z 5 cyfr.">
            </div>

            <!-- Pole nazwy nowej rodziny -->
            <div id="familyNameContainer" style="display:none;">
                <input type="text" name="familyName" placeholder="Podaj nazwę rodziny">
            </div>

            <!-- Przycisk rejestracji -->
            <button type="submit">Zarejestruj się</button>
        </form>
        <!-- Link do strony logowania dla istniejących użytkowników -->
        <p class="login-link">Masz już konto? <a href="login.php">Zaloguj się</a></p>
    </div>
    <!-- Skrypt do obsługi pokazywania/ukrywania hasła -->
    <script src="password.js"></script>
</body>

</html>
