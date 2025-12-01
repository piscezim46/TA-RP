<?php
// public/update_ticket_status.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../includes/db.php';

// Allowed statuses (match your ENUM exactly)
$ALLOWED = ['Submitted','Reviewed','Shortlisted','Interview','Rejected','Hired','CEO'];

try {
  // Read JSON
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);

  if (!$data || !isset($data['ticket_ids'], $data['new_status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
  }

  $newStatus = trim($data['new_status']);
  if (!in_array($newStatus, $ALLOWED, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid status']);
    exit;
  }

  $ids = array_values(array_filter(array_map('intval', (array)$data['ticket_ids']), fn($v) => $v > 0));
  if (count($ids) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid ticket ids']);
    exit;
  }

  // Build placeholders (?, ?, ?, ...)
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));

  $sql = "UPDATE tickets SET status = ? WHERE id IN ($placeholders)";
  $stmt = $conn->prepare($sql);

  // bind status + ids
  $bindTypes = 's' . $types;
  $bindValues = array_merge([$newStatus], $ids);
  $stmt->bind_param($bindTypes, ...$bindValues);

  $stmt->execute();
  echo json_encode(['updated' => $stmt->affected_rows]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
