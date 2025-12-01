<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

if (!isset($_SESSION['user']) || !_has_access('tickets_assign')) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = $_POST['ticket_id'];
    $manager_id = $_POST['manager_id'];

    if ($ticket_id && $manager_id) {
        $stmt = $conn->prepare("UPDATE tickets SET assigned_to = ? WHERE id = ?");
        $stmt->bind_param("ii", $manager_id, $ticket_id);
        $stmt->execute();
    }
}

header("Location: admin.php");
exit;
