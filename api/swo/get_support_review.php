<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'swo_id is required');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Load SWO details
$stmt = $conn->prepare(
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.swo_type_id, s.kcor, s.status,
            s.submitted_at, s.rejection_reason,
            s.assigned_to,
            ua.username AS assigned_to_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to = ua.id
     WHERE s.id = ? AND s.status IN ('Pending Support Review', 'Returned from Control')"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found or not in a reviewable state');
}

$swo_type_id = !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : null;
<<<<<<< HEAD

// Check for snapshot
$snapChk = $conn->prepare("SELECT COUNT(*) as cnt FROM swo_checklist_items WHERE swo_id = ?");
$snapChk->bind_param('i', $swo_id);
$snapChk->execute();
$snapCnt = intval($snapChk->get_result()->fetch_assoc()['cnt']);
$snapChk->close();

if ($snapCnt > 0) {
    $itemSql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id,
                       ci.visible_support, ci.support_parent_item_id, ci.visible_user, ci.user_parent_item_id
                FROM swo_checklist_items sci
                JOIN checklist_items ci ON ci.id = sci.item_id
                WHERE sci.swo_id = ?
                ORDER BY ci.section, COALESCE(ci.parent_item_id, ci.id), ci.parent_item_id IS NOT NULL, ci.section_number";
    $itemStmt = $conn->prepare($itemSql);
    $itemStmt->bind_param('i', $swo_id);
} else {
    $itemSql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id,
                       ci.visible_support, ci.support_parent_item_id, ci.visible_user, ci.user_parent_item_id
                 FROM checklist_items ci
                WHERE ci.is_deleted = 0
                  AND ci.is_active = 1";
    if ($swo_type_id !== null) {
        $itemSql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    }
    $itemSql .= " ORDER BY ci.section, COALESCE(ci.parent_item_id, ci.id), ci.parent_item_id IS NOT NULL, ci.section_number";
    if ($swo_type_id !== null) {
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param('i', $swo_type_id);
    } else {
        $itemStmt = $conn->prepare($itemSql);
    }
=======
$itemSql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id,
                   ci.visible_support, ci.support_parent_item_id, ci.visible_user, ci.user_parent_item_id
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
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9
}
$itemStmt->execute();
$itemsRes = $itemStmt->get_result();
$itemRows = [];
$allItemRows = [];
while ($row = $itemsRes->fetch_assoc()) {
    $row['effective_parent_item_id'] = $row['support_parent_item_id'] !== null
        ? intval($row['support_parent_item_id'])
        : ($row['parent_item_id'] !== null ? intval($row['parent_item_id']) : null);
    $row['user_effective_parent_item_id'] = $row['user_parent_item_id'] !== null
        ? intval($row['user_parent_item_id'])
        : ($row['parent_item_id'] !== null ? intval($row['parent_item_id']) : null);
    $allItemRows[] = $row;
    if (intval($row['visible_support'] ?? 1) !== 1) {
        continue;
    }
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

$userChildrenByParent = [];
foreach ($allItemRows as $row) {
    if (intval($row['visible_user'] ?? 1) !== 1) {
        continue;
    }
    $parentId = $row['user_effective_parent_item_id'];
    if ($parentId === null) {
        continue;
    }
    if (!isset($userChildrenByParent[$parentId])) {
        $userChildrenByParent[$parentId] = [];
    }
    $userChildrenByParent[$parentId][] = $row;
}

// Load existing support item reviews
$stmt = $conn->prepare(
    "SELECT item_key, support_decision, support_comment
     FROM support_item_reviews WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$itemReviews = [];
while ($row = $result->fetch_assoc()) {
    $itemReviews[$row['item_key']] = [
        'decision' => $row['support_decision'] ?? '',
        'comment'  => $row['support_comment']  ?? '',
    ];
}
$stmt->close();

// Load user item comments (read-only for support)
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

// Load control item reviews (read-only for support when SWO was returned from Control)
$controlComments = [];
if ($swo['status'] === 'Returned from Control') {
    $stmt = $conn->prepare(
        "SELECT item_key, control_comment FROM control_item_reviews WHERE swo_id = ?"
    );
    $stmt->bind_param('i', $swo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $controlComments[$row['item_key']] = $row['control_comment'] ?? '';
    }
    $stmt->close();
}

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
<<<<<<< HEAD
    // Also collect hidden children (visible_support=0) from allItemRows so their user comments are visible
    $hiddenChildrenByParent = [];
    foreach ($allItemRows as $row) {
        if (intval($row['visible_support'] ?? 1) === 1) continue; // already in $children
        $effParent = $row['effective_parent_item_id'];
        if ($effParent === null) continue;
        if ($row['section'] !== $secKey) continue;
        $hiddenChildrenByParent[$effParent][] = $row;
    }
=======
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9
    $parentIdSet = [];
    foreach ($parents as $p) {
        $parentIdSet[intval($p['id'])] = true;
    }

    foreach ($parents as $parent) {
        $parentKey = $parent['item_key'];
        $parentUserStatusHidden = intval($parent['visible_user'] ?? 1) !== 1;
        $parentStatus = $parentUserStatusHidden ? 'empty' : ($userStatuses[$parentKey] ?? 'empty');
        $parentReview = $itemReviews[$parentKey] ?? ['decision' => '', 'comment' => ''];
        $childRows = $children[$parent['id']] ?? [];
        $hasVisibleChildren = !empty($childRows);

        $progressChildRows = $userChildrenByParent[intval($parent['id'])] ?? [];
        $childTotal = 0;
        $childCompleted = 0;
        foreach ($progressChildRows as $child) {
            $childTotal++;
            $childStatus = $userStatuses[$child['item_key']] ?? 'empty';
            if ($childStatus === 'done' || $childStatus === 'na') {
                $childCompleted++;
            }
        }

        $childVisibleTotal = 0;
        $childDone = 0;
        $childNa = 0;
        $childStill = 0;
        $childNotYet = 0;
        $childEmpty = 0;
        foreach ($childRows as $child) {
            $childKey = $child['item_key'];
            $childStatus = $userStatuses[$childKey] ?? 'empty';
            if (intval($child['visible_user'] ?? 1) !== 1) {
                continue;
            }
            $childVisibleTotal++;
            switch ($childStatus) {
                case 'done':    $childDone++;   break;
                case 'na':      $childNa++;     break;
                case 'still':   $childStill++;  break;
                case 'not_yet': $childNotYet++; break;
                default:        $childEmpty++;  break;
            }
        }
        $childCompletionPct = $childTotal > 0 ? round(($childCompleted / $childTotal) * 100, 1) : null;

        $sectionData['items'][] = [
            'key'             => $parentKey,
            'label'           => $parent['description'],
            'status'          => $parentStatus,
            'user_comment'    => $userComments[$parentKey] ?? '',
            'decision'        => $parentReview['decision'],
            'comment'         => $parentReview['comment'],
            'control_comment' => $controlComments[$parentKey] ?? '',
            'is_parent'       => ($hasVisibleChildren || $childTotal > 0),
            'user_status_hidden' => $parentUserStatusHidden,
            'parent_item_id'  => null,
            'item_id'         => intval($parent['id']),
            'child_total_count' => $childTotal,
            'child_completed_count' => $childCompleted,
            'child_completion_pct' => $childCompletionPct,
        ];

        if (!$hasVisibleChildren && !$parentUserStatusHidden) {
            $totalItems++;
            switch ($parentStatus) {
                case 'done':    $doneCount++;    break;
                case 'na':      $naCount++;      break;
                case 'still':   $stillCount++;   break;
                case 'not_yet': $notYetCount++;  break;
                default:        $emptyCount++;   break;
            }
        }

        if ($hasVisibleChildren) {
            $totalItems += $childVisibleTotal;
            $doneCount  += $childDone;
            $naCount    += $childNa;
            $stillCount += $childStill;
            $notYetCount += $childNotYet;
            $emptyCount += $childEmpty;

            foreach ($childRows as $child) {
                $childKey = $child['item_key'];
                $childStatusHidden = intval($child['visible_user'] ?? 1) !== 1;
                $st = $childStatusHidden ? 'empty' : ($userStatuses[$childKey] ?? 'empty');
                $review = $itemReviews[$childKey] ?? ['decision' => '', 'comment' => ''];
                $sectionData['items'][] = [
                    'key'             => $childKey,
                    'label'           => $child['description'],
                    'status'          => $st,
                    'user_comment'    => $userComments[$childKey] ?? '',
                    'decision'        => $review['decision'],
                    'comment'         => $review['comment'],
                    'control_comment' => $controlComments[$childKey] ?? '',
                    'is_parent'       => false,
                    'user_status_hidden' => $childStatusHidden,
                    'parent_item_id'  => intval($parent['id']),
                    'parent_key'      => $parentKey,
                    'item_id'         => intval($child['id']),
                    'child_total_count' => 0,
                    'child_completed_count' => 0,
                    'child_completion_pct' => null,
                ];
            }
<<<<<<< HEAD
        } // end if ($hasVisibleChildren)

        // Append hidden-from-support children as comment-only rows (always, regardless of hasVisibleChildren)
        $hiddenChildren = $hiddenChildrenByParent[intval($parent['id'])] ?? [];
        foreach ($hiddenChildren as $child) {
            $childKey = $child['item_key'];
            $userComment = $userComments[$childKey] ?? '';
            if ($userComment === '') continue;
            $sectionData['items'][] = [
                'key'             => $childKey,
                'label'           => $child['description'],
                'status'          => $userStatuses[$childKey] ?? 'empty',
                'user_comment'    => $userComment,
                'decision'        => '',
                'comment'         => '',
                'control_comment' => $controlComments[$childKey] ?? '',
                'is_parent'       => false,
                'user_status_hidden' => false,
                'comment_only'    => true,
                'parent_item_id'  => intval($parent['id']),
                'parent_key'      => $parentKey,
                'item_id'         => intval($child['id']),
                'child_total_count' => 0,
                'child_completed_count' => 0,
                'child_completion_pct' => null,
            ];
        }

    } // end foreach ($parents as $parent)


=======
        }

    }
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9

    // Keep orphaned items visible when their role-specific parent is hidden for this role.
    foreach ($rows as $row) {
        $parentId = $row['effective_parent_item_id'];
        if ($parentId === null || isset($parentIdSet[intval($parentId)])) {
            continue;
        }

        $key = $row['item_key'];
        $st = $userStatuses[$key] ?? 'empty';
        $review = $itemReviews[$key] ?? ['decision' => '', 'comment' => ''];
        $sectionData['items'][] = [
            'key'             => $key,
            'label'           => $row['description'],
            'status'          => $st,
            'user_comment'    => $userComments[$key] ?? '',
            'decision'        => $review['decision'],
            'comment'         => $review['comment'],
            'control_comment' => $controlComments[$key] ?? '',
            'is_parent'       => false,
            'user_status_hidden' => intval($row['visible_user'] ?? 1) !== 1,
            'parent_item_id'  => intval($parentId),
            'item_id'         => intval($row['id']),
            'child_total_count' => 0,
            'child_completed_count' => 0,
            'child_completion_pct' => null,
        ];
        if (intval($row['visible_user'] ?? 1) === 1) {
            $totalItems++;
            switch ($st) {
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

<<<<<<< HEAD
if ($snapCnt > 0) {
    $summaryStmt = $conn->prepare(
        "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.support_parent_item_id
         FROM swo_checklist_items sci
         JOIN checklist_items ci ON ci.id = sci.item_id
         WHERE sci.swo_id = ? AND COALESCE(ci.visible_support, 1) = 1"
    );
    $summaryStmt->bind_param('i', $swo_id);
} else {
    $summarySql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.support_parent_item_id
                    FROM checklist_items ci
                    WHERE ci.is_deleted = 0
                      AND ci.is_active = 1
                      AND COALESCE(ci.visible_support, 1) = 1";
    if ($swo_type_id !== null) {
        $summarySql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bind_param('i', $swo_type_id);
    } else {
        $summaryStmt = $conn->prepare($summarySql);
    }
=======
$summarySql = "SELECT ci.id, ci.item_key, ci.parent_item_id, ci.support_parent_item_id
                FROM checklist_items ci
                WHERE ci.is_deleted = 0
                  AND ci.is_active = 1
                  AND COALESCE(ci.visible_support, 1) = 1";
if ($swo_type_id !== null) {
    $summarySql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->bind_param('i', $swo_type_id);
} else {
    $summaryStmt = $conn->prepare($summarySql);
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9
}
$summaryStmt->execute();
$summaryRows = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$summaryStmt->close();

$summaryHasChildren = [];
foreach ($summaryRows as $row) {
    $effectiveParent = $row['support_parent_item_id'] !== null
        ? intval($row['support_parent_item_id'])
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
    $st = $itemReviews[$row['item_key']]['decision'] ?? 'empty';
    $totalItems++;
    switch ($st) {
        case 'done':    $doneCount++;    break;
        case 'na':      $naCount++;      break;
        case 'still':   $stillCount++;   break;
        case 'not_yet': $notYetCount++;  break;
        default:        $emptyCount++;   break;
    }
}

$completed = $totalItems - $emptyCount;
$progress  = $totalItems > 0 ? round($completed / $totalItems * 100, 1) : 0;

$conn->close();

jsonResponse(true, 'Support review data retrieved', [
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
