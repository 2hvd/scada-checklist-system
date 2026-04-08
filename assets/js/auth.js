/* auth.js - Login handler */
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const errorMsg = document.getElementById('errorMsg');
        const loginBtn = document.getElementById('loginBtn');

        if (!username || !password) {
            showError('Please enter username and password.');
            return;
        }

        loginBtn.disabled = true;
        loginBtn.textContent = 'Signing in...';
        if (errorMsg) errorMsg.style.display = 'none';

        try {
            const response = await fetch('/scada-checklist-system/api/auth/login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username, password})
            });
            const data = await response.json();

            if (data.success) {
                const role = data.data.role;
                if (role === 'admin') {
                    window.location.href = '/scada-checklist-system/views/admin/index.php';
                } else if (role === 'support') {
                    window.location.href = '/scada-checklist-system/views/support/index.php';
                } else {
                    window.location.href = '/scada-checklist-system/views/user/index.php';
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
        const errorMsg = document.getElementById('errorMsg');
        if (errorMsg) {
            errorMsg.textContent = msg;
            errorMsg.style.display = 'block';
        }
    }
});
