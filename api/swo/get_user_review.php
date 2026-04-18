<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('user');

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Load SWO details — must be assigned to this user
$stmt = $conn->prepare(
    "SELECT id, swo_number, station_name, swo_type, swo_type_id, kcor, status,
            submitted_at, rejection_reason
     FROM swo_list
     WHERE id = ? AND assigned_to = ?"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found or not assigned to you');
}

// Load checklist items in SWO type context with hierarchy
$swo_type_id = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;
$itemSql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id,
                   ci.visible_user, ci.user_parent_item_id
               FROM checklist_items ci
              WHERE ci.is_deleted = 0
                AND ci.is_active = 1";
if ($swo_type_id !== null) {
    $itemSql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
}
// Keep parent items first, then children grouped by parent and ordered by section number.
$itemSql .= " ORDER BY ci.section, CASE WHEN ci.parent_item_id IS NULL THEN 0 ELSE 1 END, ci.parent_item_id, ci.section_number";

if ($swo_type_id !== null) {
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param('i', $swo_type_id);
} else {
    $itemStmt = $conn->prepare($itemSql);
}
$itemStmt->execute();
$itemsRes = $itemStmt->get_result();
$itemRows = [];
while ($row = $itemsRes->fetch_assoc()) {
    if (intval($row['visible_user'] ?? 1) !== 1) {
        continue;
    }
    $row['effective_parent_item_id'] = $row['user_parent_item_id'] !== null
        ? intval($row['user_parent_item_id'])
        : ($row['parent_item_id'] !== null ? intval($row['parent_item_id']) : null);
    $itemRows[] = $row;
}
$itemStmt->close();

// Load user's checklist statuses
$stmt = $conn->prepare(
    "SELECT item_key, status FROM checklist_status WHERE swo_id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userStatuses = [];
while ($row = $result->fetch_assoc()) {
    $userStatuses[$row['item_key']] = $row['status'];
}
$stmt->close();

// Load existing user item comments
$stmt = $conn->prepare(
    "SELECT item_key, comment FROM user_item_comments WHERE swo_id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userComments = [];
while ($row = $result->fetch_assoc()) {
    $userComments[$row['item_key']] = $row['comment'] ?? '';
}
$stmt->close();

$conn->close();

// Build structured sections
$sections  = [];
$totalItems = 0;
$doneCount = $naCount = $stillCount = $notYetCount = $emptyCount = 0;

$sectionLabels = [
    'during_config' => 'During Configuration',
    'during_commissioning' => 'During Commissioning',
    'after_commissioning' => 'After Commissioning',
];
$bySection = [];
foreach ($itemRows as $row) {
    if (!isset($bySection[$row['section']])) {
        $bySection[$row['section']] = [];
    }
    $bySection[$row['section']][] = $row;
}
foreach ($bySection as $secKey => $rows) {
    $sectionData = ['label' => $sectionLabels[$secKey] ?? ucwords(str_replace('_', ' ', $secKey)), 'items' => []];

    $parents = [];
    $children = [];
    foreach ($rows as $row) {
        if ($row['effective_parent_item_id'] === null) {
            $parents[] = $row;
        } else {
            $children[$row['effective_parent_item_id']][] = $row;
        }
    }
    $parentIdSet = [];
    foreach ($parents as $p) {
        $parentIdSet[intval($p['id'])] = true;
    }

    foreach ($parents as $parent) {
        $parentStatus = $userStatuses[$parent['item_key']] ?? 'empty';
        $sectionData['items'][] = [
            'key' => $parent['item_key'],
            'label' => $parent['description'],
            'status' => $parentStatus,
            'comment' => $userComments[$parent['item_key']] ?? '',
            'is_parent' => isset($children[$parent['id']]),
            'parent_item_id' => null,
            'item_id' => intval($parent['id']),
        ];

        if (!isset($children[$parent['id']])) {
            $totalItems++;
            switch ($parentStatus) {
                case 'done':    $doneCount++;    break;
                case 'na':      $naCount++;      break;
                case 'still':   $stillCount++;   break;
                case 'not_yet': $notYetCount++;  break;
                default:        $emptyCount++;   break;
            }
        }

        foreach ($children[$parent['id']] ?? [] as $child) {
            $childStatus = $userStatuses[$child['item_key']] ?? 'empty';
            $sectionData['items'][] = [
                'key' => $child['item_key'],
                'label' => $child['description'],
                'status' => $childStatus,
                'comment' => $userComments[$child['item_key']] ?? '',
                'is_parent' => false,
                'parent_item_id' => intval($parent['id']),
                'parent_key' => $parent['item_key'],
                'item_id' => intval($child['id']),
            ];
            $totalItems++;
            switch ($childStatus) {
                case 'done':    $doneCount++;    break;
                case 'na':      $naCount++;      break;
                case 'still':   $stillCount++;   break;
                case 'not_yet': $notYetCount++;  break;
                default:        $emptyCount++;   break;
            }
        }
    }

    // Keep orphaned items visible when their role-specific parent exists in DB
    // but is not visible to the current role in this response.
    foreach ($rows as $row) {
        $parentId = $row['effective_parent_item_id'];
        if ($parentId === null || isset($parentIdSet[intval($parentId)])) {
            continue;
        }
        $st = $userStatuses[$row['item_key']] ?? 'empty';
        $sectionData['items'][] = [
            'key' => $row['item_key'],
            'label' => $row['description'],
            'status' => $st,
            'comment' => $userComments[$row['item_key']] ?? '',
            'is_parent' => false,
            'parent_item_id' => intval($parentId),
            'item_id' => intval($row['id']),
        ];
        $totalItems++;
        switch ($st) {
            case 'done':    $doneCount++;    break;
            case 'na':      $naCount++;      break;
            case 'still':   $stillCount++;   break;
            case 'not_yet': $notYetCount++;  break;
            default:        $emptyCount++;   break;
        }
    }

    $sections[] = $sectionData;
}

$completed = $doneCount + $naCount;
$progress  = $totalItems > 0 ? round($completed / $totalItems * 100, 1) : 0;

jsonResponse(true, 'User review data retrieved', [
    'swo'      => $swo,
    'sections' => $sections,
    'progress' => $progress,
    'counts'   => [
        'done'    => $doneCount,
        'na'      => $naCount,
        'still'   => $stillCount,
        'not_yet' => $notYetCount,
        'empty'   => $emptyCount,
        'total'   => $totalItems,
    ],
]);
