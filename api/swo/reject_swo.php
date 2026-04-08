<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id = intval($input['swo_id'] ?? 0);
$reason = sanitizeInput($input['reason'] ?? '');

if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}
if (empty($reason)) {
    jsonResponse(false, 'Rejection reason is required');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, status FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$swo = $result->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['status'] !== 'Pending') {
    $conn->close();
    jsonResponse(false, 'Only Pending SWOs can be rejected');
}

$admin_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE swo_list SET status = 'Draft' WHERE id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to reject SWO');
}
$stmt->close();

// Add rejection comment
$item_key = null;
$comment_text = 'SWO Rejected: ' . $reason;
$stmt = $conn->prepare("INSERT INTO comments (user_id, swo_id, item_key, comment_text) VALUES (?,?,?,?)");
$stmt->bind_param('iiss', $admin_id, $swo_id, $item_key, $comment_text);
$stmt->execute();
$stmt->close();

logAudit($conn, $admin_id, $swo_id, 'REJECT_SWO', 'Pending', 'Draft');

// Log in submissions_history
$stmt = $conn->prepare("INSERT INTO submissions_history (user_id, swo_id, action, notes) VALUES (?,?,'reject',?)");
$stmt->bind_param('iis', $admin_id, $swo_id, $reason);
$stmt->execute();
$stmt->close();

$conn->close();
jsonResponse(true, 'SWO rejected and returned to Draft');
