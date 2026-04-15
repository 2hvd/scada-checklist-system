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
$item_key = sanitizeInput($input['item_key'] ?? '');
$decision = sanitizeInput($input['control_decision'] ?? '');
$comment  = sanitizeInput($input['control_comment'] ?? '');

if (!$swo_id || !$item_key) {
    jsonResponse(false, 'swo_id and item_key are required');
}

$allowed_decisions = ['done', 'na', 'still', 'not_yet', 'empty', ''];
if (!in_array($decision, $allowed_decisions, true)) {
    jsonResponse(false, 'Invalid decision value');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify SWO is in a reviewable state
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

// Upsert control item review
$stmt = $conn->prepare(
    "INSERT INTO control_item_reviews (swo_id, item_key, control_decision, control_comment, reviewed_by)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         control_decision = VALUES(control_decision),
         control_comment  = VALUES(control_comment),
         reviewed_by      = VALUES(reviewed_by),
         reviewed_at      = CURRENT_TIMESTAMP"
);
$stmt->bind_param('isssi', $swo_id, $item_key, $decision, $comment, $user_id);
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

if (!empty($subItems) && $decision !== '' && $decision !== null) {
    $cascadeStmt = $conn->prepare(
        "INSERT INTO control_item_reviews (swo_id, item_key, control_decision, control_comment, reviewed_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             control_decision = VALUES(control_decision),
             control_comment  = VALUES(control_comment),
             reviewed_by      = VALUES(reviewed_by),
             reviewed_at      = CURRENT_TIMESTAMP"
    );
    $cascadeComment = "Auto-set from parent: {$item_key}";
    foreach ($subItems as $sub) {
        $cascadeStmt->bind_param('isssi', $swo_id, $sub['item_key'], $decision, $cascadeComment, $user_id);
        $cascadeStmt->execute();
    }
    $cascadeStmt->close();
}

$conn->close();

jsonResponse(true, 'Review saved');
