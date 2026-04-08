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

// Verify SWO assignment
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
    jsonResponse(false, 'Only In Progress SWOs can be submitted');
}

// Verify all items have non-empty status
$stmt = $conn->prepare(
    "SELECT COUNT(*) as empty_count FROM checklist_status 
     WHERE swo_id = ? AND user_id = ? AND status = 'empty'"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row['empty_count'] > 0) {
    $conn->close();
    jsonResponse(false, 'All checklist items must have a status before submitting. ' . $row['empty_count'] . ' item(s) still empty.');
}

// Update SWO status
$stmt = $conn->prepare("UPDATE swo_list SET status = 'Submitted', submitted_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to submit checklist');
}
$stmt->close();

// Log submission history
$notes = $input['notes'] ?? null;
$stmt = $conn->prepare("INSERT INTO submissions_history (user_id, swo_id, action, notes) VALUES (?,?,'submit',?)");
$stmt->bind_param('iis', $user_id, $swo_id, $notes);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'SUBMIT_CHECKLIST', 'In Progress', 'Submitted');

$conn->close();
jsonResponse(true, 'Checklist submitted for review');
