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

$swo_id = intval($input['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

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
if (!in_array($swo['status'], ['Pending Support Review', 'Submitted'])) {
    $conn->close();
    jsonResponse(false, 'Only checklists in Pending Support Review or Submitted status can be withdrawn');
}

$stmt = $conn->prepare("UPDATE swo_list SET status = 'In Progress', submitted_at = NULL WHERE id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to withdraw checklist');
}
$stmt->close();

$notes = $input['notes'] ?? null;
$stmt = $conn->prepare("INSERT INTO submissions_history (user_id, swo_id, action, notes) VALUES (?,?,'withdraw',?)");
$stmt->bind_param('iis', $user_id, $swo_id, $notes);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'WITHDRAW_CHECKLIST', $swo['status'], 'In Progress');

$conn->close();
jsonResponse(true, 'Checklist withdrawn successfully');
