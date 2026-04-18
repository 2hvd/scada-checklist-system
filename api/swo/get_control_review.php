<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('control');

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Load SWO details
$stmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.swo_type_id, s.kcor, s.status,
            s.submitted_at, s.support_reviewed_at,
            s.assigned_to,
            ua.username AS assigned_to_name,
            us.username AS support_reviewer_name
      FROM swo_list s
      LEFT JOIN users ua ON s.assigned_to      = ua.id
      LEFT JOIN users us ON s.support_reviewer_id = us.id
     WHERE s.id = ?
       AND (
           s.status = 'Pending Control Review'
           OR (s.status = 'Completed' AND s.control_reviewer_id = ?)
       )"
);
$stmt->bind_param('ii', $swo_id, $user_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found or not in a reviewable state');
}

$swo_type_id = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;
$itemSql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id,
                   ci.visible_control, ci.control_parent_item_id, ci.visible_user
                FROM checklist_items ci
               WHERE ci.is_deleted = 0
                 AND ci.is_active = 1";
if ($swo_type_id !== null) {
    $itemSql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
}
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
    if (intval($row['visible_control'] ?? 1) !== 1) {
        continue;
    }
    $row['effective_parent_item_id'] = $row['control_parent_item_id'] !== null
        ? intval($row['control_parent_item_id'])
        : ($row['parent_item_id'] !== null ? intval($row['parent_item_id']) : null);
    $itemRows[] = $row;
}
$itemStmt->close();

$stmt = $conn->prepare(
    "SELECT item_key, status FROM checklist_status WHERE swo_id = ? AND user_id = ?"
);
$assignedUserId = intval($swo['assigned_to'] ?? 0);
$stmt->bind_param('ii', $swo_id, $assignedUserId);
$stmt->execute();
$result = $stmt->get_result();
$userStatuses = [];
while ($row = $result->fetch_assoc()) {
    $userStatuses[$row['item_key']] = $row['status'];
}
$stmt->close();

