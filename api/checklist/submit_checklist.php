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
$stmt = $conn->prepare("SELECT id, status, assigned_to, swo_type_id FROM swo_list WHERE id = ?");
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
$swo_type_id = $swo['swo_type_id'] !== null ? intval($swo['swo_type_id']) : null;

// Submit validation must follow checklist visibility rules by SWO type
// so hidden items from other types do not block submission.
$sql = "SELECT COUNT(*) AS empty_count
        FROM checklist_items ci
        LEFT JOIN checklist_status cs
               ON cs.item_key = ci.item_key
              AND cs.swo_id = ?
              AND cs.user_id = ?
        WHERE ci.is_active = 1
          AND ci.is_deleted = 0
          AND COALESCE(cs.status, 'empty') = 'empty'";

if ($swo_type_id !== null) {
    $sql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $swo_id, $user_id, $swo_type_id);
} else {
    $sql .= " AND ci.swo_type_id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $swo_id, $user_id);
}

$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row['empty_count'] > 0) {
    $conn->close();
    jsonResponse(false, 'All checklist items must have a status before submitting. ' . $row['empty_count'] . ' item(s) still empty.');
}

// Update SWO status
$stmt = $conn->prepare("UPDATE swo_list SET status = 'Pending Support Review', submitted_at = NOW() WHERE id = ?");
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

logAudit($conn, $user_id, $swo_id, 'SUBMIT_CHECKLIST', 'In Progress', 'Pending Support Review');

$conn->close();
jsonResponse(true, 'Checklist submitted for review');
