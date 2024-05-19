<?php
include 'config.php'; // Dołącza plik konfiguracyjny bazy danych.
session_start(); // Rozpoczyna sesję PHP, aby można było korzystać ze zmiennych sesji.

// Upewnij się, że użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
header("Location: login.php");
exit();
}
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
    <link rel="stylesheet" href="css/expense_forecast.css">
</head>

<body>

    <header>
        <h1>ZARZĄDZANIE BUDŻETEM DOMOWYM</h1>
        <div class="logo-container">
            <img src="logo.png" alt="BudgetMaster Logo"> <!-- Upewnij się, że ścieżka do obrazu jest prawidłowa -->
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
        <div class="forecast-container">
            <h1> Prognozowanie wydatków </h1>
            <!-- Formularz do wyboru miesiąca i roku dla prognozy -->
            <form id="forecastForm" method="post">
                <label for="month">Wybierz miesiąc:</label>
                <select name="month" id="month">
                    <?php
                $currentMonth = date('n');
                $currentYear = date('Y');
                for ($month = $currentMonth + 1; $month <= 12; $month++) {
                    $formattedMonth = str_pad($month, 2, '0', STR_PAD_LEFT);
                    echo "<option value=\"$formattedMonth\">$formattedMonth</option>";
                }
                ?>
                </select>

                <label for="year">Wybierz rok:</label>
                <select name="year" id="year">
                    <?php
                echo "<option value=\"$currentYear\">$currentYear</option>";
                if ($currentMonth == 12) {
                    $nextYear = $currentYear + 1;
                    echo "<option value=\"$nextYear\">$nextYear</option>";
                }
                ?>
                </select>

                <input type="submit" value="Prognozuj wydatki">
            </form>

            <!-- Pole do wyświetlania prognozowanej kwoty -->
            <div id="forecastResult">
            </div>

            <!-- Przycisk do wyświetlania informacji o systemie prognozowania -->
            <button onclick="showForecastInfo()">Jak to działa?</button>

            <!-- Pole do wyświetlania informacji o systemie prognozowania -->
            <div id="forecastInfo" style="display:none;">
                Nasz system prognozowania wydatków wykorzystuje model ARIMA (Autoregressive Integrated Moving Average)
                do analizy historycznych danych finansowych i przewidywania przyszłych wydatków. Dane są agregowane
                miesięcznie i przetwarzane w celu stworzenia serii czasowej. Następnie, stosując model ARIMA, system
                analizuje wzorce w danych, uwzględniając trendy i sezonowość, aby precyzyjnie przewidzieć wydatki na
                nadchodzący miesiąc.
            </div>
        </div>
    </div>

    <script>
    function showForecastInfo() {
        var infoDiv = document.getElementById("forecastInfo");
        if (infoDiv.style.display === "none") {
            infoDiv.style.display = "block";
        } else {
            infoDiv.style.display = "none";
        }
    }
    document.addEventListener('DOMContentLoaded', function() {

        var sidebar = document.querySelector('.sidebar');
        var toggle = document.getElementById('toggleSidebar');

        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('expanded');
        });

        document.getElementById('forecastForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var month = document.getElementById('month').value;
            var year = document.getElementById('year').value;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'expense_forecast_processor.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                // Aktualizacja treści diva 'forecastResult' odpowiedzią z PHP
                document.getElementById('forecastResult').innerHTML = this.responseText;
            };
            xhr.send('month=' + month + '&year=' + year);
        });
    });
    </script>

    <footer>
        <p>&copy; Zarządzanie Budżetem Domowym - Sebastian Jeszka - Politechnika Koszalińska - Wydział Elektroniki i
            Informatyki - 2023</p>
    </footer>
</body>

</html>