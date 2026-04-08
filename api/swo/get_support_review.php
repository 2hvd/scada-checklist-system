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
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.kcor, s.status,
            s.submitted_at, s.rejection_reason,
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

// Load checklist items with user statuses
$checklistItems = getChecklistItemsFromDB($conn);

$stmt = $conn->prepare(
    "SELECT item_key, status FROM checklist_status WHERE swo_id = ?"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$userStatuses = [];
while ($row = $result->fetch_assoc()) {
    $userStatuses[$row['item_key']] = $row['status'];
}
$stmt->close();

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

// Load overall support review comments
$stmt = $conn->prepare(
    "SELECT comments FROM support_reviews WHERE swo_id = ? ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$overallRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// Build structured sections
$sections = [];
$totalItems = 0;
$doneCount = $naCount = $stillCount = $notYetCount = $emptyCount = 0;

foreach ($checklistItems as $secKey => $section) {
    $sectionData = ['label' => $section['label'], 'items' => []];
    foreach ($section['items'] as $itemKey => $itemLabel) {
        $userStatus = $userStatuses[$itemKey] ?? 'empty';
        $review     = $itemReviews[$itemKey] ?? ['decision' => '', 'comment' => ''];

        $sectionData['items'][] = [
            'key'      => $itemKey,
            'label'    => $itemLabel,
            'status'   => $userStatus,
            'decision' => $review['decision'],
            'comment'  => $review['comment'],
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

jsonResponse(true, 'Support review data retrieved', [
    'swo'             => $swo,
    'sections'        => $sections,
    'progress'        => $progress,
    'counts'          => [
        'done'    => $doneCount,
        'na'      => $naCount,
        'still'   => $stillCount,
        'not_yet' => $notYetCount,
        'empty'   => $emptyCount,
        'total'   => $totalItems,
    ],
    'overall_comments' => $overallRow['comments'] ?? '',
]);
