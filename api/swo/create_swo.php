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
$kcor         = sanitizeInput($input['kcor'] ?? '');
$description  = sanitizeInput($input['description'] ?? '');
$action       = sanitizeInput($input['action'] ?? 'draft');

if (empty($swo_number) || empty($station_name) || empty($swo_type)) {
    jsonResponse(false, 'SWO number, station name, and type are required');
}

$status = ($action === 'submit') ? 'Pending' : 'Draft';
$created_by = $_SESSION['user_id'];

$conn = getDBConnection();

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
    "INSERT INTO swo_list (swo_number, station_name, swo_type, kcor, description, status, created_by) VALUES (?,?,?,?,?,?,?)"
);
$stmt->bind_param('ssssssi', $swo_number, $station_name, $swo_type, $kcor, $description, $status, $created_by);

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
