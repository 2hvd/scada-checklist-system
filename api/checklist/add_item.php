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
    $chk = $conn->prepare("SELECT id, swo_type_id, section, section_number FROM checklist_items WHERE id = ? AND is_deleted = 0 AND parent_item_id IS NULL");
    $chk->bind_param('i', $parent_item_id);
    $chk->execute();
    $parent = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$parent) {
        $conn->close();
        jsonResponse(false, 'Invalid parent item (must be a top-level item)');
    }

    $parentTypeId = $parent['swo_type_id'] !== null ? intval($parent['swo_type_id']) : null;

    // Sub-item must have the same swo_type_id as the parent
    if ($swo_type_id !== null && $parentTypeId !== $swo_type_id) {
        $conn->close();
        jsonResponse(false, 'Sub-item must have the same SWO type as the parent item');
    }
    // If not provided, inherit parent's type
    if ($swo_type_id === null) {
        $swo_type_id = $parentTypeId;
    }

    // Sub-item must be in the same section as parent
    if ($parent['section'] !== $section) {
        $conn->close();
        jsonResponse(false, 'Sub-item must be in the same section as the parent item');
    }
}

// Prevent duplicate numbering in the same context
if ($parent_item_id !== null) {
    $dup = $conn->prepare(
        "SELECT id
           FROM checklist_items
          WHERE is_deleted = 0
            AND parent_item_id = ?
            AND section_number = ?"
    );
    $dup->bind_param('ii', $parent_item_id, $section_number);
} else {
    if ($swo_type_id !== null) {
        $dup = $conn->prepare(
            "SELECT id
               FROM checklist_items
              WHERE is_deleted = 0
                AND parent_item_id IS NULL
                AND section = ?
                AND section_number = ?
                AND swo_type_id = ?"
        );
        $dup->bind_param('sii', $section, $section_number, $swo_type_id);
    } else {
        $dup = $conn->prepare(
            "SELECT id
               FROM checklist_items
              WHERE is_deleted = 0
                AND parent_item_id IS NULL
                AND section = ?
                AND section_number = ?
                AND swo_type_id IS NULL"
        );
        $dup->bind_param('si', $section, $section_number);
    }
}
$dup->execute();
$existing = $dup->get_result()->fetch_assoc();
$dup->close();

if ($existing) {
    $conn->close();
    jsonResponse(false, 'This number is already used in the selected context. Choose a different number.');
}

// Generate item_key based on hierarchy + SWO type context
if ($parent_item_id !== null) {
    $baseKey = $section . '_' . intval($parent['section_number']) . '_' . $section_number;
} else {
    $baseKey = $section . '_' . $section_number;
}
$item_key = $swo_type_id !== null ? ($baseKey . '_t' . $swo_type_id) : $baseKey;

// Ensure key uniqueness even for legacy collisions
$keyCandidate = $item_key;
$suffix = 1;
// Cap fallback key attempts to avoid excessive DB loops on unexpected key collisions.
$maxSuffixAttempts = 100;
$keyChk = $conn->prepare("SELECT id FROM checklist_items WHERE item_key = ?");
while ($suffix <= $maxSuffixAttempts) {
    $keyChk->bind_param('s', $keyCandidate);
    $keyChk->execute();
    $exists = $keyChk->get_result()->fetch_assoc();
    if (!$exists) {
        break;
    }
    $suffix++;
    $keyCandidate = $item_key . '_' . $suffix;
}
$keyChk->close();
$maxAttemptsExceeded = ($suffix > $maxSuffixAttempts);
if ($maxAttemptsExceeded) {
    $conn->close();
    jsonResponse(false, 'Unable to generate unique item key. Please try another number.');
}
$item_key = $keyCandidate;

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
