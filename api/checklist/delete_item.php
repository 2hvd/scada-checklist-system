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

$item_id = intval($input['item_id'] ?? 0);
if (!$item_id) {
    jsonResponse(false, 'Item ID is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify item exists and is not already deleted
$chk = $conn->prepare("SELECT id, item_key FROM checklist_items WHERE id = ? AND is_deleted = 0");
$chk->bind_param('i', $item_id);
$chk->execute();
$item = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$item) {
    $conn->close();
    jsonResponse(false, 'Checklist item not found');
}

// Check if this item has been used in any checklist_status records
$usageChk = $conn->prepare("SELECT COUNT(DISTINCT swo_id) AS cnt FROM checklist_status WHERE item_key = ?");
$usageChk->bind_param('s', $item['item_key']);
$usageChk->execute();
$usage = $usageChk->get_result()->fetch_assoc();
$usageChk->close();

$usage_count = intval($usage['cnt'] ?? 0);

if ($usage_count > 0) {
    // Soft delete — item has been used in SWOs
    $stmt = $conn->prepare("UPDATE checklist_items SET is_deleted = 1, is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->close();
    $message = "Item soft-deleted (used in $usage_count SWO(s) — data preserved)";
} else {
    // Permanently delete — never used
    $stmt = $conn->prepare("DELETE FROM checklist_items WHERE id = ?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Item permanently deleted';
}

logAudit($conn, $user_id, null, "DELETE_CHECKLIST_ITEM: {$item['item_key']}", 'active', 'deleted');

$conn->close();
jsonResponse(true, $message);
