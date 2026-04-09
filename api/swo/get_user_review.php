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
    "SELECT id, swo_number, station_name, swo_type, kcor, status,
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

// Load checklist items
$checklistItems = getChecklistItemsFromDB($conn);

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

foreach ($checklistItems as $secKey => $section) {
    $sectionData = ['label' => $section['label'], 'items' => []];
    foreach ($section['items'] as $itemKey => $itemLabel) {
        $userStatus = $userStatuses[$itemKey] ?? 'empty';
        $comment    = $userComments[$itemKey]  ?? '';

        $sectionData['items'][] = [
            'key'     => $itemKey,
            'label'   => $itemLabel,
            'status'  => $userStatus,
            'comment' => $comment,
        ];

        $totalItems++;
        switch ($userStatus) {
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
