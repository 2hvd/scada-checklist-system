<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireLogin();

$conn = getDBConnection();

$active_only = ($_GET['active_only'] ?? '1') === '1';

if ($active_only) {
    $result = $conn->query("SELECT id, name, description, is_active, created_at FROM swo_types WHERE is_active = 1 ORDER BY id");
} else {
    $result = $conn->query("SELECT id, name, description, is_active, created_at FROM swo_types ORDER BY id");
}

$types = [];
while ($row = $result->fetch_assoc()) {
    $types[] = $row;
}

$conn->close();
jsonResponse(true, 'SWO types retrieved', $types);
