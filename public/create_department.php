<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/access.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !_has_access('departments_view')) {
    http_response_code(403);
    echo json_encode(['error'=>'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid input']);
    exit;
}

$deptName = trim($input['department_name'] ?? '');
$shortName = trim($input['short_name'] ?? '');
$director = trim($input['director_name'] ?? '');
$teams = is_array($input['teams']) ? $input['teams'] : [];

if ($deptName === '' || $director === '') {
    http_response_code(400);
    echo json_encode(['error'=>'Department name and director are required']);
    exit;
}

// Insert department (including optional short_name)
$stmt = $conn->prepare("INSERT INTO departments (department_name, short_name, director_name) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $deptName, $shortName, $director);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error'=>'Failed to insert department: '.$conn->error]);
    exit;
}
$deptId = $stmt->insert_id;
$stmt->close();

$insertedTeams = [];
if (count($teams) > 0) {
    $tstmt = $conn->prepare("INSERT INTO teams (team_name, department_id, manager_name) VALUES (?, ?, ?)");
    foreach ($teams as $t) {
        $teamName = trim($t['team_name'] ?? '');
        $managerName = trim($t['manager_name'] ?? '');
        if ($teamName === '') continue;
        $tstmt->bind_param('sis', $teamName, $deptId, $managerName);
        if ($tstmt->execute()) {
            $insertedTeams[] = [
                'team_id' => $tstmt->insert_id,
                'team_name' => $teamName,
                'manager_name' => $managerName
            ];
        }
    }
    $tstmt->close();
}

echo json_encode([
    'success' => true,
    'department' => [
        'department_id' => (int)$deptId,
        'department_name' => $deptName,
        'short_name' => $shortName,
        'director_name' => $director,
        'teams' => $insertedTeams
    ]
]);
exit;
?>