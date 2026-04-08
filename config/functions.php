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
    $timeout = 15 * 60; // 15 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            jsonResponse(false, 'Session expired. Please login again.');
        } else {
            header('Location: /scada-checklist-system/index.php?timeout=1');
            exit;
        }
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
