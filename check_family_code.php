<?php
include 'config.php';

$response = ['status' => 'not_found', 'message' => '']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['familyCode'])) {
    $familyCode = $conn->real_escape_string($_POST['familyCode']);
    $stmt = $conn->prepare("SELECT id FROM rodziny WHERE kod_dolaczenia = ?");
    $stmt->bind_param("s", $familyCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $response = ['status' => 'found', 'message' => 'Rodzina została znaleziona.'];
    } else {
        $response['message'] = 'Nie znaleziono rodziny o takim kodzie.';
    }
    $stmt->close();
}

$conn->close();
echo json_encode($response); 