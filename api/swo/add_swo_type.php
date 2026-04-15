<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$name        = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');

if ($name === '') {
    jsonResponse(false, 'SWO type name is required');
}

if (strlen($name) > 100) {
    jsonResponse(false, 'SWO type name must be 100 characters or less');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Check for duplicate name
$chk = $conn->prepare("SELECT id, is_active FROM swo_types WHERE name = ?");
$chk->bind_param('s', $name);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing) {
    // If it was soft-deleted, reactivate it
    if ($existing['is_active'] == 0) {
        $stmt = $conn->prepare("UPDATE swo_types SET is_active = 1, description = ? WHERE id = ?");
        $stmt->bind_param('si', $description, $existing['id']);
        $stmt->execute();
        $stmt->close();

        logAudit($conn, $user_id, null, "REACTIVATE_SWO_TYPE: {$name}", 'inactive', 'active');
        $conn->close();
        jsonResponse(true, 'SWO type reactivated', ['id' => $existing['id']]);
    }

    $conn->close();
    jsonResponse(false, "SWO type '{$name}' already exists");
}

$stmt = $conn->prepare("INSERT INTO swo_types (name, description, is_active, created_by) VALUES (?, ?, 1, ?)");
$stmt->bind_param('ssi', $name, $description, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to add SWO type');
}

$type_id = $stmt->insert_id;
$stmt->close();

logAudit($conn, $user_id, null, "ADD_SWO_TYPE: {$name}", null, 'active');

$conn->close();
jsonResponse(true, 'SWO type added successfully', ['id' => $type_id]);
