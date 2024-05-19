<?php
$servername = "localhost";
$username = "root";  
$password = "";      
$dbname = "zarzadzanie_budzetem";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>