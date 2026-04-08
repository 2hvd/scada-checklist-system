<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
}

$conn      = getDBConnection();
$user_id   = $_SESSION['user_id'];

// Verify the SWO exists and was created by this support user
$stmt = $conn->prepare("SELECT id, created_by FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['created_by'] != $user_id) {
    $conn->close();
    jsonResponse(false, 'Access denied');
}

// Fetch all support item reviews for this SWO
$stmt = $conn->prepare(
    "SELECT item_key, support_decision, support_comment, reviewed_by, reviewed_at
     FROM support_item_reviews
     WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = [
        'item_key'         => $row['item_key'],
        'support_decision' => $row['support_decision'] ?? '',
        'support_comment'  => $row['support_comment'] ?? '',
        'reviewed_by'      => $row['reviewed_by'],
        'reviewed_at'      => $row['reviewed_at'],
    ];
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Reviews retrieved', ['reviews' => $reviews]);
