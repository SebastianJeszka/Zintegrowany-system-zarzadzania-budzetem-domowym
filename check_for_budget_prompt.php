<?php
session_start();

// Tylko jeśli użytkownik jest zalogowany
if (isset($_SESSION['user_id'])) {
    // Sprawdzamy, czy zalogowany użytkownik jest założycielem rodziny
    // i czy powinno pojawić się okienko z ustawieniami budżetu.
    if (!empty($_SESSION['prompt_for_budget'])) {
        echo "true";
    } else {
        echo "false";
    }
} else {
    echo "false";
}
?>