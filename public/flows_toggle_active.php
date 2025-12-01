<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !_has_access('flows_edit', ['admin','hr'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Access denied']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['status_id'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

$status_id = (int)$data['status_id'];
$new_active = isset($data['active']) ? ((int)$data['active'] ? 1 : 0) : null;
if ($status_id <= 0 || $new_active === null) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid parameters']);
    exit;
}

// update positions_status and all transitions that reference the status
$conn->begin_transaction();
try {
    $stmt = $conn->prepare('UPDATE positions_status SET active = ? WHERE status_id = ?');
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param('ii', $new_active, $status_id);
    if (!$stmt->execute()) throw new Exception('Exec failed: ' . $stmt->error);
    $stmt->close();

    // update any transitions that reference this status (either from or to)
    $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    $stmt2 = $conn->prepare('UPDATE positions_status_transitions SET active = ?, updated_by = ?, updated_at = NOW() WHERE from_status_id = ? OR to_status_id = ?');
    if (!$stmt2) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt2->bind_param('iiii', $new_active, $userId, $status_id, $status_id);
    if (!$stmt2->execute()) throw new Exception('Exec failed: ' . $stmt2->error);
    $stmt2->close();

    $conn->commit();
    echo json_encode(['success'=>true, 'status_id'=>$status_id, 'active'=>$new_active]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('flows_toggle_active.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error']);
}

?>
