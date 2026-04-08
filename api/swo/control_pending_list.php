<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('control');

$conn = getDBConnection();

$stmt = $conn->prepare(
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
$stmt->execute();
$result = $stmt->get_result();
$swos = [];
while ($row = $result->fetch_assoc()) {
    $swos[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Pending control list retrieved', $swos);
