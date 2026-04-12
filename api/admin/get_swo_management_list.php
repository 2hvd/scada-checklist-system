<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$conn = getDBConnection();
$status = $_GET['status'] ?? '';

$sql = "SELECT 
    s.id,
    s.swo_number,
    s.station_name,
    s.swo_type,
    s.kcor,
    s.status,
    s.created_at,
    s.assigned_at,
    s.submitted_at,
    s.support_reviewed_at,
    s.control_reviewed_at,
    u_assigned.username AS assigned_to
FROM swo_list s
LEFT JOIN users u_assigned ON s.assigned_to = u_assigned.id";

if ($status) {
    $sql .= " WHERE s.status = ?";
    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $status);
} else {
    $sql .= " ORDER BY s.created_at DESC";
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
$swos = [];
while ($row = $result->fetch_assoc()) {
    $swos[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'SWO management list retrieved', $swos);
