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
    "SELECT s.id, s.swo_number, s.station_name, s.swo_type, s.kcor, s.status,
            s.submitted_at, s.support_reviewed_at,
            ua.username AS assigned_to_name,
            us.username AS support_reviewer_name
     FROM swo_list s
     LEFT JOIN users ua ON s.assigned_to      = ua.id
     LEFT JOIN users us ON s.support_reviewer_id = us.id
     WHERE s.id = ? AND s.status = 'Pending Control Review'"
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

$conn->close();

// Build structured sections
$sections = [];
$totalItems = 0;
$doneCount = $naCount = $stillCount = $notYetCount = $emptyCount = 0;

foreach ($checklistItems as $secKey => $section) {
    $sectionData = ['label' => $section['label'], 'items' => []];
    foreach ($section['items'] as $itemKey => $itemLabel) {
        $userStatus     = $userStatuses[$itemKey]  ?? 'empty';
        $supportReview  = $supportReviews[$itemKey] ?? ['support_decision' => '', 'support_comment' => ''];
        $controlReview  = $controlReviews[$itemKey] ?? ['decision' => '', 'comment' => ''];

        $sectionData['items'][] = [
            'key'              => $itemKey,
            'label'            => $itemLabel,
            'status'           => $userStatus,
            'user_comment'     => $userComments[$itemKey] ?? '',
            'support_decision' => $supportReview['support_decision'],
            'support_comment'  => $supportReview['support_comment'],
            'decision'         => $controlReview['decision'],
            'comment'          => $controlReview['comment'],
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
