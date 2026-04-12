<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('control');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_id   = intval($input['swo_id'] ?? 0);
$comments = sanitizeInput($input['comments'] ?? '');

if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify SWO state
$stmt = $conn->prepare("SELECT id, status FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['status'] !== 'Pending Control Review') {
    $conn->close();
    jsonResponse(false, 'SWO is not pending control review');
}

// Validate ALL items have a control decision before approving
$allItems = getAllItemKeysFromDB($conn);
$totalItems = count($allItems);
if ($totalItems > 0) {
    $placeholders = implode(',', array_fill(0, $totalItems, '?'));
    $checkStmt = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM control_item_reviews
         WHERE swo_id = ? AND item_key IN ($placeholders)
         AND control_decision IS NOT NULL AND control_decision != ''"
    );
    $types = 'i' . str_repeat('s', $totalItems);
    $params = array_merge([$swo_id], $allItems);
    $checkStmt->bind_param($types, ...$params);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if ((int)$checkResult['cnt'] < $totalItems) {
        $conn->close();
        jsonResponse(false, 'All items must have a decision before approving');
    }
}

// Update SWO status to Completed
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'Completed',
     control_reviewer_id = ?, control_reviewed_at = NOW()
     WHERE id = ?"
);
$stmt->bind_param('ii', $user_id, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to approve submission');
}
$stmt->close();

// Record control review decision
$stmt = $conn->prepare(
    "INSERT INTO control_reviews (swo_id, reviewed_by, decision, comments)
     VALUES (?, ?, 'approve', ?)
     ON DUPLICATE KEY UPDATE decision = 'approve', comments = VALUES(comments), created_at = NOW()"
);
$stmt->bind_param('iis', $swo_id, $user_id, $comments);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'CONTROL_APPROVE', 'Pending Control Review', 'Completed');

$conn->close();
jsonResponse(true, 'Submission approved and marked as Completed');
