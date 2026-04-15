<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$item_id = intval($_GET['item_id'] ?? 0);
if (!$item_id) {
    jsonResponse(false, 'Item ID is required');
}

$conn = getDBConnection();

// Get the item_key for this item
$chk = $conn->prepare("SELECT item_key FROM checklist_items WHERE id = ?");
$chk->bind_param('i', $item_id);
$chk->execute();
$item = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$item) {
    $conn->close();
    jsonResponse(false, 'Checklist item not found');
}

// Count distinct SWOs that have used this item
$stmt = $conn->prepare("SELECT COUNT(DISTINCT swo_id) AS count FROM checklist_status WHERE item_key = ?");
$stmt->bind_param('s', $item['item_key']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$count = intval($result['count'] ?? 0);

// Get the SWO IDs if there are any
$swo_ids = [];
if ($count > 0) {
    $stmt = $conn->prepare("SELECT DISTINCT swo_id FROM checklist_status WHERE item_key = ? ORDER BY swo_id");
    $stmt->bind_param('s', $item['item_key']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $swo_ids[] = intval($row['swo_id']);
    }
    $stmt->close();
}

$conn->close();
jsonResponse(true, 'Usage data retrieved', [
    'count'   => $count,
    'swo_ids' => $swo_ids,
]);
