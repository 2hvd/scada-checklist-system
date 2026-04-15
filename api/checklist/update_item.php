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

$item_id     = intval($input['item_id'] ?? 0);
$description = trim($input['description'] ?? '');

if (!$item_id) {
    jsonResponse(false, 'Item ID is required');
}
if ($description === '') {
    jsonResponse(false, 'Description is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify item exists and is not deleted
$chk = $conn->prepare("SELECT id, item_key FROM checklist_items WHERE id = ? AND is_deleted = 0");
$chk->bind_param('i', $item_id);
$chk->execute();
$item = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$item) {
    $conn->close();
    jsonResponse(false, 'Checklist item not found');
}

// Check if this item is used in any SWOs — block editing if so
$usageChk = $conn->prepare("SELECT COUNT(DISTINCT swo_id) AS count FROM checklist_status WHERE item_key = ?");
$usageChk->bind_param('s', $item['item_key']);
$usageChk->execute();
$usage = $usageChk->get_result()->fetch_assoc();
$usageChk->close();

$usage_count = intval($usage['count'] ?? 0);
if ($usage_count > 0) {
    $conn->close();
    jsonResponse(false, "Cannot edit item — it is used in $usage_count SWO(s). Create a new item instead.");
}

$stmt = $conn->prepare("UPDATE checklist_items SET description = ? WHERE id = ?");
$stmt->bind_param('si', $description, $item_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to update checklist item');
}
$stmt->close();

logAudit($conn, $user_id, null, "UPDATE_CHECKLIST_ITEM: {$item['item_key']}", null, null);

$conn->close();
jsonResponse(true, 'Checklist item updated successfully');
