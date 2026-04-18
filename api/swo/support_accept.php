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
$stmt = $conn->prepare("SELECT id, status, swo_type_id FROM swo_list WHERE id = ?");
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

// Validate only support-visible leaf items for this SWO have a support decision before accepting
$swo_type_id = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;
$checkSql = "SELECT COUNT(ci.item_key) AS total_items,
                    SUM(CASE WHEN sir.support_decision IS NOT NULL AND sir.support_decision != '' THEN 1 ELSE 0 END) AS decided_items
              FROM checklist_items ci
              LEFT JOIN support_item_reviews sir ON sir.swo_id = ? AND sir.item_key = ci.item_key
              WHERE ci.is_active = 1
                AND ci.is_deleted = 0
                AND COALESCE(ci.visible_support, 1) = 1";
if ($swo_type_id !== null) {
    $checkSql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)
                   AND NOT EXISTS (
                        SELECT 1
                          FROM checklist_items c
                         WHERE c.is_deleted = 0
                           AND c.is_active = 1
                           AND COALESCE(c.visible_support, 1) = 1
                           AND (c.swo_type_id = ? OR c.swo_type_id IS NULL)
                           AND (
                                c.support_parent_item_id = ci.id
                                OR (c.support_parent_item_id IS NULL AND c.parent_item_id = ci.id)
                           )
                   )";
} else {
    $checkSql .= " AND NOT EXISTS (
                        SELECT 1
                          FROM checklist_items c
                         WHERE c.is_deleted = 0
                           AND c.is_active = 1
                           AND COALESCE(c.visible_support, 1) = 1
                           AND (
                                c.support_parent_item_id = ci.id
                                OR (c.support_parent_item_id IS NULL AND c.parent_item_id = ci.id)
                           )
                   )";
}
$checkStmt = $conn->prepare($checkSql);
if ($swo_type_id !== null) {
    // swo_id is bound once; swo_type_id is bound twice
    // (main role-visible filter + NOT EXISTS leaf-child filter).
    $checkStmt->bind_param('iii', $swo_id, $swo_type_id, $swo_type_id);
} else {
    $checkStmt->bind_param('i', $swo_id);
}
$checkStmt->execute();
$checkResult = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();
$totalItems = intval($checkResult['total_items'] ?? 0);
$decidedItems = intval($checkResult['decided_items'] ?? 0);
if ($totalItems > 0 && $decidedItems < $totalItems) {
    $conn->close();
    jsonResponse(false, 'All items must have a decision before accepting');
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
