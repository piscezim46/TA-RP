<?php

session_start();
require_once '../includes/db.php';

// Restrict access
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$applicant_ids = is_array($input['applicant_ids'] ?? null) ? array_values(array_unique($input['applicant_ids'])) : [];

if (!$applicant_ids) {
    http_response_code(400);
    echo 'No applicant IDs provided';
    exit;
}

// Prepare zip
$zip = new ZipArchive();
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'resumes_' . uniqid() . '.zip';
if ($zip->open($tmpFile, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    echo 'Could not create zip';
    exit;
}

$added = 0;
$stmt = $conn->prepare("SELECT resume_path, full_name, applicant_id FROM applicants WHERE applicant_id = ?");
foreach ($applicant_ids as $aid) {
    $stmt->bind_param('s', $aid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || empty($row['resume_path'])) continue;

    // sanitize and resolve path - assume uploads are stored in public/uploads/
    $basename = basename($row['resume_path']);
    $uploadsDir = realpath(__DIR__ . '/uploads') ?: (__DIR__ . '/uploads');
    $filePath = $uploadsDir . DIRECTORY_SEPARATOR . $basename;

    if (!file_exists($filePath)) continue;

    // archive name: applicantid_fullname_basename (sanitize)
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '_', ($row['applicant_id'] . '_' . ($row['full_name'] ?? 'applicant') . '_' . $basename));
    $zip->addFile($filePath, $safeName);
    $added++;
}
$stmt->close();

if ($added === 0) {
    $zip->close();
    @unlink($tmpFile);
    http_response_code(404);
    echo 'No resume files found for selected applicants';
    exit;
}

$zip->close();

// Send zip
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="resumes.zip"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
