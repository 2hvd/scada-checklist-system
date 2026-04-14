<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
}

$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT cir.item_key, cir.control_decision, cir.control_comment,
            uic.comment AS user_comment
     FROM control_item_reviews cir
     LEFT JOIN user_item_comments uic
           ON uic.swo_id    = cir.swo_id
          AND uic.item_key  = cir.item_key
          AND uic.user_id   = (SELECT assigned_to FROM swo_list WHERE id = cir.swo_id)
     WHERE cir.swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Control item reviews retrieved', $reviews);
