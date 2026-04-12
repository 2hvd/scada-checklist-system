<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
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

// Fetch all item comments for this SWO and user
$stmt = $conn->prepare(
    "SELECT item_key, comment FROM user_item_comments WHERE swo_id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[$row['item_key']] = $row['comment'];
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Comments retrieved', ['comments' => $comments]);
