<?php
/**
 * setup_passwords.php
 * Run this ONCE after importing database/sample_data.sql
 * to set correct bcrypt password hashes for test accounts.
 *
 * Access: http://localhost/scada-checklist-system/setup_passwords.php
 * DELETE this file after running it in production!
 */

// Simple guard - only run from localhost
$allowed = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed) && !in_array($_SERVER['HTTP_HOST'] ?? '', $allowed)) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

require_once __DIR__ . '/config/db_config.php';

$accounts = [
    ['username' => 'admin',   'password' => 'admin123'],
    ['username' => 'support', 'password' => 'support123'],
    ['username' => 'user1',   'password' => 'user123'],
];

$conn = getDBConnection();
$results = [];

foreach ($accounts as $account) {
    $hash = password_hash($account['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param('ss', $hash, $account['username']);
    $stmt->execute();
    $results[] = [
        'username' => $account['username'],
        'affected' => $stmt->affected_rows,
        'status'   => $stmt->affected_rows > 0 ? 'Updated' : 'Not found'
    ];
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Setup Passwords</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; }
        h1 { color: #1a3a5c; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #1a3a5c; color: #fff; }
        .success { color: #27ae60; font-weight: 600; }
        .warning { background: #fff3cd; padding: 12px; border-radius: 6px; border-left: 4px solid #f39c12; margin: 20px 0; }
        a { color: #2e86de; }
    </style>
</head>
<body>
    <h1>⚙️ SCADA Checklist System — Password Setup</h1>
    <table>
        <thead><tr><th>Username</th><th>Password</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['username']); ?></strong></td>
                <td><?php
                    $pw = ['admin'=>'admin123','support'=>'support123','user1'=>'user123'];
                    echo htmlspecialchars($pw[$r['username']] ?? '—');
                ?></td>
                <td class="success"><?php echo htmlspecialchars($r['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="warning">
        ⚠️ <strong>Security Notice:</strong> Delete <code>setup_passwords.php</code> after use in production environments.
    </div>
    <p>✅ Passwords set. <a href="/scada-checklist-system/index.php">Go to Login →</a></p>
</body>
</html>
