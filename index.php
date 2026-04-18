<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: views/admin/index.php');
    } elseif ($role === 'support') {
        header('Location: views/support/index.php');
    } elseif ($role === 'control') {
        header('Location: views/control/index.php');
    } else {
        header('Location: views/user/index.php');
    }
    exit;
}

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
            background: linear-gradient(135deg, #0c1527 0%, #162036 40%, #1a2744 70%, #0c1527 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            position: relative;
            overflow: hidden;
        }
        body.login-body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 20% 50%, rgba(99,102,241,0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(139,92,246,0.06) 0%, transparent 50%),
                        radial-gradient(ellipse at 60% 80%, rgba(6,182,212,0.05) 0%, transparent 50%);
            animation: bgFloat 20s ease-in-out infinite;
        }
        @keyframes bgFloat {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(2%, -1%) rotate(1deg); }
            66% { transform: translate(-1%, 2%) rotate(-1deg); }
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: loginSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes loginSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #818cf8, #8b5cf6, #6366f1);
            background-size: 300% 100%;
            border-radius: 24px 24px 0 0;
            animation: gradientSlide 4s ease infinite;
        }
        @keyframes gradientSlide {
            0%, 100% { background-position: 0% 0%; }
            50% { background-position: 100% 0%; }
        }
        .login-logo {
            text-align: center;
            margin-bottom: 36px;
        }
        .login-logo .logo-icon {
            font-size: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(99,102,241,0.15);
        }
        .login-logo h1 {
            color: #0c1527;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .login-logo p {
            color: #64748b;
            font-size: 13px;
            margin: 6px 0 0;
            font-weight: 500;
        }
        .login-container .form-group {
            margin-bottom: 20px;
        }
        .login-container .form-group label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
            font-size: 13px;
            letter-spacing: 0.01em;
        }
        .login-container .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
            outline: none;
            background: #fff;
            color: #1e293b;
            font-family: inherit;
        }
        .login-container .form-group input::placeholder {
            color: #94a3b8;
        }
        .login-container .form-group input:hover {
            border-color: #cbd5e1;
        }
        .login-container .form-group input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            font-family: inherit;
            letter-spacing: 0.01em;
            box-shadow: 0 4px 14px rgba(99,102,241,0.35);
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            box-shadow: 0 6px 20px rgba(99,102,241,0.4);
            transform: translateY(-1px);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .btn-login:disabled {
            background: linear-gradient(135deg, #a5b4fc, #818cf8);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .error-msg {
            background: linear-gradient(135deg, #fee2e2, #fef2f2);
            color: #dc2626;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            display: none;
            font-weight: 500;
        }
        .login-footer {
            text-align: center;
            margin-top: 28px;
            color: #94a3b8;
            font-size: 12px;
        }
        @media (max-width: 480px) {
            .login-container {
                margin: 16px;
                padding: 32px 24px;
                border-radius: 20px;
            }
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
                        } else if (role === 'control') {
                            window.location.href = 'views/control/index.php';
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
