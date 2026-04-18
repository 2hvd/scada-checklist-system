<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$conn = getDBConnection();

$sectionFilter  = trim($_GET['section'] ?? '');
$search         = trim($_GET['search'] ?? '');
$statusFilter   = trim($_GET['status'] ?? 'all');
$swoTypeFilter  = intval($_GET['swo_type_id'] ?? 0);

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
if ($statusFilter === 'active') {
    $whereClauses[] = 'ci.is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $whereClauses[] = 'ci.is_active = 0';
}
if ($swoTypeFilter > 0) {
    $whereClauses[] = '(ci.swo_type_id = ? OR ci.swo_type_id IS NULL)';
    $params[]       = $swoTypeFilter;
    $types         .= 'i';
}
if ($search !== '') {
    $whereClauses[] = 'ci.description LIKE ?';
    $params[]       = '%' . $search . '%';
    $types         .= 's';
}

$where = implode(' AND ', $whereClauses);

$sql = "SELECT ci.id, ci.section, ci.section_number, ci.description, ci.item_key,
               ci.swo_type_id, ci.parent_item_id,
               ci.visible_user, ci.visible_support, ci.visible_control,
               ci.user_parent_item_id, ci.support_parent_item_id, ci.control_parent_item_id,
               ci.is_active, ci.created_at, u.username AS created_by_name,
               st.name AS swo_type_name,
               parent.description AS parent_description,
               parent.item_key AS parent_item_key,
               COUNT(DISTINCT s.id) AS usage_count,
               (SELECT COUNT(*) FROM checklist_items sub WHERE sub.parent_item_id = ci.id AND sub.is_deleted = 0) AS sub_items_count
           FROM checklist_items ci
           LEFT JOIN users u ON u.id = ci.created_by
           LEFT JOIN checklist_status cs ON ci.item_key = cs.item_key
           LEFT JOIN swo_list s
                  ON s.id = cs.swo_id
                 AND (
                      ci.swo_type_id IS NULL
                      OR s.swo_type_id IS NULL
                      OR s.swo_type_id = ci.swo_type_id
                 )
           LEFT JOIN swo_types st ON st.id = ci.swo_type_id
           LEFT JOIN checklist_items parent ON parent.id = ci.parent_item_id
         WHERE {$where}
          GROUP BY ci.id, ci.section, ci.section_number, ci.description, ci.item_key,
                   ci.swo_type_id, ci.parent_item_id,
                   ci.visible_user, ci.visible_support, ci.visible_control,
                   ci.user_parent_item_id, ci.support_parent_item_id, ci.control_parent_item_id,
                   ci.is_active, ci.created_at, u.id,
                   st.name, parent.description, parent.item_key
         ORDER BY ci.swo_type_id, ci.section, ci.parent_item_id IS NOT NULL, ci.parent_item_id, ci.section_number";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $row['section_label']     = $sectionLabels[$row['section']] ?? ucwords(str_replace('_', ' ', $row['section']));
    $row['usage_count']       = intval($row['usage_count']);
    $row['sub_items_count']   = intval($row['sub_items_count']);
    $row['is_parent']         = ($row['parent_item_id'] === null && $row['sub_items_count'] > 0);
    $row['swo_type_name']     = $row['swo_type_name'] ?? '—';
    $row['parent_description'] = $row['parent_description'] ?? null;
    $row['parent_item_key']   = $row['parent_item_key'] ?? null;
    $row['visible_user']      = intval($row['visible_user'] ?? 1);
    $row['visible_support']   = intval($row['visible_support'] ?? 1);
    $row['visible_control']   = intval($row['visible_control'] ?? 1);
    $items[] = $row;
}
$stmt->close();

// Build hierarchical map (section -> swo_type_id -> parents/children)
$hierarchical = [];
$childrenByParent = [];
foreach ($items as $item) {
    if ($item['parent_item_id'] !== null) {
        $childrenByParent[$item['parent_item_id']][] = $item;
    }
}
foreach ($items as $item) {
    if ($item['parent_item_id'] !== null) {
        continue;
    }
    $sectionKey = $item['section'];
    $typeKey = ($item['swo_type_id'] === null || $item['swo_type_id'] === '') ? 'null' : strval($item['swo_type_id']);
    if (!isset($hierarchical[$sectionKey])) {
        $hierarchical[$sectionKey] = [];
    }
    if (!isset($hierarchical[$sectionKey][$typeKey])) {
        $hierarchical[$sectionKey][$typeKey] = [];
    }
    $parent = $item;
    $parent['children'] = $childrenByParent[$item['id']] ?? [];
    $hierarchical[$sectionKey][$typeKey][] = $parent;
}

