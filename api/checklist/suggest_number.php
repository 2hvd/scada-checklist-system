<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('admin');

$section = trim($_GET['section'] ?? '');
$swo_type_id = !empty($_GET['swo_type_id']) ? intval($_GET['swo_type_id']) : null;
$parent_item_id = !empty($_GET['parent_item_id']) ? intval($_GET['parent_item_id']) : null;

$validSections = ['during_config', 'during_commissioning', 'after_commissioning'];
if (!in_array($section, $validSections, true)) {
    jsonResponse(false, 'Invalid section');
}

$conn = getDBConnection();

if ($swo_type_id !== null) {
    $typeChk = $conn->prepare("SELECT id FROM swo_types WHERE id = ? AND is_active = 1");
    $typeChk->bind_param('i', $swo_type_id);
    $typeChk->execute();
    $typeData = $typeChk->get_result()->fetch_assoc();
    $typeChk->close();
    if (!$typeData) {
        $conn->close();
        jsonResponse(false, 'Invalid SWO type');
    }
}

$contextTypeId = $swo_type_id;
$suggested = 1;

if ($parent_item_id !== null) {
    $parentStmt = $conn->prepare(
        "SELECT id, section, swo_type_id, section_number
           FROM checklist_items
          WHERE id = ? AND is_deleted = 0 AND parent_item_id IS NULL"
    );
    $parentStmt->bind_param('i', $parent_item_id);
    $parentStmt->execute();
    $parent = $parentStmt->get_result()->fetch_assoc();
    $parentStmt->close();

    if (!$parent) {
        $conn->close();
        jsonResponse(false, 'Invalid parent item');
    }
    if ($parent['section'] !== $section) {
        $conn->close();
        jsonResponse(false, 'Parent item section mismatch');
    }

    $contextTypeId = $parent['swo_type_id'] !== null ? intval($parent['swo_type_id']) : null;
    if ($swo_type_id !== null && $contextTypeId !== $swo_type_id) {
        $conn->close();
        jsonResponse(false, 'Parent item SWO type mismatch');
    }

    $maxStmt = $conn->prepare(
        "SELECT MAX(section_number) AS max_number
           FROM checklist_items
          WHERE is_deleted = 0 AND parent_item_id = ?"
    );
    $maxStmt->bind_param('i', $parent_item_id);
    $maxStmt->execute();
    $maxRow = $maxStmt->get_result()->fetch_assoc();
    $maxStmt->close();

    $suggested = intval($maxRow['max_number'] ?? 0) + 1;
} else {
    if ($contextTypeId !== null) {
        $maxStmt = $conn->prepare(
            "SELECT MAX(section_number) AS max_number
               FROM checklist_items
              WHERE is_deleted = 0
                AND parent_item_id IS NULL
                AND section = ?
                AND swo_type_id = ?"
        );
        $maxStmt->bind_param('si', $section, $contextTypeId);
    } else {
        $maxStmt = $conn->prepare(
            "SELECT MAX(section_number) AS max_number
               FROM checklist_items
              WHERE is_deleted = 0
                AND parent_item_id IS NULL
                AND section = ?
                AND swo_type_id IS NULL"
        );
        $maxStmt->bind_param('s', $section);
    }
    $maxStmt->execute();
    $maxRow = $maxStmt->get_result()->fetch_assoc();
    $maxStmt->close();
    $suggested = intval($maxRow['max_number'] ?? 0) + 1;
}

$conn->close();

jsonResponse(true, 'Suggested number retrieved', [
    'suggested_number' => $suggested,
    'swo_type_id' => $contextTypeId,
    'section' => $section,
    'parent_item_id' => $parent_item_id,
]);
