<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get SWO info including swo_type_id
$stmt = $conn->prepare("SELECT id, status, assigned_to, swo_type_id FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

$assigned_id  = $swo['assigned_to'];
$swo_type_id  = $swo['swo_type_id'] ? intval($swo['swo_type_id']) : null;
$requested_swo_type_id = intval($_GET['swo_type_id'] ?? 0);
if ($swo_type_id === null && $requested_swo_type_id > 0) {
    $swo_type_id = $requested_swo_type_id;
}

// Load items filtered by SWO type if set, including hierarchy
// Items with no swo_type_id (null) are shown for all SWO types (backward compatible)
$sql = "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description,
               ci.parent_item_id, ci.swo_type_id,
               COALESCE(cs.status, 'empty') AS status,
               cs.updated_at AS status_updated_at,
               uic.comment AS user_comment
        FROM checklist_items ci
        LEFT JOIN checklist_status cs
              ON cs.item_key = ci.item_key
             AND cs.swo_id   = ?
             AND cs.user_id  = ?
        LEFT JOIN user_item_comments uic
              ON uic.item_key = ci.item_key
             AND uic.swo_id   = ?
             AND uic.user_id  = ?
        WHERE ((ci.is_active = 1 AND ci.is_deleted = 0)
               OR (cs.id IS NOT NULL))";

$bind_types = 'iiii';
$bind_values = [$swo_id, $assigned_id, $swo_id, $assigned_id];

// Filter by SWO type: strictly show only matching type + universal (NULL) items.
// Do not include mismatched typed items just because they have checklist_status rows.
if ($swo_type_id !== null) {
    $sql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $bind_types .= 'i';
    $bind_values[] = $swo_type_id;
}

$sql .= " ORDER BY ci.section, ci.parent_item_id IS NOT NULL, ci.parent_item_id, ci.section_number";

$stmt = $conn->prepare($sql);
$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sectionLabels = [
    'during_config'        => 'During Configuration',
    'during_commissioning' => 'During Commissioning',
    'after_commissioning'  => 'After Commissioning',
];

// Build item lookup for hierarchy
$itemById = [];
foreach ($rows as $row) {
    $itemById[$row['id']] = $row;
}

// Organize into sections with hierarchy
$grouped = [];
foreach ($rows as $row) {
    $sec = $row['section'];
    if (!isset($grouped[$sec])) {
        $grouped[$sec] = [];
    }
    $grouped[$sec][] = $row;
}

$sectionOrder = ['during_config', 'during_commissioning', 'after_commissioning'];
uksort($grouped, function($a, $b) use ($sectionOrder) {
    $ai = array_search($a, $sectionOrder);
    $bi = array_search($b, $sectionOrder);
    if ($ai === false) $ai = 99;
    if ($bi === false) $bi = 99;
    return $ai - $bi;
});

$sections = [];
$total = 0;
$done = 0;
$na = 0;
$not_yet = 0;
$still = 0;
$empty_count = 0;

foreach ($grouped as $section_key => $sectionRows) {
    // Separate parents and sub-items for ordering
    $parents = [];
    $children = [];

    foreach ($sectionRows as $row) {
        if ($row['parent_item_id'] === null) {
            $parents[] = $row;
        } else {
            $children[$row['parent_item_id']][] = $row;
        }
    }

    $items = [];
    foreach ($parents as $parent) {
        $hasChildren = isset($children[$parent['id']]);
        $st = $parent['status'];

        $items[] = [
            'key'           => $parent['item_key'],
            'label'         => $parent['description'],
            'status'        => $st,
            'user_comment'  => $parent['user_comment'] ?? '',
            'updated_at'    => $parent['status_updated_at'],
            'is_parent'     => $hasChildren,
            'parent_item_id' => null,
            'item_id'       => $parent['id'],
        ];

        // Only count parent in progress if it has no children
        if (!$hasChildren) {
            $total++;
            if ($st === 'done')        $done++;
            elseif ($st === 'na')      $na++;
            elseif ($st === 'not_yet') $not_yet++;
            elseif ($st === 'still')   $still++;
            else                       $empty_count++;
        }

        // Add sub-items
        if ($hasChildren) {
            foreach ($children[$parent['id']] as $child) {
                $cst = $child['status'];
                $items[] = [
                    'key'           => $child['item_key'],
                    'label'         => $child['description'],
                    'status'        => $cst,
                    'user_comment'  => $child['user_comment'] ?? '',
                    'updated_at'    => $child['status_updated_at'],
                    'is_parent'     => false,
                    'parent_item_id' => $parent['id'],
                    'parent_key'    => $parent['item_key'],
                    'item_id'       => $child['id'],
                ];
                $total++;
                if ($cst === 'done')        $done++;
                elseif ($cst === 'na')      $na++;
                elseif ($cst === 'not_yet') $not_yet++;
                elseif ($cst === 'still')   $still++;
                else                        $empty_count++;
            }
        }
    }

    // Add orphan children (items with parent_item_id but parent not in result set)
    foreach ($sectionRows as $row) {
        if ($row['parent_item_id'] !== null && !isset($itemById[$row['parent_item_id']])) {
            $st = $row['status'];
            $items[] = [
                'key'           => $row['item_key'],
                'label'         => $row['description'],
                'status'        => $st,
                'user_comment'  => $row['user_comment'] ?? '',
                'updated_at'    => $row['status_updated_at'],
                'is_parent'     => false,
                'parent_item_id' => $row['parent_item_id'],
                'item_id'       => $row['id'],
            ];
            $total++;
            if ($st === 'done')        $done++;
            elseif ($st === 'na')      $na++;
            elseif ($st === 'not_yet') $not_yet++;
            elseif ($st === 'still')   $still++;
            else                       $empty_count++;
        }
    }

    $sections[] = [
        'key'   => $section_key,
        'label' => $sectionLabels[$section_key] ?? ucwords(str_replace('_', ' ', $section_key)),
        'items' => $items,
    ];
}

$progress = $total > 0 ? round(($done + $na) / $total * 100, 1) : 0;

$conn->close();
jsonResponse(true, 'Checklist retrieved', [
    'swo_id'       => $swo_id,
    'swo_status'   => $swo['status'],
    'sections'     => $sections,
    'progress'     => $progress,
    'counts'       => [
        'total'    => $total,
        'done'     => $done,
        'na'       => $na,
        'not_yet'  => $not_yet,
        'still'    => $still,
        'empty'    => $empty_count,
    ]
]);
