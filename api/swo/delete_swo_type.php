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

$swo_type_id = intval($input['swo_type_id'] ?? 0);
if (!$swo_type_id) {
    jsonResponse(false, 'SWO type ID is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify the type exists
$chk = $conn->prepare("SELECT id, name, is_active FROM swo_types WHERE id = ?");
$chk->bind_param('i', $swo_type_id);
$chk->execute();
$type = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$type) {
    $conn->close();
    jsonResponse(false, 'SWO type not found');
}

// Check if this type is used in any SWO
$usageChk = $conn->prepare("SELECT COUNT(*) AS cnt FROM swo_list WHERE swo_type_id = ?");
$usageChk->bind_param('i', $swo_type_id);
$usageChk->execute();
$usage = $usageChk->get_result()->fetch_assoc();
$usageChk->close();
$swo_usage = intval($usage['cnt'] ?? 0);

// Check if any checklist items use this type
$itemChk = $conn->prepare("SELECT COUNT(*) AS cnt FROM checklist_items WHERE swo_type_id = ? AND is_deleted = 0");
$itemChk->bind_param('i', $swo_type_id);
$itemChk->execute();
$itemUsage = $itemChk->get_result()->fetch_assoc();
$itemChk->close();
$item_usage = intval($itemUsage['cnt'] ?? 0);

if ($swo_usage > 0 || $item_usage > 0) {
    // Soft delete - type is in use
    $stmt = $conn->prepare("UPDATE swo_types SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $swo_type_id);
    $stmt->execute();
    $stmt->close();

    logAudit($conn, $user_id, null, "SOFT_DELETE_SWO_TYPE: {$type['name']}", 'active', 'inactive');
    $conn->close();
    jsonResponse(true, "SWO type deactivated (used in {$swo_usage} SWO(s) and {$item_usage} item(s))");
} else {
    // Hard delete - type is not in use
    $stmt = $conn->prepare("DELETE FROM swo_types WHERE id = ?");
    $stmt->bind_param('i', $swo_type_id);
    $stmt->execute();
    $stmt->close();

    logAudit($conn, $user_id, null, "DELETE_SWO_TYPE: {$type['name']}", 'active', 'deleted');
    $conn->close();
    jsonResponse(true, 'SWO type permanently deleted');
}
