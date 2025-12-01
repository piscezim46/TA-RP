<?php
$host = "localhost";
$user = "root";
$pass = "StrongPassword123!";
$db   = "ticketing_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("DB connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
