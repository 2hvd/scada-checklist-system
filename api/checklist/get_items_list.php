<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$conn = getDBConnection();

$sectionFilter = trim($_GET['section'] ?? '');
$search        = trim($_GET['search'] ?? '');

$validSections = ['during_config', 'during_commissioning', 'after_commissioning'];
$sectionLabels = [
    'during_config'        => 'During Configuration',
    'during_commissioning' => 'During Commissioning',
    'after_commissioning'  => 'After Commissioning',
];

$whereClauses = ['ci.is_deleted = 0'];
$params       = [];
$types        = '';

if ($sectionFilter !== '' && in_array($sectionFilter, $validSections, true)) {
    $whereClauses[] = 'ci.section = ?';
    $params[]       = $sectionFilter;
    $types         .= 's';
}
if ($search !== '') {
    $whereClauses[] = 'ci.description LIKE ?';
    $params[]       = '%' . $search . '%';
    $types         .= 's';
}

$where = implode(' AND ', $whereClauses);

$sql = "SELECT ci.id, ci.section, ci.section_number, ci.description, ci.item_key,
               ci.is_active, ci.created_at, u.username AS created_by_name
          FROM checklist_items ci
          LEFT JOIN users u ON u.id = ci.created_by
         WHERE {$where}
         ORDER BY ci.section, ci.section_number";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $row['section_label'] = $sectionLabels[$row['section']] ?? ucwords(str_replace('_', ' ', $row['section']));
    $items[] = $row;
}
$stmt->close();

// Statistics
$statsRes = $conn->query(
    "SELECT section, COUNT(*) AS total, SUM(is_active) AS active_count
       FROM checklist_items
      WHERE is_deleted = 0
      GROUP BY section"
);
$stats = [];
while ($row = $statsRes->fetch_assoc()) {
    $stats[$row['section']] = [
        'total'  => intval($row['total']),
        'active' => intval($row['active_count']),
        'label'  => $sectionLabels[$row['section']] ?? $row['section'],
    ];
}

$totalItems  = array_sum(array_column($stats, 'total'));
$activeItems = array_sum(array_column($stats, 'active'));

$conn->close();
jsonResponse(true, 'Items retrieved', [
    'items'        => $items,
    'total_items'  => $totalItems,
    'active_items' => $activeItems,
    'by_section'   => array_values($stats),
]);
