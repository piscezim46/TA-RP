<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

// authorization: require explicit 'users_bulk_force_reset' access right
if (!isset($_SESSION['user']) || !_has_access('users_bulk_force_reset')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];

// sanitize, dedupe and keep only positive ints
$clean = [];
foreach ($ids as $v) {
    $id = (int)$v;
    if ($id > 0) $clean[$id] = $id;
}
$clean = array_values($clean);
if (empty($clean)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid ids provided']);
    exit;
}

// Build safe IN list (integers only)
$inList = implode(',', $clean);

// perform update: set force_password_reset = 1 for all provided ids (inactive accounts included)
$sql = "UPDATE users SET force_password_reset = 1 WHERE id IN ($inList)";
if ($conn->query($sql) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed']);
    exit;
}

// Now fetch which ids actually have force_password_reset = 1
$checkSql = "SELECT id FROM users WHERE id IN ($inList) AND force_password_reset = 1";
$r = $conn->query($checkSql);
$updated = [];
if ($r) {
    while ($row = $r->fetch_assoc()) $updated[] = (int)$row['id'];
    $r->free();
}

$failed = array_values(array_diff($clean, $updated));
// Insert a log row for each updated id (bulk action)
try {
    $performedBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
    $note = 'Bulk force reset via UI';
    $lin = $conn->prepare('INSERT INTO force_reset_logs (target_user_id, action_type, performed_by_user_id, note) VALUES (?, ?, ?, ?)');
    if ($lin) {
        $atype = 'bulk';
        foreach ($updated as $tid) {
            $tid_i = (int)$tid;
            $lin->bind_param('isis', $tid_i, $atype, $performedBy, $note);
            $lin->execute();
        }
        $lin->close();
    }
} catch (Throwable $e) { /* non-fatal */ }

echo json_encode(['success' => true, 'requested' => count($clean), 'updated_ids' => $updated, 'failed_ids' => $failed]);
exit;

