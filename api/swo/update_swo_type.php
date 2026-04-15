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

$swo_type_id = intval($input['swo_type_id'] ?? 0);
$name        = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');

if (!$swo_type_id || $name === '') {
    jsonResponse(false, 'SWO Type ID and name are required');
}

if (strlen($name) > 100) {
    jsonResponse(false, 'SWO type name must be 100 characters or less');
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Verify the type exists
$chk = $conn->prepare("SELECT id, name FROM swo_types WHERE id = ?");
$chk->bind_param('i', $swo_type_id);
$chk->execute();
$type = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$type) {
    $conn->close();
    jsonResponse(false, 'SWO type not found');
}

// Check for duplicate name (excluding current type)
$dupChk = $conn->prepare("SELECT id FROM swo_types WHERE name = ? AND id != ?");
$dupChk->bind_param('si', $name, $swo_type_id);
$dupChk->execute();
$duplicate = $dupChk->get_result()->fetch_assoc();
$dupChk->close();

if ($duplicate) {
    $conn->close();
    jsonResponse(false, "SWO type '{$name}' already exists");
}

$stmt = $conn->prepare("UPDATE swo_types SET name = ?, description = ? WHERE id = ?");
$stmt->bind_param('ssi', $name, $description, $swo_type_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to update SWO type');
}

$stmt->close();

$oldName = preg_replace('/[^\w\s\/\-]/', '', $type['name']);
$newName = preg_replace('/[^\w\s\/\-]/', '', $name);
logAudit($conn, $user_id, null, "UPDATE_SWO_TYPE: {$oldName} -> {$newName}", null, null);

$conn->close();
jsonResponse(true, 'SWO type updated successfully');