// Load support item reviews (read-only for control)
$stmt = $conn->prepare(
    "SELECT item_key, support_decision, support_comment
     FROM support_item_reviews WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$supportReviews = [];
while ($row = $result->fetch_assoc()) {
    $supportReviews[$row['item_key']] = [
        'support_decision' => $row['support_decision'] ?? '',
        'support_comment'  => $row['support_comment']  ?? '',
    ];
}
$stmt->close();

// Load user item comments (read-only for control)
$stmt = $conn->prepare(
    "SELECT item_key, comment FROM user_item_comments WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$userComments = [];
while ($row = $result->fetch_assoc()) {
    $userComments[$row['item_key']] = $row['comment'] ?? '';
}
$stmt->close();

// Load existing control item reviews
$stmt = $conn->prepare(
    "SELECT item_key, control_decision, control_comment
     FROM control_item_reviews WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$controlReviews = [];
while ($row = $result->fetch_assoc()) {
    $controlReviews[$row['item_key']] = [
        'decision' => $row['control_decision'] ?? '',
        'comment'  => $row['control_comment']  ?? '',
    ];
}
$stmt->close();

// Build structured sections
$sections = [];
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
        $parentKey = $parent['item_key'];
        $parentUserStatusHidden = intval($parent['visible_user'] ?? 1) !== 1;
        $parentStatus = $parentUserStatusHidden ? 'empty' : ($userStatuses[$parentKey] ?? 'empty');
        $supportReview = $supportReviews[$parentKey] ?? ['support_decision' => '', 'support_comment' => ''];
        $controlReview = $controlReviews[$parentKey] ?? ['decision' => '', 'comment' => ''];
        $childRows = $children[$parent['id']] ?? [];
        $hasChildren = !empty($childRows);

        $childTotal = 0;
        $childCompleted = 0;
        foreach ($childRows as $child) {
            $childTotal++;
            if (intval($child['visible_user'] ?? 1) !== 1) {
                continue;
            }
            $childStatus = $userStatuses[$child['item_key']] ?? 'empty';
            if ($childStatus === 'done' || $childStatus === 'na') {
                $childCompleted++;
            }
        }
        $childCompletionPct = $childTotal > 0 ? round(($childCompleted / $childTotal) * 100, 1) : null;

        $sectionData['items'][] = [
            'key'              => $parentKey,
            'label'            => $parent['description'],
            'status'           => $parentStatus,
            'user_comment'     => $userComments[$parentKey] ?? '',
            'support_decision' => $supportReview['support_decision'],
            'support_comment'  => $supportReview['support_comment'],
            'decision'         => $controlReview['decision'],
            'comment'          => $controlReview['comment'],
            'is_parent'        => $hasChildren,
            'user_status_hidden' => $parentUserStatusHidden,
            'parent_item_id'   => null,
            'item_id'          => intval($parent['id']),
            'child_total_count' => $childTotal,
            'child_completed_count' => $childCompleted,
            'child_completion_pct' => $childCompletionPct,
        ];

        if (!$hasChildren && !$parentUserStatusHidden) {
            $totalItems++;
            switch ($parentStatus) {
                case 'done':    $doneCount++;    break;
                case 'na':      $naCount++;      break;
                case 'still':   $stillCount++;   break;
                case 'not_yet': $notYetCount++;  break;
                default:        $emptyCount++;   break;
            }
        }

        foreach ($childRows as $child) {
            $childKey = $child['item_key'];
            $childStatusHidden = intval($child['visible_user'] ?? 1) !== 1;
            $childStatus = $childStatusHidden ? 'empty' : ($userStatuses[$childKey] ?? 'empty');
            $childSupportReview = $supportReviews[$childKey] ?? ['support_decision' => '', 'support_comment' => ''];
            $childControlReview = $controlReviews[$childKey] ?? ['decision' => '', 'comment' => ''];

            $sectionData['items'][] = [
                'key'              => $childKey,
                'label'            => $child['description'],
                'status'           => $childStatus,
                'user_comment'     => $userComments[$childKey] ?? '',
                'support_decision' => $childSupportReview['support_decision'],
                'support_comment'  => $childSupportReview['support_comment'],
                'decision'         => $childControlReview['decision'],
                'comment'          => $childControlReview['comment'],
                'is_parent'        => false,
                'user_status_hidden' => $childStatusHidden,
                'parent_item_id'   => intval($parent['id']),
                'parent_key'       => $parentKey,
                'item_id'          => intval($child['id']),
                'child_total_count' => 0,
                'child_completed_count' => 0,
                'child_completion_pct' => null,
            ];

            if (!$childStatusHidden) {
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
    }

    // Keep orphaned items visible when their role-specific parent is hidden for this role.
    foreach ($rows as $row) {
        $parentId = $row['effective_parent_item_id'];
        if ($parentId === null || isset($parentIdSet[intval($parentId)])) {
            continue;
        }

        $key = $row['item_key'];
        $status = $userStatuses[$key] ?? 'empty';
        $supportReview = $supportReviews[$key] ?? ['support_decision' => '', 'support_comment' => ''];
        $controlReview = $controlReviews[$key] ?? ['decision' => '', 'comment' => ''];
        $sectionData['items'][] = [
            'key'              => $key,
            'label'            => $row['description'],
            'status'           => $status,
            'user_comment'     => $userComments[$key] ?? '',
            'support_decision' => $supportReview['support_decision'],
            'support_comment'  => $supportReview['support_comment'],
            'decision'         => $controlReview['decision'],
            'comment'          => $controlReview['comment'],
            'is_parent'        => false,
            'user_status_hidden' => intval($row['visible_user'] ?? 1) !== 1,
            'parent_item_id'   => intval($parentId),
            'item_id'          => intval($row['id']),
            'child_total_count' => 0,
            'child_completed_count' => 0,
            'child_completion_pct' => null,
        ];
        if (intval($row['visible_user'] ?? 1) === 1) {
            $totalItems++;
            switch ($status) {
                case 'done':    $doneCount++;    break;
                case 'na':      $naCount++;      break;
                case 'still':   $stillCount++;   break;
                case 'not_yet': $notYetCount++;  break;
                default:        $emptyCount++;   break;
            }
        }
    }
    $sections[] = $sectionData;
}

$summarySql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.user_parent_item_id
               FROM checklist_items ci
               WHERE ci.is_deleted = 0
                 AND ci.is_active = 1
                 AND COALESCE(ci.visible_user, 1) = 1";
if ($swo_type_id !== null) {
    $summarySql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->bind_param('i', $swo_type_id);
} else {
    $summaryStmt = $conn->prepare($summarySql);
}
$summaryStmt->execute();
$summaryRows = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$summaryStmt->close();

$summaryHasChildren = [];
foreach ($summaryRows as $row) {
    $effectiveParent = $row['user_parent_item_id'] !== null
        ? intval($row['user_parent_item_id'])
        : ($row['parent_item_id'] !== null ? intval($row['parent_item_id']) : null);
    if ($effectiveParent !== null) {
        $summaryHasChildren[$effectiveParent] = true;
    }
}

$doneCount = $naCount = $stillCount = $notYetCount = $emptyCount = 0;
$totalItems = 0;
foreach ($summaryRows as $row) {
    $itemId = intval($row['id']);
    if (isset($summaryHasChildren[$itemId])) {
        continue;
    }
    $status = $userStatuses[$row['item_key']] ?? 'empty';
    $totalItems++;
    switch ($status) {
        case 'done':    $doneCount++;    break;
        case 'na':      $naCount++;      break;
        case 'still':   $stillCount++;   break;
        case 'not_yet': $notYetCount++;  break;
        default:        $emptyCount++;   break;
    }
}

$completed = $doneCount + $naCount;
$progress  = $totalItems > 0 ? round($completed / $totalItems * 100, 1) : 0;

$conn->close();

jsonResponse(true, 'Control review data retrieved', [
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
