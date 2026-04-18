<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

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
$user_id = (int) $_SESSION['user_id'];

// Verify SWO assignment
$stmt = $conn->prepare("SELECT id, status, assigned_to, swo_type_id FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}
if ($swo['assigned_to'] != $user_id) {
    $conn->close();
    jsonResponse(false, 'This SWO is not assigned to you');
}
if ($swo['status'] !== 'In Progress') {
    $conn->close();
    jsonResponse(false, 'Only In Progress SWOs can be submitted');
}
$swo_type_id = $swo['swo_type_id'] !== null ? intval($swo['swo_type_id']) : null;

<<<<<<< HEAD
// Use snapshot if available, otherwise fall back to active items
$snapCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM swo_checklist_items WHERE swo_id = ?");
$snapCheck->bind_param('i', $swo_id);
$snapCheck->execute();
$snapCount = intval($snapCheck->get_result()->fetch_assoc()['cnt']);
$snapCheck->close();

if ($snapCount > 0) {
    // Use snapshot
    $sql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.user_parent_item_id,
                   COALESCE(cs.status, 'empty') AS status
            FROM swo_checklist_items sci
            JOIN checklist_items ci ON ci.id = sci.item_id
            LEFT JOIN checklist_status cs
                   ON cs.item_key = ci.item_key
                  AND cs.swo_id = ?
                  AND cs.user_id = ?
            WHERE sci.swo_id = ?
              AND COALESCE(ci.visible_user, 1) = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $swo_id, $user_id, $swo_id);
} else {
    // Fallback: active items
    $sql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.user_parent_item_id,
                   COALESCE(cs.status, 'empty') AS status
            FROM checklist_items ci
            LEFT JOIN checklist_status cs
                   ON cs.item_key = ci.item_key
                  AND cs.swo_id = ?
                  AND cs.user_id = ?
            WHERE ci.is_active = 1
              AND ci.is_deleted = 0
              AND COALESCE(ci.visible_user, 1) = 1";

    if ($swo_type_id !== null) {
        $sql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iii', $swo_id, $user_id, $swo_type_id);
    } else {
        $sql .= " AND ci.swo_type_id IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $swo_id, $user_id);
    }
=======
// Submit validation must match user checklist rendering:
// only user-visible leaf items can block submission.
$sql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.user_parent_item_id,
               COALESCE(cs.status, 'empty') AS status
        FROM checklist_items ci
        LEFT JOIN checklist_status cs
               ON cs.item_key = ci.item_key
              AND cs.swo_id = ?
              AND cs.user_id = ?
        WHERE ci.is_active = 1
          AND ci.is_deleted = 0
          AND COALESCE(ci.visible_user, 1) = 1";

if ($swo_type_id !== null) {
    $sql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $swo_id, $user_id, $swo_type_id);
} else {
    $sql .= " AND ci.swo_type_id IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $swo_id, $user_id);
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$childrenByParent = [];
foreach ($rows as $r) {
    $effectiveParentId = $r['user_parent_item_id'] !== null
        ? intval($r['user_parent_item_id'])
        : ($r['parent_item_id'] !== null ? intval($r['parent_item_id']) : null);
    if ($effectiveParentId !== null) {
        if (!isset($childrenByParent[$effectiveParentId])) {
            $childrenByParent[$effectiveParentId] = 0;
        }
        $childrenByParent[$effectiveParentId]++;
    }
}

$empty_count = 0;
foreach ($rows as $r) {
    $itemId = intval($r['id']);
    $hasChildren = !empty($childrenByParent[$itemId]);
    if ($hasChildren) {
        continue;
    }
    if (($r['status'] ?? 'empty') === 'empty') {
        $empty_count++;
    }
}

if ($empty_count > 0) {
    $conn->close();
    jsonResponse(false, 'All checklist items must have a status before submitting. ' . $empty_count . ' item(s) still empty.');
}

// Start each review cycle from a clean slate.
$stmt = $conn->prepare("DELETE FROM support_item_reviews WHERE swo_id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to reset support review state');
}
$stmt->close();

$stmt = $conn->prepare("DELETE FROM control_item_reviews WHERE swo_id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to reset control review state');
}
$stmt->close();

// Update SWO status
$stmt = $conn->prepare("UPDATE swo_list SET status = 'Pending Support Review', submitted_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to submit checklist');
}
$stmt->close();

// Log submission history
$notes = $input['notes'] ?? null;
$stmt = $conn->prepare("INSERT INTO submissions_history (user_id, swo_id, action, notes) VALUES (?,?,'submit',?)");
$stmt->bind_param('iis', $user_id, $swo_id, $notes);
$stmt->execute();
$stmt->close();

logAudit($conn, $user_id, $swo_id, 'SUBMIT_CHECKLIST', 'In Progress', 'Pending Support Review');

// Ensure a snapshot exists for this SWO.
// If none exists (e.g. SWO was approved before snapshot system was introduced),
// create one now from the items that actually have statuses recorded.
$snapCheck2 = $conn->prepare("SELECT COUNT(*) as cnt FROM swo_checklist_items WHERE swo_id = ?");
$snapCheck2->bind_param('i', $swo_id);
$snapCheck2->execute();
$existingSnap = intval($snapCheck2->get_result()->fetch_assoc()['cnt']);
$snapCheck2->close();

if ($existingSnap === 0) {
    // Build snapshot from items that have recorded statuses OR are currently active+visible
    if ($swo_type_id !== null) {
        $snapItemStmt = $conn->prepare(
            "SELECT DISTINCT ci.id FROM checklist_items ci
             LEFT JOIN checklist_status cs ON cs.item_key = ci.item_key AND cs.swo_id = ?
             WHERE (cs.id IS NOT NULL OR (ci.is_active = 1 AND ci.is_deleted = 0))
               AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)"
        );
        $snapItemStmt->bind_param('ii', $swo_id, $swo_type_id);
    } else {
        $snapItemStmt = $conn->prepare(
            "SELECT DISTINCT ci.id FROM checklist_items ci
             LEFT JOIN checklist_status cs ON cs.item_key = ci.item_key AND cs.swo_id = ?
             WHERE (cs.id IS NOT NULL OR (ci.is_active = 1 AND ci.is_deleted = 0))
               AND ci.swo_type_id IS NULL"
        );
        $snapItemStmt->bind_param('i', $swo_id);
    }
    $snapItemStmt->execute();
    $snapItems = $snapItemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $snapItemStmt->close();

    if (!empty($snapItems)) {
        $insSnap = $conn->prepare("INSERT IGNORE INTO swo_checklist_items (swo_id, item_id) VALUES (?, ?)");
        foreach ($snapItems as $si) {
            $snapItemId = intval($si['id']);
            $insSnap->bind_param('ii', $swo_id, $snapItemId);
            $insSnap->execute();
        }
        $insSnap->close();
    }
}

$conn->close();
jsonResponse(true, 'Checklist submitted for review');
