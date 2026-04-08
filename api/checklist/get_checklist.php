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
$role = $_SESSION['role'];

// Get SWO info
$stmt = $conn->prepare("SELECT id, status, assigned_to FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

$assigned_id = $swo['assigned_to'];

// Get checklist statuses
$csStmt = $conn->prepare("SELECT item_key, status, updated_at FROM checklist_status WHERE swo_id = ? AND user_id = ?");
$csStmt->bind_param('ii', $swo_id, $assigned_id);
$csStmt->execute();
$csResult = $csStmt->get_result();

$statusMap = [];
while ($row = $csResult->fetch_assoc()) {
    $statusMap[$row['item_key']] = ['status' => $row['status'], 'updated_at' => $row['updated_at']];
}
$csStmt->close();

$checklistDef = null;
$sections = [];
$total = 0;
$done = 0;
$na = 0;
$not_yet = 0;
$still = 0;
$empty_count = 0;

// Determine section labels
$sectionLabels = [
    'during_config'        => 'During Configuration',
    'during_commissioning' => 'During Commissioning',
    'after_commissioning'  => 'After Commissioning',
];

if (!empty($statusMap)) {
    // Build display from the item_keys that exist for this SWO, resolved from DB
    $itemKeys = array_keys($statusMap);
    $labelMap = getItemLabelsForKeys($conn, $itemKeys);

    // Group items by section preserving order
    $grouped = [];
    foreach ($itemKeys as $key) {
        $meta = $labelMap[$key] ?? ['description' => $key, 'section' => 'unknown', 'section_number' => null];
        $sec  = $meta['section'];
        if (!isset($grouped[$sec])) {
            $grouped[$sec] = [];
        }
        $grouped[$sec][] = [
            'key'        => $key,
            'label'      => $meta['description'],
            'section_number' => $meta['section_number'],
        ];
    }

    // Sort sections by known order
    $sectionOrder = ['during_config', 'during_commissioning', 'after_commissioning'];
    uksort($grouped, function($a, $b) use ($sectionOrder) {
        $ai = array_search($a, $sectionOrder);
        $bi = array_search($b, $sectionOrder);
        if ($ai === false) $ai = 99;
        if ($bi === false) $bi = 99;
        return $ai - $bi;
    });

    foreach ($grouped as $section_key => $sectionItems) {
        // Sort items by section_number
        usort($sectionItems, function($a, $b) {
            return ($a['section_number'] ?? 0) - ($b['section_number'] ?? 0);
        });

        $items = [];
        foreach ($sectionItems as $item) {
            $st = $statusMap[$item['key']]['status'] ?? 'empty';
            $items[] = [
                'key'        => $item['key'],
                'label'      => $item['label'],
                'status'     => $st,
                'updated_at' => $statusMap[$item['key']]['updated_at'] ?? null,
            ];
            $total++;
            if ($st === 'done')     $done++;
            elseif ($st === 'na')   $na++;
            elseif ($st === 'not_yet') $not_yet++;
            elseif ($st === 'still')   $still++;
            else $empty_count++;
        }
        $sections[] = [
            'key'   => $section_key,
            'label' => $sectionLabels[$section_key] ?? ucwords(str_replace('_', ' ', $section_key)),
            'items' => $items
        ];
    }
} else {
    // No checklist_status rows yet — use current active items from DB for display
    $checklistDef = getChecklistItemsFromDB($conn);
    foreach ($checklistDef as $section_key => $section) {
        $items = [];
        foreach ($section['items'] as $key => $label) {
            $st = 'empty';
            $items[] = [
                'key'        => $key,
                'label'      => $label,
                'status'     => $st,
                'updated_at' => null,
            ];
            $total++;
            $empty_count++;
        }
        $sections[] = [
            'key'   => $section_key,
            'label' => $section['label'],
            'items' => $items
        ];
    }
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
