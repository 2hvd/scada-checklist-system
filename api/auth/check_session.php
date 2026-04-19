<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/functions.php';

function isSQLiteDriver(): bool {
    return defined('DB_DRIVER') && strtolower(DB_DRIVER) === 'sqlite';
}

function tableExists($conn, $tableName): bool {
    if (isSQLiteDriver()) {
        $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    } else {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    }

    if (!$stmt) {
        error_log('check_session tableExists prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function scalarOrDefault($conn, $sql, $default = '0') {
    $res = $conn->query($sql);
    if (!$res) {
        error_log('check_session real-time query failed: ' . $conn->error);
        return (string)$default;
    }
    $row = $res->fetch_row();
    return isset($row[0]) && $row[0] !== null ? (string)$row[0] : (string)$default;
}

function maxTimestampExpr(string $column): string {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
        $maskedColumn = preg_replace('/[^a-zA-Z0-9_]/', '?', $column);
        error_log('Invalid column name in maxTimestampExpr: ' . $maskedColumn);
        throw new InvalidArgumentException('Invalid column name for timestamp expression');
    }
    if (isSQLiteDriver()) {
        return "IFNULL(CAST(strftime('%s', MAX($column)) AS INTEGER), 0)";
    }
    return "IFNULL(UNIX_TIMESTAMP(MAX($column)), 0)";
}

function buildRealtimeVersion($conn): string {
    $parts = [];

    $tablesWithTimeCols = [
        ['audit_log', 'timestamp'],
        ['checklist_status', 'updated_at'],
        ['comments', 'created_at'],
        ['user_item_comments', 'saved_at'],
        ['support_item_reviews', 'reviewed_at'],
        ['control_item_reviews', 'reviewed_at'],
        ['support_reviews', 'created_at'],
        ['control_reviews', 'created_at'],
    ];

    foreach ($tablesWithTimeCols as [$table, $column]) {
        if (tableExists($conn, $table)) {
            $parts[] = scalarOrDefault($conn, "SELECT " . maxTimestampExpr($column) . " FROM $table");
            $parts[] = scalarOrDefault($conn, "SELECT COUNT(*) FROM $table");
        }
    }

    if (tableExists($conn, 'swo_list')) {
        $parts[] = scalarOrDefault($conn, "SELECT COUNT(*) FROM swo_list");
        $parts[] = scalarOrDefault($conn, "SELECT " . maxTimestampExpr('created_at') . " FROM swo_list");
    }

    if (tableExists($conn, 'checklist_items')) {
        $parts[] = scalarOrDefault($conn, "SELECT COUNT(*) FROM checklist_items");
        $parts[] = scalarOrDefault($conn, "SELECT " . maxTimestampExpr('created_at') . " FROM checklist_items");
    }

    return hash('sha256', implode('|', $parts));
}

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Not logged in');
}

$_SESSION['last_activity'] = time();
$conn = getDBConnection();
$realtimeVersion = buildRealtimeVersion($conn);
$conn->close();

jsonResponse(true, 'Session active', [
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role'],
    'realtime_version' => $realtimeVersion
]);
