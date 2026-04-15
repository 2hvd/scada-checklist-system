<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get assigned SWOs
$stmt = $conn->prepare(
    "SELECT s.*, uc.username AS created_by_name
     FROM swo_list s
     LEFT JOIN users uc ON s.created_by = uc.id
     WHERE s.assigned_to = ?
     ORDER BY s.assigned_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$swos = [];

while ($swo = $result->fetch_assoc()) {
    // Get applicable checklist items (count only effective/leaf items)
    $swoTypeId = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;
    if ($swoTypeId !== null) {
        $itemStmt = $conn->prepare(
            "SELECT id, item_key, parent_item_id
             FROM checklist_items
             WHERE is_active = 1 AND is_deleted = 0
               AND (swo_type_id = ? OR swo_type_id IS NULL)"
        );
        $itemStmt->bind_param('i', $swoTypeId);
    } else {
        $itemStmt = $conn->prepare(
            "SELECT id, item_key, parent_item_id
             FROM checklist_items
             WHERE is_active = 1 AND is_deleted = 0"
        );
    }
    $itemStmt->execute();
    $itemRows = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    $hasChildren = [];
    foreach ($itemRows as $row) {
        if ($row['parent_item_id'] !== null) {
            $hasChildren[intval($row['parent_item_id'])] = true;
        }
    }

    $leafKeys = [];
    foreach ($itemRows as $row) {
        $itemId = intval($row['id']);
        if (!isset($hasChildren[$itemId])) {
            $leafKeys[$row['item_key']] = true;
        }
    }

    $counts = ['done' => 0, 'na' => 0, 'not_yet' => 0, 'still' => 0, 'empty' => 0];
    $total = count($leafKeys);

    if ($total > 0) {
        $filledCount = 0;
        $csStmt = $conn->prepare(
            "SELECT item_key, status
             FROM checklist_status
             WHERE swo_id = ? AND user_id = ?"
        );
        $csStmt->bind_param('ii', $swo['id'], $user_id);
        $csStmt->execute();
        $csResult = $csStmt->get_result();
        while ($row = $csResult->fetch_assoc()) {
            $itemKey = $row['item_key'];
            $status = $row['status'];
            if (!isset($leafKeys[$itemKey])) {
                continue;
            }
            if ($status === 'done' || $status === 'na' || $status === 'not_yet' || $status === 'still') {
                $counts[$status]++;
                $filledCount++;
            }
        }
        $csStmt->close();
        $counts['empty'] = max(0, $total - $filledCount);
    }

    $completed = $counts['done'] + $counts['na'];
    $progress = $total > 0 ? round($completed / $total * 100, 1) : 0;

    $swo['checklist_counts'] = $counts;
    $swo['progress'] = $progress;
    $swo['total_items'] = $total;
    $swos[] = $swo;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'User data retrieved', ['swos' => $swos]);
