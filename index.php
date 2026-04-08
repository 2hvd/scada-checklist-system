<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: views/admin/index.php');
    } elseif ($role === 'support') {
        header('Location: views/support/index.php');
    } else {
        header('Location: views/user/index.php');
    }
    exit;
}

$timeout = isset($_GET['timeout']) ? true : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCADA Checklist System - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-body {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d1f33 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            padding: 48px 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo .logo-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
        }
        .login-logo h1 {
            color: #1a3a5c;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }
        .login-logo p {
            color: #666;
            font-size: 13px;
            margin: 4px 0 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
            box-sizing: border-box;
            outline: none;
        }
        .form-group input:focus {
            border-color: #2e86de;
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #2e86de;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: #1a6db5;
        }
        .btn-login:disabled {
            background: #90bfe8;
            cursor: not-allowed;
        }
        .error-msg {
            background: #fde8e8;
            color: #c0392b;
            border: 1px solid #f5c6c6;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 14px;
            display: none;
        }
        .timeout-msg {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-logo">
            <span class="logo-icon">⚙️</span>
            <h1>SCADA Checklist System</h1>
            <p>Secure Access Portal</p>
        </div>

        <?php if ($timeout): ?>
        <div class="timeout-msg">⚠️ Your session has expired. Please login again.</div>
        <?php endif; ?>

        <div class="error-msg" id="errorMsg"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login" id="loginBtn">Sign In</button>
        </form>

        <div class="login-footer">SCADA Checklist System &copy; <?php echo date('Y'); ?></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const errorMsg = document.getElementById('errorMsg');
            const loginBtn = document.getElementById('loginBtn');

            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;

                if (!username || !password) {
                    showError('Please enter username and password.');
                    return;
                }

                loginBtn.disabled = true;
                loginBtn.textContent = 'Signing in...';
                errorMsg.style.display = 'none';

                try {
                    const response = await fetch('api/auth/login.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({username, password})
                    });
                    const data = await response.json();
                    if (data.success) {
                        const role = data.data.role;
                        if (role === 'admin') {
                            window.location.href = 'views/admin/index.php';
                        } else if (role === 'support') {
                            window.location.href = 'views/support/index.php';
                        } else {
                            window.location.href = 'views/user/index.php';
                        }
                    } else {
                        showError(data.message || 'Login failed. Check credentials.');
                        loginBtn.disabled = false;
                        loginBtn.textContent = 'Sign In';
                    }
                } catch (err) {
                    showError('Connection error. Please try again.');
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Sign In';
                }
            });

            function showError(msg) {
                errorMsg.textContent = msg;
                errorMsg.style.display = 'block';
            }
        });
    </script>
</body>
</html>
