<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id   = intval($input['swo_id'] ?? 0);
$item_key = sanitizeInput($input['item_key'] ?? '');
$comment  = sanitizeInput($input['comment'] ?? '');

if (!$swo_id || !$item_key) {
    jsonResponse(false, 'swo_id and item_key are required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify SWO is assigned to this user
$stmt = $conn->prepare("SELECT id FROM swo_list WHERE id = ? AND assigned_to = ?");
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found or not assigned to you');
}

// Upsert user item comment
$stmt = $conn->prepare(
    "INSERT INTO user_item_comments (swo_id, item_key, comment, user_id)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         comment  = VALUES(comment),
         saved_at = CURRENT_TIMESTAMP"
);
$stmt->bind_param('issi', $swo_id, $item_key, $comment, $user_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to save comment');
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Comment saved');
