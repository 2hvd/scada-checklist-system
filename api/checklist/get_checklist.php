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

// Always load ALL active items from checklist_items, left-joined with this SWO's saved statuses
$stmt = $conn->prepare(
    "SELECT ci.section, ci.section_number, ci.item_key, ci.description,
            COALESCE(cs.status, 'empty') AS status,
            cs.updated_at AS status_updated_at
     FROM checklist_items ci
     LEFT JOIN checklist_status cs
           ON cs.item_key = ci.item_key
          AND cs.swo_id   = ?
          AND cs.user_id  = ?
     WHERE ci.is_active = 1
       AND ci.is_deleted = 0
     ORDER BY ci.section, ci.section_number"
);
$stmt->bind_param('ii', $swo_id, $assigned_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sections = [];
$total = 0;
$done = 0;
$na = 0;
$not_yet = 0;
$still = 0;
$empty_count = 0;

$sectionLabels = [
    'during_config'        => 'During Configuration',
    'during_commissioning' => 'During Commissioning',
    'after_commissioning'  => 'After Commissioning',
];

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

foreach ($grouped as $section_key => $sectionRows) {
    $items = [];
    foreach ($sectionRows as $row) {
        $st = $row['status'];
        $items[] = [
            'key'        => $row['item_key'],
            'label'      => $row['description'],
            'status'     => $st,
            'updated_at' => $row['status_updated_at'],
        ];
        $total++;
        if ($st === 'done')        $done++;
        elseif ($st === 'na')      $na++;
        elseif ($st === 'not_yet') $not_yet++;
        elseif ($st === 'still')   $still++;
        else                       $empty_count++;
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
