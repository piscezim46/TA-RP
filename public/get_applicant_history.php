<?php
// public/get_applicant_history.php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: text/html; charset=utf-8');
session_start();

$applicant_id = isset($_GET['applicant_id']) ? intval($_GET['applicant_id']) : 0;
if ($applicant_id <= 0) { echo '<div style="color:#bbb;padding:8px;">No activity yet.</div>'; exit; }

$historyHtml = '<div style="color:#bbb;padding:8px;">No activity yet.</div>';
$entries = [];
$s_check = $conn->query("SHOW TABLES LIKE 'applicants_status_history'");
if ($s_check && $s_check->num_rows > 0) {
  $hs = $conn->prepare(
    "SELECT h.history_id AS id, h.applicant_id, h.position_id, h.status_id, COALESCE(s.status_name,'') AS status_name, h.updated_by, h.updated_at, h.reason
     FROM applicants_status_history h
     LEFT JOIN applicants_status s ON h.status_id = s.status_id
     WHERE h.applicant_id = ? ORDER BY h.updated_at DESC"
  );
  if ($hs) {
    $hs->bind_param('i', $applicant_id);
    $hs->execute();
    $hres = $hs->get_result();
    while ($r = $hres->fetch_assoc()) {
      $entries[] = array_merge($r, ['type' => 'status', 'ts' => $r['updated_at']]);
    }
    $hs->close();
  }
}
if (count($entries) > 0) {
  $historyHtml = '';
  foreach ($entries as $h) {
    $who = htmlspecialchars($h['updated_by'] ?: 'System');
    $at = htmlspecialchars($h['updated_at'] ?? '');
    $statusName = htmlspecialchars($h['status_name'] ?: '');
    $reason = nl2br(htmlspecialchars($h['reason'] ?? ''));
    $historyHtml .= '<div style="padding:8px;border-bottom:1px solid rgba(255,255,255,0.03);">';
    $historyHtml .= "<div style=\"font-size:13px;color:#ccc;\"><strong>Status</strong> — <span style=\"color:#aab;\">by {$who}</span> <span style=\"color:#777;font-size:12px;margin-left:8px;\">{$at}</span></div>";
    $historyHtml .= "<div style=\"margin-top:6px;color:#ddd;font-size:13px;\"><em>To:</em> <strong>{$statusName}</strong>";
    if (strlen(trim(strip_tags($reason))) > 0) $historyHtml .= "<div style=\"margin-top:6px;color:#cfd8e3;font-size:13px;\">Reason: {$reason}</div>";
    $historyHtml .= '</div>';
  }
}

echo $historyHtml;
exit;
