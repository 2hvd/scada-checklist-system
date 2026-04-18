/* api.js - Core API utility */
const API = {
    baseUrl: '/scada-checklist-system/api',

    async get(endpoint, params = {}) {
        const url = new URL(this.baseUrl + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.append(k, v));
        const response = await fetch(url.toString());
        if (response.status === 401 || response.redirected) {
            window.location.href = '/scada-checklist-system/index.php';
            return;
        }
        return response.json();
    },

    async post(endpoint, data = {}) {
        const response = await fetch(this.baseUrl + endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        if (response.status === 401 || response.redirected) {
            window.location.href = '/scada-checklist-system/index.php';
            return;
        }
        return response.json();
    },

    async logout() {
        await this.post('/auth/logout.php');
        window.location.href = '/scada-checklist-system/index.php';
    }
};

// Global logout handler
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            API.logout();
        });
    }
});
