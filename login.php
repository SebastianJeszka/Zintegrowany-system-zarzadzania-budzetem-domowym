<?php
// Rozpoczęcie sesji, aby móc korzystać z zmiennych sesyjnych
session_start();

// Dołączenie pliku konfiguracyjnego bazy danych
include 'config.php';

// Zmienna do przechowywania komunikatów o błędach logowania
$login_error = '';

// Sprawdzenie, czy formularz został wysłany metodą POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Oczyszczenie danych z formularza i przypisanie ich do zmiennych
    $pseudonim = $conn->real_escape_string($_POST['login']);
    $haslo = $_POST['password'];

    // Przygotowanie zapytania SQL do pobrania danych użytkownika na podstawie pseudonimu
    $sql = "SELECT id, imie, haslo, id_rodziny FROM uzytkownicy WHERE pseudonim = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pseudonim);
    $stmt->execute();
    $result = $stmt->get_result();

    // Sprawdzenie, czy użytkownik o danym pseudonimie istnieje w bazie danych
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Weryfikacja hasła użytkownika
        if (password_verify($haslo, $user['haslo'])) {
            // Ustawienie danych użytkownika w sesji
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['imie'];
            $_SESSION['id_rodziny'] = $user['id_rodziny']; // Przechowywanie ID rodziny użytkownika w sesji

            // Sprawdzenie, czy budżet rodziny jest ustawiony
            if (is_null($user['budzet'])) {
                // Ustawienie flagi w sesji, jeśli budżet nie jest ustawiony
                $_SESSION['prompt_for_budget'] = true;
            }
            
            // Przekierowanie do strony głównej po pomyślnym zalogowaniu
            header("location: index.php");
            exit();
        } else {
            // Ustawienie komunikatu o błędzie, jeśli hasło jest niepoprawne
            $login_error = "Niepoprawny login lub hasło";
        }
    } else {
        // Ustawienie komunikatu o błędzie, jeśli użytkownik nie istnieje
        $login_error = "Niepoprawny login lub hasło";
    }
    // Zamknięcie zapytania i połączenia z bazą danych
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <title>Logowanie</title>
    <!-- Dołączenie pliku CSS dla strony logowania -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="login-container">
        <h1> Logowanie </h1>
        <!-- Wyświetlenie komunikatu o błędzie, jeśli istnieje -->
        <?php if (!empty($login_error)): ?>
        <div class="login-error"><?php echo $login_error; ?></div>
        <?php endif; ?>

        <!-- Formularz logowania -->
        <form method="post">
            <input type="text" name="login" placeholder="login" required>
            <div class="input-container">
                <input type="password" name="password" placeholder="hasło" id="password" required>
                <!-- Przycisk do pokazywania/ukrywania hasła -->
                <span id="togglePassword" style="cursor: pointer;">👁️</span>
            </div>

            <button type="submit">Zaloguj się</button>
        </form>
        <!-- Link do strony rejestracji -->
        <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
    </div>
    <!-- Dołączenie skryptu JS do obsługi pokazywania/ukrywania hasła -->
    <script src="password.js"></script>
</body>

</html>
