<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$conn = getDBConnection();

// SWO status summary
$statusResult = $conn->query(
    "SELECT status, COUNT(*) as count FROM swo_list GROUP BY status"
);
$statusCounts = [];
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = intval($row['count']);
}

// Per-user stats (only 'user' role)
$userStmt = $conn->prepare(
    "SELECT u.id, u.username,
        COUNT(DISTINCT s.id) AS total_assigned,
        SUM(CASE WHEN s.status = 'Completed' OR s.status = 'Closed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN s.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN s.status IN ('Pending Support Review', 'Submitted') THEN 1 ELSE 0 END) AS pending_review
    FROM users u
    LEFT JOIN swo_list s ON s.assigned_to = u.id
    WHERE u.role = 'user' AND u.active = 1
    GROUP BY u.id, u.username"
);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userStats = [];
$leafKeysByTypeCache = [];

while ($user = $userResult->fetch_assoc()) {
    $userTotalItems = 0;
    $userCompletedItems = 0;

    $swoStmt = $conn->prepare(
        "SELECT id, swo_type_id
         FROM swo_list
         WHERE assigned_to = ? AND status NOT IN ('Draft','Pending','Registered')"
    );
    $swoStmt->bind_param('i', $user['id']);
    $swoStmt->execute();
    $assignedSwos = $swoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $swoStmt->close();

    foreach ($assignedSwos as $swoRow) {
        $swoId = intval($swoRow['id']);
        $swoTypeId = !empty($swoRow['swo_type_id']) ? intval($swoRow['swo_type_id']) : null;
        $cacheKey = $swoTypeId !== null ? strval($swoTypeId) : 'null';
        if (!isset($leafKeysByTypeCache[$cacheKey])) {
            $itemSql = "SELECT id, item_key, parent_item_id, user_parent_item_id, visible_user
                        FROM checklist_items
                        WHERE is_active = 1 AND is_deleted = 0";
            if ($swoTypeId !== null) {
                $itemSql .= " AND (swo_type_id = ? OR swo_type_id IS NULL)";
                $itemStmt = $conn->prepare($itemSql);
                $itemStmt->bind_param('i', $swoTypeId);
            } else {
                $itemStmt = $conn->prepare($itemSql);
            }
            $itemStmt->execute();
            $rawItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $itemStmt->close();

            $visibleItems = [];
            foreach ($rawItems as $item) {
                if (intval($item['visible_user'] ?? 1) !== 1) {
                    continue;
                }
                $item['effective_parent_item_id'] = $item['user_parent_item_id'] !== null
                    ? intval($item['user_parent_item_id'])
                    : ($item['parent_item_id'] !== null ? intval($item['parent_item_id']) : null);
                $visibleItems[] = $item;
            }

            $hasChildren = [];
            foreach ($visibleItems as $item) {
                if ($item['effective_parent_item_id'] !== null) {
                    $hasChildren[intval($item['effective_parent_item_id'])] = true;
                }
            }

            $leafKeysByTypeCache[$cacheKey] = [];
            foreach ($visibleItems as $item) {
                $itemId = intval($item['id']);
                if (!isset($hasChildren[$itemId])) {
                    $leafKeysByTypeCache[$cacheKey][$item['item_key']] = true;
                }
            }
        }

        $leafKeys = $leafKeysByTypeCache[$cacheKey];

        if (empty($leafKeys)) {
            continue;
        }

        $userTotalItems += count($leafKeys);

        $statusStmt = $conn->prepare(
            "SELECT item_key, status
             FROM checklist_status
             WHERE swo_id = ? AND user_id = ?"
        );
        $statusStmt->bind_param('ii', $swoId, $user['id']);
        $statusStmt->execute();
        $statusRows = $statusStmt->get_result();
        while ($status = $statusRows->fetch_assoc()) {
            if (!isset($leafKeys[$status['item_key']])) {
                continue;
            }
            if ($status['status'] === 'done' || $status['status'] === 'na') {
                $userCompletedItems++;
            }
        }
        $statusStmt->close();
    }

    $user['total_items'] = $userTotalItems;
    $user['completed_items'] = $userCompletedItems;
    $user['submitted'] = intval($user['pending_review']);
    $user['completion_pct'] = $user['total_items'] > 0
        ? round($user['completed_items'] / $user['total_items'] * 100, 1)
        : 0;
    $userStats[] = $user;
}
$userStmt->close();

// Recent audit log
$auditResult = $conn->query(
    "SELECT al.*, u.username, s.swo_number
     FROM audit_log al
     LEFT JOIN users u ON al.user_id = u.id
     LEFT JOIN swo_list s ON al.swo_id = s.id
     ORDER BY al.timestamp DESC LIMIT 20"
);
$recentActivity = [];
while ($row = $auditResult->fetch_assoc()) {
    $recentActivity[] = $row;
}

$conn->close();
jsonResponse(true, 'Admin stats retrieved', [
    'status_counts'   => $statusCounts,
    'user_stats'      => $userStats,
    'recent_activity' => $recentActivity,
]);
