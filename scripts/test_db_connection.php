<?php
// Diagnostic script: tests DB credentials in config.php and reports connection status
require_once __DIR__ . '/../config.php';

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : '';
$db   = defined('DB_NAME') ? DB_NAME : '';

header('Content-Type: application/json; charset=utf-8');

$report = [
    'host' => $host,
    'user' => $user,
    'db' => $db,
];

// Attempt mysqli connection without altering global error handlers
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    $report['ok'] = false;
    $report['error_no'] = $mysqli->connect_errno;
    $report['error'] = $mysqli->connect_error;
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit(1);
}

$report['ok'] = true;
$report['server_info'] = $mysqli->server_info;
$mysqli->close();
echo json_encode($report, JSON_PRETTY_PRINT);
