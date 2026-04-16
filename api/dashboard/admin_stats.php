<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$conn = getDBConnection();

// SWO status summary
$statusResult = $conn->query(
    "SELECT status, COUNT(*) as count FROM swo_list GROUP BY status"
);
$statusCounts = [];
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = intval($row['count']);
}

// Per-user stats (only 'user' role)
$userStmt = $conn->prepare(
    "SELECT u.id, u.username,
        COUNT(DISTINCT s.id) AS total_assigned,
        SUM(CASE WHEN s.status = 'Completed' OR s.status = 'Closed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN s.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN s.status IN ('Pending Support Review', 'Submitted') THEN 1 ELSE 0 END) AS pending_review
    FROM users u
    LEFT JOIN swo_list s ON s.assigned_to = u.id
    WHERE u.role = 'user' AND u.active = 1
    GROUP BY u.id, u.username"
);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userStats = [];

while ($user = $userResult->fetch_assoc()) {
    // Get checklist progress for this user
    $progressStmt = $conn->prepare(
        "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN cs.status IN ('done','na') THEN 1 ELSE 0 END) AS completed_items
         FROM checklist_status cs
         JOIN swo_list s ON cs.swo_id = s.id
         WHERE cs.user_id = ? AND s.status NOT IN ('Draft','Pending','Registered')"
    );
    $progressStmt->bind_param('i', $user['id']);
    $progressStmt->execute();
    $progress = $progressStmt->get_result()->fetch_assoc();
    $progressStmt->close();

    $user['total_items'] = intval($progress['total']);
    $user['completed_items'] = intval($progress['completed_items']);
    $user['submitted'] = intval($user['pending_review']);
    $user['completion_pct'] = $user['total_items'] > 0
        ? round($user['completed_items'] / $user['total_items'] * 100, 1)
        : 0;
    $userStats[] = $user;
}
$userStmt->close();

// Recent audit log
$auditResult = $conn->query(
    "SELECT al.*, u.username, s.swo_number
     FROM audit_log al
     LEFT JOIN users u ON al.user_id = u.id
     LEFT JOIN swo_list s ON al.swo_id = s.id
     ORDER BY al.timestamp DESC LIMIT 20"
);
$recentActivity = [];
while ($row = $auditResult->fetch_assoc()) {
    $recentActivity[] = $row;
}

$conn->close();
jsonResponse(true, 'Admin stats retrieved', [
    'status_counts'   => $statusCounts,
    'user_stats'      => $userStats,
    'recent_activity' => $recentActivity,
]);
