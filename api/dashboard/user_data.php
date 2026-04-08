<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get assigned SWOs
$stmt = $conn->prepare(
    "SELECT s.*, uc.username AS created_by_name
     FROM swo_list s
     LEFT JOIN users uc ON s.created_by = uc.id
     WHERE s.assigned_to = ?
     ORDER BY s.assigned_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$swos = [];

while ($swo = $result->fetch_assoc()) {
    // Get checklist progress
    $csStmt = $conn->prepare(
        "SELECT status, COUNT(*) as cnt FROM checklist_status 
         WHERE swo_id = ? AND user_id = ? GROUP BY status"
    );
    $csStmt->bind_param('ii', $swo['id'], $user_id);
    $csStmt->execute();
    $csResult = $csStmt->get_result();
    $counts = ['done'=>0,'na'=>0,'not_yet'=>0,'still'=>0,'empty'=>0];
    while ($row = $csResult->fetch_assoc()) {
        $counts[$row['status']] = intval($row['cnt']);
    }
    $csStmt->close();

    $total = array_sum($counts);
    $completed = $counts['done'] + $counts['na'];
    $progress = $total > 0 ? round($completed / $total * 100, 1) : 0;

    $swo['checklist_counts'] = $counts;
    $swo['progress'] = $progress;
    $swo['total_items'] = $total;
    $swos[] = $swo;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'User data retrieved', ['swos' => $swos]);
