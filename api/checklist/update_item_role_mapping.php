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

$item_id = intval($input['item_id'] ?? 0);
$role_input = isset($input['role_config']) && is_array($input['role_config']) ? $input['role_config'] : [];
if ($item_id <= 0) {
    jsonResponse(false, 'Invalid item_id');
}

$role_map = [
    'user' => ['visible' => 1, 'parent_item_id' => null],
    'support' => ['visible' => 1, 'parent_item_id' => null],
    'control' => ['visible' => 1, 'parent_item_id' => null],
];
foreach (['user', 'support', 'control'] as $role) {
    if (!isset($role_input[$role]) || !is_array($role_input[$role])) {
        continue;
    }
    $role_map[$role]['visible'] = !empty($role_input[$role]['visible']) ? 1 : 0;
    if (!empty($role_input[$role]['parent_item_id'])) {
        $role_map[$role]['parent_item_id'] = intval($role_input[$role]['parent_item_id']);
    }
    if ($role_map[$role]['visible'] === 0) {
        $role_map[$role]['parent_item_id'] = null;
    }
}
if (($role_map['user']['visible'] + $role_map['support']['visible'] + $role_map['control']['visible']) === 0) {
    jsonResponse(false, 'At least one role must be enabled');
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT id, section, swo_type_id, parent_item_id, item_key
       FROM checklist_items
      WHERE id = ? AND is_deleted = 0"
);
$stmt->bind_param('i', $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $conn->close();
    jsonResponse(false, 'Checklist item not found');
}

$section = $item['section'];
$swo_type_id = $item['swo_type_id'] !== null ? intval($item['swo_type_id']) : null;
$current_parent = $item['parent_item_id'] !== null ? intval($item['parent_item_id']) : null;

$validatedParents = [];
$firstParentForLegacy = null;
foreach (['user', 'support', 'control'] as $role) {
    $roleParentId = $role_map[$role]['parent_item_id'];
    if ($role_map[$role]['visible'] !== 1 || $roleParentId === null) {
        continue;
    }
    if ($roleParentId === $item_id) {
        $conn->close();
        jsonResponse(false, "Role {$role} parent cannot be the same item");
    }
    if (!isset($validatedParents[$roleParentId])) {
        $chk = $conn->prepare(
            "SELECT id, swo_type_id, section
               FROM checklist_items
              WHERE id = ? AND is_deleted = 0 AND parent_item_id IS NULL"
        );
        $chk->bind_param('i', $roleParentId);
        $chk->execute();
        $validatedParents[$roleParentId] = $chk->get_result()->fetch_assoc() ?: null;
        $chk->close();
    }
    $parent = $validatedParents[$roleParentId];
    if (!$parent) {
        $conn->close();
        jsonResponse(false, "Invalid parent item selected for {$role}");
    }
    $parentTypeId = $parent['swo_type_id'] !== null ? intval($parent['swo_type_id']) : null;
    if ($parent['section'] !== $section) {
        $conn->close();
        jsonResponse(false, "Role {$role} parent must be in the same section");
    }
    if ($parentTypeId !== $swo_type_id) {
        $conn->close();
        jsonResponse(false, "Role {$role} parent must match item SWO type");
    }
    if ($firstParentForLegacy === null) {
        $firstParentForLegacy = intval($parent['id']);
    }
}

$legacy_parent_item_id = $current_parent;
if ($legacy_parent_item_id === null) {
    $legacy_parent_item_id = $firstParentForLegacy;
}
if ($firstParentForLegacy === null) {
    $legacy_parent_item_id = null;
}

$visible_user = $role_map['user']['visible'];
$visible_support = $role_map['support']['visible'];
$visible_control = $role_map['control']['visible'];
$user_parent_item_id = $role_map['user']['visible'] ? $role_map['user']['parent_item_id'] : null;
$support_parent_item_id = $role_map['support']['visible'] ? $role_map['support']['parent_item_id'] : null;
$control_parent_item_id = $role_map['control']['visible'] ? $role_map['control']['parent_item_id'] : null;

$sql = "UPDATE checklist_items
           SET visible_user = ?,
               visible_support = ?,
               visible_control = ?,
               user_parent_item_id = " . ($user_parent_item_id !== null ? "?" : "NULL") . ",
               support_parent_item_id = " . ($support_parent_item_id !== null ? "?" : "NULL") . ",
               control_parent_item_id = " . ($control_parent_item_id !== null ? "?" : "NULL") . ",
               parent_item_id = " . ($legacy_parent_item_id !== null ? "?" : "NULL") . "
         WHERE id = ?";
$stmt = $conn->prepare($sql);

$bind_types = 'iii';
$bind_values = [$visible_user, $visible_support, $visible_control];
if ($user_parent_item_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $user_parent_item_id;
}
if ($support_parent_item_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $support_parent_item_id;
}
if ($control_parent_item_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $control_parent_item_id;
}
if ($legacy_parent_item_id !== null) {
    $bind_types .= 'i';
    $bind_values[] = $legacy_parent_item_id;
}
$bind_types .= 'i';
$bind_values[] = $item_id;

$stmt->bind_param($bind_types, ...$bind_values);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    jsonResponse(false, 'Database error: ' . $err);
}
$stmt->close();

logAudit($conn, $user_id, null, "UPDATE_CHECKLIST_ROLE_MAPPING: {$item['item_key']}", null, 'updated');

$conn->close();
jsonResponse(true, 'Role mapping updated successfully');
