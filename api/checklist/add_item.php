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
$swo_type_id    = !empty($input['swo_type_id']) ? intval($input['swo_type_id']) : null;
$parent_item_id = !empty($input['parent_item_id']) ? intval($input['parent_item_id']) : null;

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

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Validate swo_type_id if provided
if ($swo_type_id !== null) {
    $chk = $conn->prepare("SELECT id FROM swo_types WHERE id = ? AND is_active = 1");
    $chk->bind_param('i', $swo_type_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        $conn->close();
        jsonResponse(false, 'Invalid or inactive SWO type');
    }
    $chk->close();
}

// Validate parent_item_id if provided
if ($parent_item_id !== null) {
    $chk = $conn->prepare("SELECT id, swo_type_id, section FROM checklist_items WHERE id = ? AND is_deleted = 0 AND parent_item_id IS NULL");
    $chk->bind_param('i', $parent_item_id);
    $chk->execute();
    $parent = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$parent) {
        $conn->close();
        jsonResponse(false, 'Invalid parent item (must be a top-level item)');
    }

    // Sub-item must have the same swo_type_id as the parent
    if ($swo_type_id !== null && $parent['swo_type_id'] !== null && intval($parent['swo_type_id']) !== $swo_type_id) {
        $conn->close();
        jsonResponse(false, 'Sub-item must have the same SWO type as the parent item');
    }

    // Sub-item must be in the same section as parent
    if ($parent['section'] !== $section) {
        $conn->close();
        jsonResponse(false, 'Sub-item must be in the same section as the parent item');
    }
}

// Generate item_key based on hierarchy
if ($parent_item_id !== null) {
    // Sub-item key: section_parentNumber_subNumber
    $parentChk = $conn->prepare("SELECT section_number FROM checklist_items WHERE id = ?");
    $parentChk->bind_param('i', $parent_item_id);
    $parentChk->execute();
    $parentData = $parentChk->get_result()->fetch_assoc();
    $parentChk->close();

    $item_key = $section . '_' . $parentData['section_number'] . '_' . $section_number;
} else {
    $item_key = $section . '_' . $section_number;
}

// Prevent duplicate item_key
$chk = $conn->prepare("SELECT id FROM checklist_items WHERE item_key = ?");
$chk->bind_param('s', $item_key);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($existing) {
    $conn->close();
    jsonResponse(false, "Item key '{$item_key}' already exists. Choose a different number.");
}

$stmt = $conn->prepare(
    "INSERT INTO checklist_items (section, section_number, description, item_key, swo_type_id, parent_item_id, is_active, created_by)
     VALUES (?, ?, ?, ?, ?, ?, 1, ?)"
);
$stmt->bind_param('sissiis',
    $section,
    $section_number,
    $description,
    $item_key,
    $swo_type_id,
    $parent_item_id,
    $user_id
);

// Handle nullable integer binding properly
$types_str = 'siss';
$bind_params = [$section, $section_number, $description, $item_key];

// Close previous statement and rebuild with proper null handling
$stmt->close();

// Build dynamic query for nullable fields
$sql = "INSERT INTO checklist_items (section, section_number, description, item_key, swo_type_id, parent_item_id, is_active, created_by)
        VALUES (?, ?, ?, ?, " .
        ($swo_type_id !== null ? "?" : "NULL") . ", " .
        ($parent_item_id !== null ? "?" : "NULL") . ", 1, ?)";

$stmt = $conn->prepare($sql);
$bind_types = 'siss';
$bind_values = [$section, $section_number, $description, $item_key];

if ($swo_type_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $swo_type_id;
}
if ($parent_item_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $parent_item_id;
}
$bind_types .= 'i';
$bind_values[] = $user_id;

$stmt->bind_param($bind_types, ...$bind_values);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Database error: ' . $error);
}

$item_id = $stmt->insert_id;
$stmt->close();

logAudit($conn, $user_id, null, "ADD_CHECKLIST_ITEM: {$item_key}", null, 'active');

$conn->close();
jsonResponse(true, 'Checklist item added successfully', ['item_id' => $item_id, 'item_key' => $item_key]);
