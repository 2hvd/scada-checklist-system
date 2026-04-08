<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/../../config/functions.php';

checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonResponse(false, 'Username and password are required');
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, username, password, role, active FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !$user['active']) {
    $conn->close();
    jsonResponse(false, 'Invalid username or password');
}

if (!password_verify($password, $user['password'])) {
    $conn->close();
    jsonResponse(false, 'Invalid username or password');
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['last_activity'] = time();

session_regenerate_id(true);

$conn->close();
jsonResponse(true, 'Login successful', [
    'role' => $user['role'],
    'username' => $user['username']
]);
