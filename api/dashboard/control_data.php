<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('control');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// SWOs pending control review
$pendingStmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.kcor, s.status,
            s.submitted_at, s.support_reviewed_at,
            ua.username AS assigned_to_name,
            us.username AS support_reviewer_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     LEFT JOIN users us ON s.support_reviewer_id = us.id
     WHERE s.status = 'Pending Control Review'
     ORDER BY s.support_reviewed_at DESC"
);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingReviews = [];
while ($row = $pendingResult->fetch_assoc()) {
    $pendingReviews[] = $row;
}
$pendingStmt->close();

// Completed SWOs reviewed by this control user
$completedStmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.status,
            s.control_reviewed_at,
            ua.username AS assigned_to_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     WHERE s.status = 'Completed' AND s.control_reviewer_id = ?
     ORDER BY s.control_reviewed_at DESC
     LIMIT 50"
);
$completedStmt->bind_param('i', $user_id);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$completedReviews = [];
while ($row = $completedResult->fetch_assoc()) {
    $completedReviews[] = $row;
}
$completedStmt->close();

// Status summary
$summaryResult = $conn->query(
    "SELECT
        SUM(status = 'Pending Control Review') AS pending,
        SUM(status = 'Completed') AS completed
     FROM swo_list"
);
$summary = $summaryResult->fetch_assoc();

$conn->close();

jsonResponse(true, 'Control data retrieved', [
    'pending_reviews'   => $pendingReviews,
    'completed_reviews' => $completedReviews,
    'summary'           => [
        'pending'   => intval($summary['pending'] ?? 0),
        'completed' => intval($summary['completed'] ?? 0),
    ],
]);
