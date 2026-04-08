<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/functions.php';

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, 'Not logged in');
}

$timeout = 15 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    jsonResponse(false, 'Session expired');
}

$_SESSION['last_activity'] = time();

jsonResponse(true, 'Session active', [
    'user_id'  => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role'     => $_SESSION['role']
]);
