<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('support');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$swo_number   = sanitizeInput($input['swo_number'] ?? '');
$station_name = sanitizeInput($input['station_name'] ?? '');
$swo_type     = sanitizeInput($input['swo_type'] ?? '');
$swo_type_id  = !empty($input['swo_type_id']) ? intval($input['swo_type_id']) : null;
$kcor         = sanitizeInput($input['kcor'] ?? '');
$description  = sanitizeInput($input['description'] ?? '');
$action       = 'submit';

if (empty($swo_number) || empty($station_name)) {
    jsonResponse(false, 'SWO number and station name are required');
}

// Require either swo_type_id or swo_type string
if (empty($swo_type) && $swo_type_id === null) {
    jsonResponse(false, 'SWO type is required');
}

$status = 'Pending';
$created_by = $_SESSION['user_id'];

$conn = getDBConnection();

// If swo_type_id provided, validate it and get the name
if ($swo_type_id !== null) {
    $typeChk = $conn->prepare("SELECT id, name FROM swo_types WHERE id = ? AND is_active = 1");
    $typeChk->bind_param('i', $swo_type_id);
    $typeChk->execute();
    $typeData = $typeChk->get_result()->fetch_assoc();
    $typeChk->close();

    if (!$typeData) {
        $conn->close();
        jsonResponse(false, 'Invalid or inactive SWO type');
    }
    // Use the type name as swo_type for backward compatibility
    $swo_type = $typeData['name'];
}

// Check for duplicate SWO number
$check = $conn->prepare("SELECT id FROM swo_list WHERE swo_number = ?");
$check->bind_param('s', $swo_number);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    $conn->close();
    jsonResponse(false, 'SWO number already exists');
}
$check->close();

$stmt = $conn->prepare(
    "INSERT INTO swo_list (swo_number, station_name, swo_type, swo_type_id, kcor, description, status, created_by) VALUES (?,?,?,?,?,?,?,?)"
);
$stmt->bind_param('sssisssi', $swo_number, $station_name, $swo_type, $swo_type_id, $kcor, $description, $status, $created_by);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to create SWO: ' . $conn->error);
}

$swo_id = $stmt->insert_id;
$stmt->close();

logAudit($conn, $created_by, $swo_id, 'CREATE_SWO', null, $status);

$conn->close();
jsonResponse(true, 'SWO created successfully', ['swo_id' => $swo_id, 'status' => $status]);
