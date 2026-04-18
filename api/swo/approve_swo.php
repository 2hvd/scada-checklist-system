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

$swo_id = intval($input['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, status, swo_type_id FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$swo = $result->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['status'] !== 'Pending') {
    $conn->close();
    jsonResponse(false, 'Only Pending SWOs can be approved');
}

$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'Registered', approved_by = ?, approved_at = NOW() WHERE id = ?"
);
$stmt->bind_param('ii', $admin_id, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to approve SWO');
}
$stmt->close();

logAudit($conn, $admin_id, $swo_id, 'APPROVE_SWO', 'Pending', 'Registered');

// Snapshot checklist items for this SWO at approval time
// so future item additions don't affect already-registered SWOs
$swo_type_id = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;

if ($swo_type_id !== null) {
    $itemStmt = $conn->prepare(
        "SELECT id FROM checklist_items
         WHERE is_active = 1 AND is_deleted = 0
           AND (swo_type_id = ? OR swo_type_id IS NULL)"
    );
    $itemStmt->bind_param('i', $swo_type_id);
} else {
    $itemStmt = $conn->prepare(
        "SELECT id FROM checklist_items
         WHERE is_active = 1 AND is_deleted = 0 AND swo_type_id IS NULL"
    );
}
$itemStmt->execute();
$itemRows = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

if (!empty($itemRows)) {
    // Clear any existing snapshot first
    $delSnap = $conn->prepare("DELETE FROM swo_checklist_items WHERE swo_id = ?");
    $delSnap->bind_param('i', $swo_id);
    $delSnap->execute();
    $delSnap->close();

    $insSnap = $conn->prepare("INSERT INTO swo_checklist_items (swo_id, item_id) VALUES (?, ?)");
    foreach ($itemRows as $ir) {
        $item_id = intval($ir['id']);
        $insSnap->bind_param('ii', $swo_id, $item_id);
        $insSnap->execute();
    }
    $insSnap->close();
}

$conn->close();
jsonResponse(true, 'SWO approved successfully');
