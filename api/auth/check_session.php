<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/functions.php';

function tableExists($conn, $tableName) {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
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

function buildRealtimeVersion($conn) {
    $parts = [];

    // Check all needed tables in a single query
    $needed = ['user_item_comments', 'support_item_reviews', 'control_item_reviews',
               'support_reviews', 'control_reviews', 'swo_list', 'checklist_items'];
    $placeholders = implode(',', array_fill(0, count($needed), '?'));
    $stmt = $conn->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)");
    $types = str_repeat('s', count($needed));
    $stmt->bind_param($types, ...$needed);
    $stmt->execute();
    $res = $stmt->get_result();
    $existingTables = [];
    while ($row = $res->fetch_row()) {
        $existingTables[$row[0]] = true;
    }
    $stmt->close();

    $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(timestamp)), 0) FROM audit_log");
    $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(updated_at)), 0) FROM checklist_status");
    $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(created_at)), 0) FROM comments");

    if (isset($existingTables['user_item_comments'])) {
        $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(saved_at)), 0) FROM user_item_comments");
    }
    if (isset($existingTables['support_item_reviews'])) {
        $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(reviewed_at)), 0) FROM support_item_reviews");
    }
    if (isset($existingTables['control_item_reviews'])) {
        $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(reviewed_at)), 0) FROM control_item_reviews");
    }
    if (isset($existingTables['support_reviews'])) {
        $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(created_at)), 0) FROM support_reviews");
    }
    if (isset($existingTables['control_reviews'])) {
        $parts[] = scalarOrDefault($conn, "SELECT IFNULL(UNIX_TIMESTAMP(MAX(created_at)), 0) FROM control_reviews");
    }
    if (isset($existingTables['swo_list'])) {
        $parts[] = scalarOrDefault(
            $conn,
            "SELECT CONCAT(
                IFNULL(COUNT(*),0), ':',
                IFNULL(SUM(CRC32(CONCAT_WS('|',
                    id, swo_number, station_name, swo_type, status,
                    COALESCE(created_by,'NULL'), COALESCE(assigned_to,'NULL'), COALESCE(approved_by,'NULL'),
                    COALESCE(assigned_at,'NULL'), COALESCE(submitted_at,'NULL'), COALESCE(approved_at,'NULL'),
                    COALESCE(support_reviewed_at,'NULL'), COALESCE(control_reviewed_at,'NULL'),
                    COALESCE(rejection_reason,'NULL')
                ))),0)
            ) FROM swo_list"
        );
    }
    if (isset($existingTables['checklist_items'])) {
        $parts[] = scalarOrDefault(
            $conn,
            "SELECT CONCAT(
                IFNULL(COUNT(*),0), ':',
                IFNULL(SUM(CRC32(CONCAT_WS('|',
                    id, item_key, section, section_number, description,
                    COALESCE(parent_item_id,'NULL'),
                    COALESCE(user_parent_item_id,'NULL'),
                    COALESCE(support_parent_item_id,'NULL'),
                    COALESCE(control_parent_item_id,'NULL'),
                    COALESCE(visible_user,'1'),
                    COALESCE(visible_support,'1'),
                    COALESCE(visible_control,'1'),
                    is_active, is_deleted, COALESCE(swo_type_id,'NULL')
                ))),0)
            ) FROM checklist_items"
        );
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
