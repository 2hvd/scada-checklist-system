<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id   = intval($input['swo_id'] ?? 0);
$item_key = sanitizeInput($input['item_key'] ?? '');
$status   = sanitizeInput($input['status'] ?? '');

if (!$swo_id || empty($item_key) || empty($status)) {
    jsonResponse(false, 'SWO ID, item key, and status are required');
}

$validStatuses = ['empty', 'done', 'na', 'not_yet', 'still'];
if (!in_array($status, $validStatuses)) {
    jsonResponse(false, 'Invalid status value');
}

$validKeys = getAllItemKeys();
if (!in_array($item_key, $validKeys)) {
    jsonResponse(false, 'Invalid item key');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify SWO is assigned to this user and In Progress
$stmt = $conn->prepare("SELECT id, status, assigned_to FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}
if ($swo['assigned_to'] != $user_id) {
    $conn->close();
    jsonResponse(false, 'This SWO is not assigned to you');
}
if ($swo['status'] !== 'In Progress') {
    $conn->close();
    jsonResponse(false, 'Checklist can only be updated when SWO is In Progress');
}

$stmt = $conn->prepare(
    "INSERT INTO checklist_status (user_id, swo_id, item_key, status) VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP"
);
$stmt->bind_param('iiss', $user_id, $swo_id, $item_key, $status);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to update status');
}
$stmt->close();

logAudit($conn, $user_id, $swo_id, "UPDATE_CHECKLIST:$item_key", null, $status);

$conn->close();
jsonResponse(true, 'Status updated successfully');
