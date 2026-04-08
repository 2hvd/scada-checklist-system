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
$comments = sanitizeInput($input['overall_comments'] ?? '');

if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn    = getDBConnection();
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

// Upsert overall review comments
$stmt = $conn->prepare(
    "INSERT INTO support_reviews (swo_id, reviewed_by, decision, comments)
     VALUES (?, ?, 'pending', ?)
     ON DUPLICATE KEY UPDATE comments = VALUES(comments), created_at = NOW()"
);
$stmt->bind_param('iis', $swo_id, $user_id, $comments);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to save overall comments');
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Overall comments saved');
