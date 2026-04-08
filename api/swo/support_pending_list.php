<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.kcor, s.status,
            s.submitted_at, s.rejection_reason,
            ua.username AS assigned_to_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     WHERE s.status IN ('Pending Support Review', 'Returned from Control')
     ORDER BY s.submitted_at DESC"
);
$stmt->execute();
$result = $stmt->get_result();
$swos = [];
while ($row = $result->fetch_assoc()) {
    $swos[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'Pending support list retrieved', $swos);
