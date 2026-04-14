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
    "SELECT sir.item_key, sir.support_decision, sir.support_comment,
            uic.comment AS user_comment
     FROM support_item_reviews sir
     LEFT JOIN user_item_comments uic
           ON uic.swo_id    = sir.swo_id
          AND uic.item_key  = sir.item_key
          AND uic.user_id   = (SELECT assigned_to FROM swo_list WHERE id = sir.swo_id)
     WHERE sir.swo_id = ?"
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

jsonResponse(true, 'Support item reviews retrieved', $reviews);
