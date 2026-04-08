<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// My SWOs with status counts
$swoStmt = $conn->prepare(
    "SELECT s.*,
        ua.username AS assigned_to_name,
        uap.username AS approved_by_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     LEFT JOIN users uap ON s.approved_by = uap.id
     WHERE s.created_by = ?
     ORDER BY s.created_at DESC"
);
$swoStmt->bind_param('i', $user_id);
$swoStmt->execute();
$swoResult = $swoStmt->get_result();
$mySwos = [];
while ($row = $swoResult->fetch_assoc()) {
    $mySwos[] = $row;
}
$swoStmt->close();

// SWOs awaiting support review (all pending, not just own)
$pendingStmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.status,
            s.submitted_at, s.rejection_reason,
            ua.username AS assigned_to_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     WHERE s.status IN ('Pending Support Review', 'Returned from Control')
     ORDER BY s.submitted_at DESC"
);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingSubmissions = [];
while ($row = $pendingResult->fetch_assoc()) {
    $pendingSubmissions[] = $row;
}
$pendingStmt->close();

// Status summary
$summaryResult = $conn->query(
    "SELECT status, COUNT(*) as count FROM swo_list WHERE created_by = $user_id GROUP BY status"
);
$statusSummary = [];
while ($row = $summaryResult->fetch_assoc()) {
    $statusSummary[$row['status']] = intval($row['count']);
}

$conn->close();
jsonResponse(true, 'Support data retrieved', [
    'my_swos'             => $mySwos,
    'pending_submissions' => $pendingSubmissions,
    'status_summary'      => $statusSummary,
]);
