<?php
// public/flows_update.php - update one or more existing statuses and their transitions
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']) || !_has_access('flows_view', ['admin','hr'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// normalize to rows array for bulk updates
$rows = [];
if (isset($data['rows']) && is_array($data['rows'])) {
    $rows = $data['rows'];
} else {
    $rows = [$data];
}

try {
    $conn->begin_transaction();
    foreach ($rows as $dataRow) {
        $id = isset($dataRow['status_id']) ? (int)$dataRow['status_id'] : 0;
        $name = trim((string)($dataRow['status_name'] ?? ''));
        $color = trim((string)($dataRow['status_color'] ?? '')) ?: null;
        $pool_id = isset($dataRow['pool_id']) && $dataRow['pool_id'] !== '' ? (int)$dataRow['pool_id'] : null;
        $sort_order = isset($dataRow['sort_order']) ? (int)$dataRow['sort_order'] : null;
        $active = !empty($dataRow['active']) ? 1 : 0;
        $transitions = isset($dataRow['transitions']) && is_array($dataRow['transitions']) ? $dataRow['transitions'] : [];

        if ($id <= 0) throw new Exception('Invalid id');
        if ($name === '') throw new Exception('Name required');
        if ($pool_id === null) throw new Exception('Pool required');
        if ($sort_order === null || $sort_order <= 0) throw new Exception('Sort must be > 0');
        if (!is_array($transitions) || count($transitions) === 0) throw new Exception('At least one transition required');

        // check sort conflict with other active statuses
        $stmt = $conn->prepare('SELECT status_id FROM positions_status WHERE active = 1 AND sort_order = ? AND status_id <> ? LIMIT 1');
        if (!$stmt) throw new Exception('DB error');
        $stmt->bind_param('ii',$sort_order,$id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); throw new Exception('Sort conflict for id ' . $id); }
        $stmt->close();

        // perform update
        $up = $conn->prepare('UPDATE positions_status SET status_name=?, status_color=?, pool_id=?, active=?, sort_order=? WHERE status_id=?');
        if (!$up) throw new Exception('Prepare update failed');
        $up->bind_param('ssiiii',$name,$color,$pool_id,$active,$sort_order,$id);
        if (!$up->execute()) throw new Exception('Update failed: ' . $up->error);
        $up->close();

        // replace transitions
        $del = $conn->prepare('DELETE FROM positions_status_transitions WHERE from_status_id = ?');
        if (!$del) throw new Exception('Prepare delete failed');
        $del->bind_param('i',$id);
        if (!$del->execute()) throw new Exception('Delete failed: ' . $del->error);
        $del->close();
        // include audit info on transition inserts
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $ins = $conn->prepare('INSERT INTO positions_status_transitions (from_status_id, to_status_id, active, updated_by, updated_at) VALUES (?, ?, 1, ?, NOW())');
        if (!$ins) throw new Exception('Prepare insert trans failed');
        foreach($transitions as $to) {
            $to_id = (int)$to;
            $ins->bind_param('iii',$id,$to_id,$userId);
            if (!$ins->execute()) throw new Exception('Insert trans failed: ' . $ins->error);
        }
        $ins->close();
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
    exit;

} catch(Exception $e) {
    $conn->rollback();
    error_log('flows_update.php error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}

?>