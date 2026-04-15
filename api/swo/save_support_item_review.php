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
$item_key = trim($input['item_key'] ?? '');

if (!$swo_id || $item_key === '') {
    jsonResponse(false, 'swo_id and item_key are required');
}

$allowed_decisions = ['done', 'na', 'still', 'not_yet', ''];
$support_decision  = isset($input['support_decision']) ? trim($input['support_decision']) : '';
if (!in_array($support_decision, $allowed_decisions, true)) {
    jsonResponse(false, 'Invalid support_decision value');
}
$support_decision = $support_decision === '' ? null : $support_decision;

$support_comment = isset($input['support_comment']) ? trim($input['support_comment']) : '';

$conn       = getDBConnection();
$support_id = $_SESSION['user_id'];

// Verify the SWO exists and was created by this support user
$stmt = $conn->prepare("SELECT id, created_by FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['created_by'] != $support_id) {
    $conn->close();
    jsonResponse(false, 'Access denied');
}

// Upsert: insert or update on duplicate (swo_id, item_key)
$stmt = $conn->prepare(
    "INSERT INTO support_item_reviews (swo_id, item_key, support_decision, support_comment, reviewed_by)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         support_decision = VALUES(support_decision),
         support_comment  = VALUES(support_comment),
         reviewed_by      = VALUES(reviewed_by),
         reviewed_at      = CURRENT_TIMESTAMP"
);
$stmt->bind_param('isssi', $swo_id, $item_key, $support_decision, $support_comment, $support_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to save review');
}
$stmt->close();

// Check if this is a parent item — if so, cascade the decision to all sub-items
$parentChk = $conn->prepare(
    "SELECT ci_child.item_key
       FROM checklist_items ci_parent
       JOIN checklist_items ci_child ON ci_child.parent_item_id = ci_parent.id AND ci_child.is_deleted = 0
      WHERE ci_parent.item_key = ?"
);
$parentChk->bind_param('s', $item_key);
$parentChk->execute();
$subItems = $parentChk->get_result()->fetch_all(MYSQLI_ASSOC);
$parentChk->close();

if (!empty($subItems) && $support_decision !== null && $support_decision !== '') {
    $cascadeStmt = $conn->prepare(
        "INSERT INTO support_item_reviews (swo_id, item_key, support_decision, support_comment, reviewed_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             support_decision = VALUES(support_decision),
             support_comment  = VALUES(support_comment),
             reviewed_by      = VALUES(reviewed_by),
             reviewed_at      = CURRENT_TIMESTAMP"
    );
    $cascadeComment = '';
    foreach ($subItems as $sub) {
        $cascadeStmt->bind_param('isssi', $swo_id, $sub['item_key'], $support_decision, $cascadeComment, $support_id);
        $cascadeStmt->execute();
    }
    $cascadeStmt->close();
}

$conn->close();

jsonResponse(true, 'Review saved');
