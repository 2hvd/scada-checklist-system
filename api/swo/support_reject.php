<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id   = intval($input['swo_id'] ?? 0);
$comments = sanitizeInput($input['comments'] ?? '');

// Ensure rejection_reason is never stored empty so the user sees the Support Comment column
if (empty($comments)) {
    $comments = 'Reviewed by support';
}

if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify SWO state
$stmt = $conn->prepare("SELECT id, status FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if (!in_array($swo['status'], ['Pending Support Review', 'Returned from Control'])) {
    $conn->close();
    jsonResponse(false, 'SWO is not pending support review');
}

// Update SWO status back to In Progress (user can edit again)
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'In Progress',
     support_reviewer_id = ?, support_reviewed_at = NOW(),
     rejection_reason = ?
     WHERE id = ?"
);
$stmt->bind_param('isi', $user_id, $comments, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to reject submission');
}
$stmt->close();

// Record support review decision
$stmt = $conn->prepare(
    "INSERT INTO support_reviews (swo_id, reviewed_by, decision, comments)
     VALUES (?, ?, 'reject', ?)
     ON DUPLICATE KEY UPDATE decision = 'reject', comments = VALUES(comments), created_at = NOW()"
);
$stmt->bind_param('iis', $swo_id, $user_id, $comments);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'SUPPORT_REJECT', $swo['status'], 'In Progress');

$conn->close();
jsonResponse(true, 'Submission rejected and returned to user');
