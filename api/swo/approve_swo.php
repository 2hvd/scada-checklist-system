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

$swo_id = intval($input['swo_id'] ?? 0);
if (!$swo_id) {
    jsonResponse(false, 'SWO ID is required');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, status FROM swo_list WHERE id = ?");
$stmt->bind_param('i', $swo_id);
$stmt->execute();
$result = $stmt->get_result();
$swo = $result->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    jsonResponse(false, 'SWO not found');
}

if ($swo['status'] !== 'Pending') {
    $conn->close();
    jsonResponse(false, 'Only Pending SWOs can be approved');
}

$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "UPDATE swo_list SET status = 'Registered', approved_by = ?, approved_at = NOW() WHERE id = ?"
);
$stmt->bind_param('ii', $admin_id, $swo_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Failed to approve SWO');
}
$stmt->close();

logAudit($conn, $admin_id, $swo_id, 'APPROVE_SWO', 'Pending', 'Registered');

$conn->close();
jsonResponse(true, 'SWO approved successfully');
