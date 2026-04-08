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

$section        = trim($input['section'] ?? '');
$section_number = intval($input['section_number'] ?? 0);
$description    = trim($input['description'] ?? '');

$validSections = ['during_config', 'during_commissioning', 'after_commissioning'];
if (!in_array($section, $validSections, true)) {
    jsonResponse(false, 'Invalid section. Must be one of: ' . implode(', ', $validSections));
}
if ($section_number < 1 || $section_number > 99) {
    jsonResponse(false, 'Invalid section number (must be 1–99)');
}
if ($description === '') {
    jsonResponse(false, 'Description is required');
}

$item_key = $section . '_' . $section_number;

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Prevent duplicate item_key
$chk = $conn->prepare("SELECT id FROM checklist_items WHERE item_key = ?");
$chk->bind_param('s', $item_key);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing) {
    $conn->close();
    jsonResponse(false, "Item key '{$item_key}' already exists. Choose a different section number.");
}

$stmt = $conn->prepare(
    "INSERT INTO checklist_items (section, section_number, description, item_key, is_active, created_by)
     VALUES (?, ?, ?, ?, 1, ?)"
);

// الترتيب الصح: s=section, i=section_number, s=description, s=item_key, i=created_by
$stmt->bind_param('sissi', $section, $section_number, $description, $item_key, $user_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Database error: ' . $conn->error);
}

$item_id = $stmt->insert_id;
$stmt->close();

logAudit($conn, $user_id, null, "ADD_CHECKLIST_ITEM: {$item_key}", null, 'active');

$conn->close();
jsonResponse(true, 'Checklist item added successfully', ['item_id' => $item_id, 'item_key' => $item_key]);
