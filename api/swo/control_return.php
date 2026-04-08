<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('control');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id   = intval($input['swo_id'] ?? 0);
$comments = sanitizeInput($input['comments'] ?? '');

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

if ($swo['status'] !== 'Pending Control Review') {
    $conn->close();
    jsonResponse(false, 'SWO is not pending control review');
}

// Update SWO status to Returned from Control
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'Returned from Control',
     control_reviewer_id = ?, control_reviewed_at = NOW(),
     rejection_reason = ?
     WHERE id = ?"
);
$stmt->bind_param('isi', $user_id, $comments, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to return submission');
}
$stmt->close();

// Record control review decision
$stmt = $conn->prepare(
    "INSERT INTO control_reviews (swo_id, reviewed_by, decision, comments)
     VALUES (?, ?, 'return', ?)
     ON DUPLICATE KEY UPDATE decision = 'return', comments = VALUES(comments), created_at = NOW()"
);
$stmt->bind_param('iis', $swo_id, $user_id, $comments);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'CONTROL_RETURN', 'Pending Control Review', 'Returned from Control');

$conn->close();
jsonResponse(true, 'Submission returned to Support for re-review');
