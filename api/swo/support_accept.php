<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

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

if (!in_array($swo['status'], ['Pending Support Review', 'Returned from Control'])) {
    $conn->close();
    jsonResponse(false, 'SWO is not pending support review');
}

// Validate ALL items have a support decision before accepting
$allItems = getAllItemKeysFromDB($conn);
$totalItems = count($allItems);
if ($totalItems > 0) {
    $placeholders = implode(',', array_fill(0, $totalItems, '?'));
    $checkStmt = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM support_item_reviews
         WHERE swo_id = ? AND item_key IN ($placeholders)
         AND support_decision IS NOT NULL AND support_decision != ''"
    );
    $types = 'i' . str_repeat('s', $totalItems);
    $params = array_merge([$swo_id], $allItems);
    $checkStmt->bind_param($types, ...$params);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if ((int)$checkResult['cnt'] < $totalItems) {
        $conn->close();
        jsonResponse(false, 'All items must have a decision before accepting');
    }
}

// Update SWO status to Pending Control Review
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'Pending Control Review',
     support_reviewer_id = ?, support_reviewed_at = NOW()
     WHERE id = ?"
);
$stmt->bind_param('ii', $user_id, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to accept submission');
}
$stmt->close();

// Record support review decision
$stmt = $conn->prepare(
    "INSERT INTO support_reviews (swo_id, reviewed_by, decision, comments)
     VALUES (?, ?, 'accept', ?)
     ON DUPLICATE KEY UPDATE decision = 'accept', comments = VALUES(comments), created_at = NOW()"
);
$stmt->bind_param('iis', $swo_id, $user_id, $comments);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'SUPPORT_ACCEPT', $swo['status'], 'Pending Control Review');

$conn->close();
jsonResponse(true, 'Submission accepted and forwarded to Control');
