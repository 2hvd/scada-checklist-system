<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT c.*, u.username FROM comments c 
     JOIN users u ON c.user_id = u.id 
     WHERE c.swo_id = ? 
     ORDER BY c.created_at ASC"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$comments = [];
while ($row = $result->fetch_assoc()) {
    $comments[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Comments retrieved', $comments);
