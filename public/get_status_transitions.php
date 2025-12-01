<?php
// public/get_status_transitions.php
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$from = isset($_GET['from']) ? intval($_GET['from']) : 0;
if ($from <= 0) { echo json_encode(['ok'=>false,'error'=>'Missing from status id']); exit; }

// Optional type hint: 'applicant'|'interview' to prefer the correct transitions table
$typeHint = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
// Prefer the most common column names in the user's DB; record debug attempts
$attempts = [];
$result = [];

// Order: prefer from_status_id/to_status_id, then from_status/to_status, then from_id/to_id
// Try a set of candidate transitions tables and common column-name pairs.
$tablesToTry = ['interviews_status_transitions', 'applicants_status_transitions'];
// If caller requested applicant transitions prefer that table first; if caller requested interview prefer interviews first.
if ($typeHint === 'applicant' || $typeHint === 'applicants') {
    $tablesToTry = ['applicants_status_transitions', 'interviews_status_transitions'];
} elseif ($typeHint === 'interview' || $typeHint === 'interviews') {
    $tablesToTry = ['interviews_status_transitions', 'applicants_status_transitions'];
}
$tryCols = [ ['from_status_id','to_status_id'], ['from_status','to_status'], ['from_id','to_id'] ];
foreach ($tablesToTry as $tbl) {
    foreach ($tryCols as $cols) {
        $fromCol = $cols[0];
        $toCol = $cols[1];
        // Ensure both candidate columns exist in the transitions table before querying.
        // This prevents fatal errors when a table uses a different naming convention.
        try {
            $colExistsFrom = false; $colExistsTo = false;
            $chkFrom = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tbl) . "` LIKE '" . $conn->real_escape_string($fromCol) . "'");
            if ($chkFrom && $chkFrom->num_rows > 0) $colExistsFrom = true;
            $chkTo = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tbl) . "` LIKE '" . $conn->real_escape_string($toCol) . "'");
            if ($chkTo && $chkTo->num_rows > 0) $colExistsTo = true;
        } catch (Throwable $t) {
            $attempt['error'] = 'column check exception: ' . $t->getMessage();
            $attempts[] = $attempt;
            continue;
        }
        if (!$colExistsFrom || !$colExistsTo) {
            $attempt['error'] = 'missing columns: ' . ($colExistsFrom ? '' : $fromCol) . ' ' . ($colExistsTo ? '' : $toCol);
            $attempts[] = $attempt;
            continue;
        }

        // Decide which canonical status table to join depending on the
        // transitions table being queried. For interviews we want to use
        // `interview_statuses` (columns `id`, `name`), while legacy
        // applicants transitions use `applicants_status` (`status_id`,`status_name`).
        $statusTable = ($tbl === 'interviews_status_transitions') ? 'interview_statuses' : 'applicants_status';
        $idCol = ($statusTable === 'interview_statuses') ? 'id' : 'status_id';
        $nameCol = ($statusTable === 'interview_statuses') ? 'name' : 'status_name';

        // If the transitions table stores status names instead of ids, join
        // on the status name column; otherwise join on the id column.
        $usesNameJoin = (stripos($toCol, 'name') !== false);
        // Include target status color where available and, for applicants, only include active target statuses
        $activeFilter = ($statusTable === 'applicants_status') ? ' AND s.active = 1' : '';
        if ($usesNameJoin) {
            $sql = sprintf('SELECT s.%s AS to_id, s.%s AS status_name, COALESCE(s.status_color, "") AS status_color FROM %s t JOIN %s s ON s.%s = t.%s WHERE t.%s = ?%s ORDER BY t.%s ASC', $idCol, $nameCol, $tbl, $statusTable, $nameCol, $toCol, $fromCol, $activeFilter, $toCol);
        } else {
            $sql = sprintf('SELECT t.%s AS to_id, s.%s AS status_name, COALESCE(s.status_color, "") AS status_color FROM %s t JOIN %s s ON s.%s = t.%s WHERE t.%s = ?%s ORDER BY t.%s ASC', $toCol, $nameCol, $tbl, $statusTable, $idCol, $toCol, $fromCol, $activeFilter, $toCol);
        }
        $attempt = ['table' => $tbl, 'cols' => [$fromCol, $toCol], 'sql' => $sql, 'error' => null, 'rows' => 0];

        // If this tryCols variant expects names for the from column, resolve
        // the numeric $from id into a status_name to use as the bind param.
        $bindVal = $from;
        $bindType = 'i';
        if (stripos($fromCol, 'name') !== false) {
            // lookup status name for provided id from the appropriate status table
            try {
                $q = sprintf('SELECT %s AS status_name FROM %s WHERE %s = ? LIMIT 1', $nameCol, $statusTable, $idCol);
                $s = $conn->prepare($q);
                if ($s) {
                    $s->bind_param('i', $from);
                    $s->execute();
                    $sr = $s->get_result();
                    $srow = $sr ? $sr->fetch_assoc() : null;
                    $s->close();
                    if ($srow && isset($srow['status_name'])) {
                        $bindVal = $srow['status_name'];
                        $bindType = 's';
                    } else {
                        $attempt['error'] = 'from status id not found when resolving name';
                        $attempts[] = $attempt;
                        continue;
                    }
                } else {
                    $attempt['error'] = 'failed to prepare name lookup';
                    $attempts[] = $attempt;
                    continue;
                }
            } catch (Throwable $t) {
                $attempt['error'] = 'name lookup exception: ' . $t->getMessage();
                $attempts[] = $attempt;
                continue;
            }
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $attempt['error'] = $conn->error ?: 'prepare_failed';
            $attempts[] = $attempt;
            continue;
        }
        try {
            // bind dynamic type (i or s)
            $stmt->bind_param($bindType, $bindVal);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $result[] = [
                    'to_id' => (int)$row['to_id'], 
                    'to_name' => $row['status_name'],
                    'status_color' => isset($row['status_color']) ? $row['status_color'] : ''
                ];
                $attempt['rows']++;
            }
            $stmt->close();
            $attempts[] = $attempt;
            if (!empty($result)) break 2;
        } catch (Throwable $t) {
            $attempt['error'] = $t->getMessage();
            $attempts[] = $attempt;
            if ($stmt) { try { @$stmt->close(); } catch (Throwable $_) { } }
            continue;
        }
    }
}

// No hard-coded fallbacks: if no transitions were discovered the response
// should reflect the database state (empty transitions). The app relies on
// DB-configured transitions and status names/colors.

echo json_encode(['ok'=>true,'from'=> $from, 'transitions' => $result, 'debug_attempts' => $attempts]);
exit;
