<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/access.php';

// Only allow users with positions_view access. Keep legacy role-name fallback for admin/hr/manager
if (!isset($_SESSION['user']) || !_has_access('positions_view', ['admin','hr','manager'])) {
    http_response_code(403);
    exit('Access denied');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid position ID');
}

// Fetch current status and closure reason
$sql = "SELECT status, closure_reason FROM positions WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($status, $closure_reason);
$stmt->fetch();
$stmt->close();

// Prevent editing if already short-closed
if ($status === 'short-closed') {
    // Fetch all fields for display
    $sql = "SELECT title, experience_level, department, description, requirements FROM positions WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($title, $experience_level, $department, $description, $requirements);
    $stmt->fetch();
    $stmt->close();

    echo json_encode([
        'error' => 'Ticket is short-closed and cannot be modified.',
        'status' => $status,
        'closure_reason' => $closure_reason,
        'title' => $title,
        'experience_level' => $experience_level,
        'department' => $department,
        'description' => $description,
        'requirements' => $requirements
    ]);
    exit;
}

// Handle Short-Close
if (isset($_POST['closure_reason']) && trim($_POST['closure_reason']) !== '') {
    $closure_reason = trim($_POST['closure_reason']);
    $sql = "UPDATE positions SET status='short-closed', closure_reason=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $closure_reason, $id);
    $stmt->execute();
    $stmt->close();
    echo "Closed";
    exit;
}

// Handle Save (edit description)
if (isset($_POST['description'])) {
    $description = trim($_POST['description']);
    $sql = "UPDATE positions SET description=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $description, $id);
    $stmt->execute();
    $stmt->close();
    echo "Saved";
    exit;
}

// If not POST, just exit
http_response_code(405);
exit('Method not allowed');
?>
<div id="editButtons" style="display: flex; gap: 10px;">
    <button type="submit" class="modal-submit-btn" id="saveBtn">Save</button>
    <button type="button" onclick="closeEditModal()" class="modal-cancel-btn">TestCancel</button>
    <button type="button" onclick="showClosureReason()" class="modal-cancel-btn">Short-Close</button>
</div>
<div id="closureReasonDiv" style="display:none; margin-top:16px;">
    <label>Closure Reason:</label>
    <input type="text" name="closure_reason" class="modal-input">
    <button type="submit" class="modal-submit-btn" id="closureBtn" style="margin-top:8px;">Submit Closure</button>
</div>
<script>
// After fetching data from edit_position.php
if (data.status === 'short-closed') {
    document.getElementById('editModalContent').innerHTML = `
        <div class="alert alert-warning" style="background:#ffefc7;color:#b26d00;padding:12px 18px;border-radius:6px;margin-bottom:18px;">
            <strong>This ticket has been short-closed and cannot be modified.</strong>
        </div>
        <h3>Position Short-Closed</h3>
        <label>Title:</label><br>
        <input name="title" value="${data.title}" class="modal-input" readonly disabled><br><br>
        <label>Experience Level:</label><br>
        <input name="experience_level" value="${data.experience_level}" class="modal-input" readonly disabled><br><br>
        <label>Department:</label><br>
        <input name="department" value="${data.department}" class="modal-input" readonly disabled><br><br>
        <label>Description:</label><br>
        <textarea name="description" rows="4" class="modal-input" readonly disabled>${data.description}</textarea><br><br>
        <label>Requirements:</label><br>
        <input name="requirements" value="${data.requirements}" class="modal-input" readonly disabled><br><br>
        <label>Status:</label><br>
        <input value="Short-Closed" class="modal-input" readonly disabled><br><br>
        <label>Short-Close Reason:</label><br>
        <textarea class="modal-input" readonly disabled>${data.closure_reason}</textarea><br><br>
        <button type="button" onclick="closeEditModal()" class="modal-cancel-btn">Close</button>
    `;
}
</script>