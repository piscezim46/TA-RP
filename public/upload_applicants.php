<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user'])) {
    die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdfs'])) {
    $position_id = intval($_POST['position_id']);
    $user_id = $_SESSION['user']['id']; // uploader
    
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    foreach ($_FILES['pdfs']['tmp_name'] as $index => $tmpName) {
        $fileName = basename($_FILES['pdfs']['name'][$index]);
        $targetFile = $uploadDir . time() . "_" . $fileName;

        // Validate file
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileType !== "pdf") {
            echo "Skipping $fileName: not a PDF<br>";
            continue;
        }

        if (move_uploaded_file($tmpName, $targetFile)) {
            // Save applicant
            $stmt = $conn->prepare("INSERT INTO applicants (resume_file) VALUES (?)");
            $stmt->bind_param("s", $targetFile);
            $stmt->execute();
            $applicant_id = $stmt->insert_id;

            // Create ticket
            $status = "Submitted";
            $stmt2 = $conn->prepare("INSERT INTO tickets (applicant_id, user_id, position_id, status, resume_path) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param("iiiss", $applicant_id, $user_id, $position_id, $status, $targetFile);
            $stmt2->execute();

            echo "Uploaded & ticket created for $fileName<br>";
        } else {
            echo "Error uploading $fileName<br>";
        }
    }

    echo "<a href='applicants.php'>Back to Applicants</a>";
}
