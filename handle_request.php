<?php
include 'config.php'; // Dołączenie pliku konfiguracyjnego z danymi do połączenia z bazą danych.
session_start(); // Rozpoczęcie sesji.

$response = ['success' => false, 'message' => '']; // Inicjalizacja domyślnej odpowiedzi.

// Sprawdzenie, czy żądanie jest typu POST i czy wymagane dane są dostępne.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'], $_POST['userId'], $_POST['action'])) {
    $user_id = $_SESSION['user_id']; // ID zalogowanego użytkownika.
    $request_user_id = $_POST['userId']; // ID użytkownika, którego prośba jest przetwarzana.
    $action = $_POST['action']; // Akcja do wykonania (akceptacja lub odrzucenie).

    $conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE); // Rozpoczęcie transakcji.
    
    // Sprawdzenie, czy zalogowany użytkownik jest założycielem rodziny.
    $founderCheckStmt = $conn->prepare("SELECT id FROM rodziny WHERE id_zalozyciela = ?");
    $founderCheckStmt->bind_param("i", $user_id);
    $founderCheckStmt->execute();
    $founderCheckResult = $founderCheckStmt->get_result();
    $founderCheckStmt->close();

    if ($founderCheckResult->num_rows === 0) {
        $response['message'] = 'Nie jesteś założycielem rodziny.';
        $conn->rollback(); // Wycofanie transakcji, jeśli użytkownik nie jest założycielem.
    } else {
        if ($action === 'accept') {
            // Akceptacja prośby: aktualizacja ID rodziny dla użytkownika.
            $updateUserStmt = $conn->prepare("UPDATE uzytkownicy SET id_rodziny = (SELECT id FROM rodziny WHERE id_zalozyciela = ?) WHERE id = ?");
            $updateUserStmt->bind_param("ii", $user_id, $request_user_id);
            $updateSuccess = $updateUserStmt->execute();
            $updateUserStmt->close();

            if ($updateSuccess) {
                // Usunięcie prośby o dołączenie po akceptacji.
                $deleteRequestStmt = $conn->prepare("DELETE FROM prosby_dołączenia WHERE id_uzytkownika = ?");
                $deleteRequestStmt->bind_param("i", $request_user_id);
                $deleteRequestStmt->execute();
                $deleteRequestStmt->close();

                $response['success'] = true;
                $response['message'] = 'Użytkownik został pomyślnie dodany do rodziny.';
                $conn->commit(); // Zatwierdzenie transakcji.
            } else {
                $response['message'] = 'Nie udało się zaktualizować id_rodziny użytkownika.';
                $conn->rollback(); // Wycofanie transakcji w przypadku błędu.
            }
        } elseif ($action === 'reject') {
            // Odrzucenie prośby: usunięcie prośby z bazy danych.
            $deleteRequestStmt = $conn->prepare("DELETE FROM prosby_dołączenia WHERE id_uzytkownika = ?");
            $deleteRequestStmt->bind_param("i", $request_user_id);
            $deleteRequestStmt->execute();
            $deleteRequestStmt->close();

            $deleteUserStmt = $conn->prepare("DELETE FROM uzytkownicy WHERE id = ?");
            $deleteUserStmt->bind_param("i", $request_user_id);
            if ($deleteUserStmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Prośba o dołączenie została odrzucona i użytkownik Usunięty.';
                $conn->commit();
                } else {
                $response['message'] = 'Nie udało się usunąć użytkownika.';
                $conn->rollback();
                }
                $deleteUserStmt->close();
                } else {
                $response['message'] = 'Nieprawidłowa akcja.';
                $conn->rollback();
                }
                }
                } else {
                $response['message'] = 'Nieautoryzowany dostęp lub błędne żądanie';
                $conn->rollback();
                }
                
                $conn->close();
                echo json_encode($response);
                ?>