// Statistics
$statsSql = "SELECT section, COUNT(*) AS total, SUM(is_active) AS active_count
               FROM checklist_items
              WHERE is_deleted = 0";
$statsParams = [];
$statsTypes = '';
if ($swoTypeFilter > 0) {
    $statsSql .= " AND (swo_type_id = ? OR swo_type_id IS NULL)";
    $statsParams[] = $swoTypeFilter;
    $statsTypes .= 'i';
}
$statsSql .= " GROUP BY section";

$statsStmt = $conn->prepare($statsSql);
if ($statsTypes !== '') {
    $statsStmt->bind_param($statsTypes, ...$statsParams);
}
$statsStmt->execute();
$statsRes = $statsStmt->get_result();
$stats = [];
while ($row = $statsRes->fetch_assoc()) {
    $stats[$row['section']] = [
        'section' => $row['section'],
        'total'  => intval($row['total']),
        'active' => intval($row['active_count']),
        'label'  => $sectionLabels[$row['section']] ?? $row['section'],
    ];
}
$statsStmt->close();

$totalItems  = array_sum(array_column($stats, 'total'));
$activeItems = array_sum(array_column($stats, 'active'));

// Get parent items list for the add modal
$parentItems = [];
$parentSql = "SELECT ci.id, ci.description, ci.item_key, ci.section, ci.section_number, ci.swo_type_id, st.name AS swo_type_name
                 FROM checklist_items ci
                 LEFT JOIN swo_types st ON st.id = ci.swo_type_id
                WHERE ci.is_deleted = 0 AND ci.is_active = 1 AND ci.parent_item_id IS NULL";
$parentParams = [];
$parentTypes = '';
if ($swoTypeFilter > 0) {
    $parentSql .= " AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL)";
    $parentParams[] = $swoTypeFilter;
    $parentTypes .= 'i';
}
$parentSql .= " ORDER BY ci.swo_type_id, ci.section, ci.section_number";
$parentStmt = $conn->prepare($parentSql);
if ($parentTypes !== '') {
    $parentStmt->bind_param($parentTypes, ...$parentParams);
}
$parentStmt->execute();
$parentRes = $parentStmt->get_result();
while ($row = $parentRes->fetch_assoc()) {
    $row['section_label'] = $sectionLabels[$row['section']] ?? ucwords(str_replace('_', ' ', $row['section']));
    $parentItems[] = $row;
}
$parentStmt->close();

// Max section number per (section, swo_type_id) for top-level items
$maxPerType = [];
$maxSql = "SELECT section, swo_type_id, MAX(section_number) AS max_number
             FROM checklist_items
            WHERE is_deleted = 0 AND parent_item_id IS NULL";
$maxParams = [];
$maxTypes = '';
if ($swoTypeFilter > 0) {
    $maxSql .= " AND (swo_type_id = ? OR swo_type_id IS NULL)";
    $maxParams[] = $swoTypeFilter;
    $maxTypes .= 'i';
}
$maxSql .= " GROUP BY section, swo_type_id";
$maxStmt = $conn->prepare($maxSql);
if ($maxTypes !== '') {
    $maxStmt->bind_param($maxTypes, ...$maxParams);
}
$maxStmt->execute();
$maxRes = $maxStmt->get_result();
while ($row = $maxRes->fetch_assoc()) {
    $maxPerType[] = [
        'section' => $row['section'],
        'swo_type_id' => $row['swo_type_id'] !== null ? intval($row['swo_type_id']) : null,
        'max_number' => intval($row['max_number'] ?? 0),
    ];
}
$maxStmt->close();

$conn->close();
jsonResponse(true, 'Items retrieved', [
    'items'        => $items,
    'hierarchical' => $hierarchical,
    'total_items'  => $totalItems,
    'active_items' => $activeItems,
    'by_section'   => array_values($stats),
    'parent_items' => $parentItems,
    'max_number_per_type' => $maxPerType,
]);
