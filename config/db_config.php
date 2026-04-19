<?php
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'scada_checklist');
define('SQLITE_PATH', getenv('SQLITE_PATH') ?: (__DIR__ . '/../database/scada_checklist.sqlite'));

function getDBConnection() {
    if (strtolower(DB_DRIVER) === 'sqlite') {
        require_once __DIR__ . '/sqlite_compat.php';
        $conn = new SQLiteCompatConnection(SQLITE_PATH);
        if (!empty($conn->connect_error)) {
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
