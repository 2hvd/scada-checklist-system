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
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT s.*,
        uc.username AS created_by_name,
        ua.username AS assigned_to_name,
        uap.username AS approved_by_name
    FROM swo_list s
    LEFT JOIN users uc ON s.created_by = uc.id
    LEFT JOIN users ua ON s.assigned_to = ua.id
    LEFT JOIN users uap ON s.approved_by = uap.id
    WHERE s.id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

// Access control
if ($role === 'user' && $swo['assigned_to'] != $user_id) {
    $conn->close();
    jsonResponse(false, 'Access denied');
}
if ($role === 'support' && $swo['created_by'] != $user_id) {
    $conn->close();
    jsonResponse(false, 'Access denied');
}

// Get checklist status
$csStmt = $conn->prepare("SELECT item_key, status FROM checklist_status WHERE swo_id = ? AND user_id = ?");
$assigned_id = $swo['assigned_to'] ?? $user_id;
$csStmt->bind_param('ii', $swo_id, $assigned_id);
$csStmt->execute();
$csResult = $csStmt->get_result();
$checklistStatus = [];
while ($row = $csResult->fetch_assoc()) {
    $checklistStatus[$row['item_key']] = $row['status'];
}
$csStmt->close();
$conn->close();

jsonResponse(true, 'SWO details retrieved', [
    'swo' => $swo,
    'checklist_status' => $checklistStatus
]);
