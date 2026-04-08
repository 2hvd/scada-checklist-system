<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id  = intval($input['swo_id'] ?? 0);
$user_id = intval($input['user_id'] ?? 0);

if (!$swo_id || !$user_id) {
    jsonResponse(false, 'SWO ID and user ID are required');
}

$conn = getDBConnection();

// Verify SWO status
$stmt = $conn->prepare("SELECT id, status FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}
if ($swo['status'] !== 'Registered') {
    $conn->close();
    jsonResponse(false, 'Only Registered SWOs can be assigned');
}

// Verify user is a 'user' role
$stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND active = 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$assignee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignee || $assignee['role'] !== 'user') {
    $conn->close();
    jsonResponse(false, 'Invalid user to assign');
}

$admin_id = $_SESSION['user_id'];

// Update SWO
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'In Progress', assigned_to = ?, assigned_at = NOW() WHERE id = ?"
);
$stmt->bind_param('ii', $user_id, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to assign SWO');
}
$stmt->close();

// Initialize checklist items for this user/SWO (use DB-based active items)
$itemKeys = getAllItemKeysFromDB($conn);
$insertStmt = $conn->prepare(
    "INSERT IGNORE INTO checklist_status (user_id, swo_id, item_key, status) VALUES (?,?,?,'empty')"
);
foreach ($itemKeys as $key) {
    $insertStmt->bind_param('iis', $user_id, $swo_id, $key);
    $insertStmt->execute();
}
$insertStmt->close();

logAudit($conn, $admin_id, $swo_id, 'ASSIGN_SWO', 'Registered', 'In Progress');

$conn->close();
jsonResponse(true, 'SWO assigned successfully');
