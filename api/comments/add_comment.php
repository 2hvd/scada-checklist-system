<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id      = intval($input['swo_id'] ?? 0);
$item_key    = isset($input['item_key']) ? sanitizeInput($input['item_key']) : null;
$comment_text = sanitizeInput($input['comment_text'] ?? '');

if (!$swo_id || empty($comment_text)) {
    jsonResponse(false, 'SWO ID and comment text are required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "INSERT INTO comments (user_id, swo_id, item_key, comment_text) VALUES (?,?,?,?)"
);
$stmt->bind_param('iiss', $user_id, $swo_id, $item_key, $comment_text);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to add comment');
}
$comment_id = $stmt->insert_id;
$stmt->close();
$conn->close();

jsonResponse(true, 'Comment added', ['comment_id' => $comment_id]);
