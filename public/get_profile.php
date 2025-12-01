<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$uid = intval($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user id']);
    exit;
}

$conn = $conn ?? null;
try {
    $stmt = $conn->prepare("SELECT u.id, u.name, u.user_name, u.email, u.department_id, u.team_id, d.department_name, t.team_name, t.manager_name, u.role_id, u.created_at FROM users u LEFT JOIN departments d ON u.department_id = d.department_id LEFT JOIN teams t ON u.team_id = t.team_id WHERE u.id = ? LIMIT 1");
    if (!$stmt) throw new Exception('Prepare failed');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    $roleName = '';
    if (!empty($row['role_id'])) {
        $r = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
        if ($r) {
            $r->bind_param('i', $row['role_id']);
            $r->execute();
            $rr = $r->get_result();
            if ($rr && ($rrow = $rr->fetch_assoc())) $roleName = $rrow['role_name'];
            $r->close();
        }
    }

    // Access keys are stored in session when the user logged in; fall back to empty array
    $access = $_SESSION['user']['access_keys'] ?? [];

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$row['id'],
            'name' => $row['name'] ?? '',
            'user_name' => $row['user_name'] ?? '',
            'email' => $row['email'] ?? '',
            'department' => $row['department_name'] ?? '',
            'team' => $row['team_name'] ?? '',
            'manager' => $row['manager_name'] ?? '',
            'role' => $roleName,
            'created_at' => $row['created_at'] ?? null,
            'access_keys' => $access
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
exit;
