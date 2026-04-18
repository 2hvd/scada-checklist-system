<?php
require_once __DIR__ . '/db_config.php';

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function logAudit($conn, $user_id, $swo_id, $action, $old_status = null, $new_status = null) {
    $stmt = $conn->prepare(
        "INSERT INTO audit_log (user_id, swo_id, action, old_status, new_status) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iisss', $user_id, $swo_id, $action, $old_status, $new_status);
    $stmt->execute();
    $stmt->close();
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    checkSessionTimeout();
    if (empty($_SESSION['user_id'])) {
        header('Location: /scada-checklist-system/index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    $roles = is_array($role) ? $role : [$role];
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /scada-checklist-system/index.php');
        exit;
    }
}

function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

function checkSessionTimeout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function getChecklistItems() {
    return [
        'during_config' => [
            'label' => 'During Configuration',
            'items' => [
                'during_config_1' => 'Verify PLC communication settings',
                'during_config_2' => 'Configure I/O mapping',
                'during_config_3' => 'Set up HMI screens',
                'during_config_4' => 'Configure alarm setpoints',
                'during_config_5' => 'Test network connectivity',
                'during_config_6' => 'Configure historian settings',
                'during_config_7' => 'Set up redundancy parameters',
                'during_config_8' => 'Configure security settings',
            ]
        ],
        'during_commissioning' => [
            'label' => 'During Commissioning',
            'items' => [
                'during_commissioning_1' => 'Perform I/O loop checks',
                'during_commissioning_2' => 'Test control logic',
                'during_commissioning_3' => 'Verify HMI functionality',
                'during_commissioning_4' => 'Test alarm system',
                'during_commissioning_5' => 'Validate historian data logging',
                'during_commissioning_6' => 'Test redundancy failover',
                'during_commissioning_7' => 'Perform communication stress test',
                'during_commissioning_8' => 'Document as-built configuration',
            ]
        ],
        'after_commissioning' => [
            'label' => 'After Commissioning',
            'items' => [
                'after_commissioning_1' => 'Verify system performance under load',
                'after_commissioning_2' => 'Complete operator training',
                'after_commissioning_3' => 'Finalize documentation',
                'after_commissioning_4' => 'Archive project files',
                'after_commissioning_5' => 'Transfer system to operations',
                'after_commissioning_6' => 'Perform 24-hour monitoring',
                'after_commissioning_7' => 'Resolve punch list items',
                'after_commissioning_8' => 'Obtain client sign-off',
            ]
        ]
    ];
}

function getAllItemKeys() {
    $keys = [];
    foreach (getChecklistItems() as $section) {
        foreach (array_keys($section['items']) as $key) {
            $keys[] = $key;
        }
    }
    return $keys;
}

/**
 * Load active checklist items from DB, structured like getChecklistItems().
 * Falls back to hardcoded list if the table doesn't exist or is empty.
 * Optionally filter by swo_type_id.
 */
function getChecklistItemsFromDB($conn, $swo_type_id = null) {
    $sectionLabels = [
        'during_config'       => 'During Configuration',
        'during_commissioning'=> 'During Commissioning',
        'after_commissioning' => 'After Commissioning',
    ];

    if ($swo_type_id !== null) {
        $stmt = $conn->prepare(
            "SELECT id, section, section_number, item_key, description, parent_item_id
               FROM checklist_items
              WHERE is_active = 1 AND is_deleted = 0
                AND (swo_type_id = ? OR swo_type_id IS NULL)
              ORDER BY section, parent_item_id IS NOT NULL, parent_item_id, section_number"
        );
        $stmt->bind_param('i', $swo_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query(
            "SELECT id, section, section_number, item_key, description, parent_item_id
               FROM checklist_items
              WHERE is_active = 1 AND is_deleted = 0
              ORDER BY section, parent_item_id IS NOT NULL, parent_item_id, section_number"
        );
    }

    if (!$result || $result->num_rows === 0) {
        if (isset($stmt)) $stmt->close();
        return getChecklistItems();
    }

    $structured = [];
    while ($row = $result->fetch_assoc()) {
        $sec = $row['section'];
        if (!isset($structured[$sec])) {
            $structured[$sec] = [
                'label' => $sectionLabels[$sec] ?? ucwords(str_replace('_', ' ', $sec)),
                'items' => [],
            ];
        }
        $structured[$sec]['items'][$row['item_key']] = $row['description'];
    }
    if (isset($stmt)) $stmt->close();

    return $structured ?: getChecklistItems();
}

/**
 * Load checklist items for a specific SWO:
 * returns all active items PLUS any items that already have saved data
 * in checklist_status for this SWO (even if since deactivated).
 * Filters by the SWO's swo_type_id if set.
 * Falls back to getChecklistItemsFromDB() if no rows are found.
 */
function getChecklistItemsForSWO($conn, $swo_id) {
    $sectionLabels = [
        'during_config'        => 'During Configuration',
        'during_commissioning' => 'During Commissioning',
        'after_commissioning'  => 'After Commissioning',
    ];

    // Get the SWO's type
    $typeStmt = $conn->prepare("SELECT swo_type_id FROM swo_list WHERE id = ?");
    $typeStmt->bind_param('i', $swo_id);
    $typeStmt->execute();
    $swoData = $typeStmt->get_result()->fetch_assoc();
    $typeStmt->close();
    $swo_type_id = $swoData ? ($swoData['swo_type_id'] ? intval($swoData['swo_type_id']) : null) : null;

    if ($swo_type_id !== null) {
        $stmt = $conn->prepare(
            "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id
               FROM checklist_items ci
               LEFT JOIN checklist_status cs ON ci.item_key = cs.item_key AND cs.swo_id = ?
              WHERE ((ci.is_active = 1 AND ci.is_deleted = 0 AND (ci.swo_type_id = ? OR ci.swo_type_id IS NULL))
                 OR (cs.id IS NOT NULL))
              GROUP BY ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id
              ORDER BY ci.section, ci.parent_item_id IS NOT NULL, ci.parent_item_id, ci.section_number"
        );
        $stmt->bind_param('ii', $swo_id, $swo_type_id);
    } else {
        $stmt = $conn->prepare(
            "SELECT ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id
               FROM checklist_items ci
               LEFT JOIN checklist_status cs ON ci.item_key = cs.item_key AND cs.swo_id = ?
              WHERE (ci.is_active = 1 AND ci.is_deleted = 0)
                 OR (cs.id IS NOT NULL)
              GROUP BY ci.id, ci.section, ci.section_number, ci.item_key, ci.description, ci.parent_item_id
              ORDER BY ci.section, ci.parent_item_id IS NOT NULL, ci.parent_item_id, ci.section_number"
        );
        $stmt->bind_param('i', $swo_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        return getChecklistItemsFromDB($conn, $swo_type_id);
    }

    $structured = [];
    while ($row = $result->fetch_assoc()) {
        $sec = $row['section'];
        if (!isset($structured[$sec])) {
            $structured[$sec] = [
                'label' => $sectionLabels[$sec] ?? ucwords(str_replace('_', ' ', $sec)),
                'items' => [],
            ];
        }
        $structured[$sec]['items'][$row['item_key']] = $row['description'];
    }
    $stmt->close();

    return $structured;
}

/**
 * Return active item keys from DB, falling back to hardcoded.
 */
function getAllItemKeysFromDB($conn, $swo_type_id = null) {
    $keys = [];
    if ($swo_type_id !== null) {
        $stmt = $conn->prepare(
            "SELECT item_key
               FROM checklist_items
              WHERE is_active = 1
                AND is_deleted = 0
                AND (swo_type_id = ? OR swo_type_id IS NULL)
              ORDER BY section, section_number"
        );
        $stmt->bind_param('i', $swo_type_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query(
            "SELECT item_key FROM checklist_items WHERE is_active = 1 AND is_deleted = 0 ORDER BY section, section_number"
        );
    }

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $keys[] = $row['item_key'];
        }
        if (isset($stmt)) {
            $stmt->close();
        }
        return $keys;
    }

    if (isset($stmt)) {
        $stmt->close();
    }
    return getAllItemKeys();
}

/**
 * Load item labels (key => description) for a specific set of keys.
 * Used by get_checklist.php to resolve labels for existing SWO items.
 */
function getItemLabelsForKeys($conn, array $keys) {
    if (empty($keys)) {
        return [];
    }

    // Build label map from DB
    $dbLabels = [];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $conn->prepare(
        "SELECT item_key, description, section, section_number FROM checklist_items WHERE item_key IN ($placeholders)"
    );
    $types = str_repeat('s', count($keys));
    $stmt->bind_param($types, ...$keys);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dbLabels[$row['item_key']] = [
            'description'    => $row['description'],
            'section'        => $row['section'],
            'section_number' => $row['section_number'],
        ];
    }
    $stmt->close();

    // Fall back to hardcoded for any key not found in DB
    $hardcoded = [];
    foreach (getChecklistItems() as $secKey => $sec) {
        foreach ($sec['items'] as $k => $label) {
            $hardcoded[$k] = ['description' => $label, 'section' => $secKey, 'section_number' => null];
        }
    }

    $result = [];
    foreach ($keys as $k) {
        $result[$k] = $dbLabels[$k] ?? ($hardcoded[$k] ?? ['description' => $k, 'section' => 'unknown', 'section_number' => null]);
    }
    return $result;
}
