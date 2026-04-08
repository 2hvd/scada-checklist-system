<?php
session_start();

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$swo_id = intval($_GET['swo_id'] ?? 0);
if (!$swo_id) {
    header('Content-Type: application/json');
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get SWO details
$stmt = $conn->prepare(
    "SELECT s.*, u.username AS assigned_to_name 
     FROM swo_list s 
     LEFT JOIN users u ON s.assigned_to = u.id
     WHERE s.id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    header('Content-Type: application/json');
    jsonResponse(false, 'SWO not found');
}

// Access control
if ($role === 'user' && $swo['assigned_to'] != $user_id) {
    $conn->close();
    header('Content-Type: application/json');
    jsonResponse(false, 'Access denied');
}

$assigned_id = $swo['assigned_to'];

// Get checklist data
$csStmt = $conn->prepare(
    "SELECT item_key, status FROM checklist_status WHERE swo_id = ? AND user_id = ?"
);
$csStmt->bind_param('ii', $swo_id, $assigned_id);
$csStmt->execute();
$csResult = $csStmt->get_result();
$statusMap = [];
while ($row = $csResult->fetch_assoc()) {
    $statusMap[$row['item_key']] = $row['status'];
}
$csStmt->close();
$conn->close();

$checklistDef = getChecklistItems();

// Output CSV
$filename = 'checklist_' . $swo['swo_number'] . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// Header rows
fputcsv($out, ['SCADA Checklist System - Export']);
fputcsv($out, ['SWO Number', $swo['swo_number']]);
fputcsv($out, ['Station Name', $swo['station_name']]);
fputcsv($out, ['SWO Type', $swo['swo_type']]);
fputcsv($out, ['Status', $swo['status']]);
fputcsv($out, ['Assigned To', $swo['assigned_to_name'] ?? 'N/A']);
fputcsv($out, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($out, []);
fputcsv($out, ['Section', 'Item', 'Status']);

$total = 0;
$done = 0;
$na = 0;

foreach ($checklistDef as $section) {
    foreach ($section['items'] as $key => $label) {
        $st = $statusMap[$key] ?? 'empty';
        fputcsv($out, [$section['label'], $label, $st]);
        $total++;
        if ($st === 'done') $done++;
        if ($st === 'na') $na++;
    }
}

fputcsv($out, []);
$progress = $total > 0 ? round(($done + $na) / $total * 100, 1) : 0;
fputcsv($out, ['Progress', $progress . '%', "($done done + $na N/A) / $total total"]);

fclose($out);
exit;
