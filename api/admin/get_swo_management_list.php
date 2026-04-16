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
    u_created.username AS created_by_name,
    u_assigned.username AS assigned_to,
    u_support.username AS support_reviewer_name,
    u_control.username AS control_reviewer_name
FROM swo_list s
LEFT JOIN users u_created ON s.created_by = u_created.id
LEFT JOIN users u_assigned ON s.assigned_to = u_assigned.id
LEFT JOIN users u_support ON s.support_reviewer_id = u_support.id
LEFT JOIN users u_control ON s.control_reviewer_id = u_control.id";

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
