<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$conn = getDBConnection();
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($role === 'admin') {
    $stmt = $conn->prepare(
        "SELECT s.*, 
            uc.username AS created_by_name,
            ua.username AS assigned_to_name,
            uap.username AS approved_by_name,
            us.username AS support_reviewer_name,
            uct.username AS control_reviewer_name
        FROM swo_list s
        LEFT JOIN users uc ON s.created_by = uc.id
        LEFT JOIN users ua ON s.assigned_to = ua.id
        LEFT JOIN users uap ON s.approved_by = uap.id
        LEFT JOIN users us ON s.support_reviewer_id = us.id
        LEFT JOIN users uct ON s.control_reviewer_id = uct.id
        ORDER BY s.created_at DESC"
    );
    $stmt->execute();
} elseif ($role === 'support') {
    $stmt = $conn->prepare(
        "SELECT s.*,
            uc.username AS created_by_name,
            ua.username AS assigned_to_name,
            uap.username AS approved_by_name,
            us.username AS support_reviewer_name,
            uct.username AS control_reviewer_name
        FROM swo_list s
        LEFT JOIN users uc ON s.created_by = uc.id
        LEFT JOIN users ua ON s.assigned_to = ua.id
        LEFT JOIN users uap ON s.approved_by = uap.id
        LEFT JOIN users us ON s.support_reviewer_id = us.id
        LEFT JOIN users uct ON s.control_reviewer_id = uct.id
        WHERE s.created_by = ?
        ORDER BY s.created_at DESC"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
} else {
    $stmt = $conn->prepare(
        "SELECT s.*,
            uc.username AS created_by_name,
            ua.username AS assigned_to_name,
            uap.username AS approved_by_name,
            us.username AS support_reviewer_name,
            uct.username AS control_reviewer_name
        FROM swo_list s
        LEFT JOIN users uc ON s.created_by = uc.id
        LEFT JOIN users ua ON s.assigned_to = ua.id
        LEFT JOIN users uap ON s.approved_by = uap.id
        LEFT JOIN users us ON s.support_reviewer_id = us.id
        LEFT JOIN users uct ON s.control_reviewer_id = uct.id
        WHERE s.assigned_to = ?
        ORDER BY s.created_at DESC"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

$result = $stmt->get_result();
$swos = [];
while ($row = $result->fetch_assoc()) {
    $swos[] = $row;
}
$stmt->close();
$conn->close();

jsonResponse(true, 'SWO list retrieved', $swos);
