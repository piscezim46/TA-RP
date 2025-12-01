<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

if (!isset($_SESSION['user']) || !_has_access('roles_view')) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: roles.php'); exit;
}

$role_name = trim($_POST['role_name'] ?? '');
$department_id = $_POST['department_id'] ?? null;
$description = $_POST['description'] ?? '';
$access_ids = $_POST['access_ids'] ?? [];

if ($department_id === '') $department_id = null;
if ($role_name === '') {
    $_SESSION['flash'] = ['error' => 'Role name required'];
    header('Location: roles.php'); exit;
}

// insert role
$stmt = $conn->prepare("INSERT INTO roles (role_name, department_id, description) VALUES (?, ?, ?)");
if (!$stmt) {
    error_log('create_role prepare failed: ' . $conn->error);
    $_SESSION['flash'] = ['error' => 'DB error'];
    header('Location: roles.php'); exit;
}
if ($department_id === null) $stmt->bind_param('iss', $role_name, $department_id, $description);
// bind_param requires correct types; easier to use a short branch
$stmt->close();

// fallback: use simple query to avoid bind type complexity
$dept_sql = is_null($department_id) ? 'NULL' : (int)$department_id;
$role_name_esc = $conn->real_escape_string($role_name);
$description_esc = $conn->real_escape_string($description);
$ins = $conn->query("INSERT INTO roles (role_name, department_id, description) VALUES ('{$role_name_esc}', {$dept_sql}, '{$description_esc}')");
if (!$ins) {
    error_log('create_role insert failed: ' . $conn->error);
    $_SESSION['flash'] = ['error' => 'DB insert failed'];
    header('Location: roles.php'); exit;
}
$role_id = (int)$conn->insert_id;

// assign access rights
if (is_array($access_ids) && count($access_ids) > 0) {
    $vals = [];
    foreach ($access_ids as $aid) {
        $aid_i = (int)$aid;
        if ($aid_i <= 0) continue;
        $vals[] = "({$role_id}, {$aid_i})";
    }
    if (!empty($vals)) {
        $q = "INSERT IGNORE INTO role_access_rights (role_id, access_id) VALUES " . implode(',', $vals);
        $conn->query($q);
    }
}

$_SESSION['flash'] = ['success' => 'Role created'];
header('Location: roles.php?created=' . urlencode($role_id));
exit;
?>