<?php
// Utility helpers for updating user timestamps safely and centrally
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * Update the user's last_login to NOW(). Returns true on success, false otherwise.
 */
function update_last_login($conn, $userId) {
    if (empty($conn) || empty($userId)) return false;
    $id = (int)$userId;
    $stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Set password_changed_at = NOW() for a user. Returns true on success.
 * Keep this separate from password UPDATE so callers can control when the timestamp is changed.
 */
function set_password_changed_at_now($conn, $userId) {
    if (empty($conn) || empty($userId)) return false;
    $id = (int)$userId;
    $stmt = $conn->prepare('UPDATE users SET password_changed_at = NOW() WHERE id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

?>
