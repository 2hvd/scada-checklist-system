/* dashboard.js - Dashboard logic */

// Admin Dashboard
const AdminDashboard = {
    async init() {
        this.loadStats();
        this.loadSWOTable();
        this.initFilters();
        initTabs('mainTabs');

        // Check for pending count badge
        setInterval(() => this.updatePendingBadge(), 30000);
    },

    async loadStats() {
        const container = document.getElementById('adminStatsContainer');
        if (!container) return;

        try {
            const data = await API.get('/dashboard/admin_stats.php');
            if (!data || !data.success) return;

            const sc = data.data.status_counts || {};
            const totalSwos = Object.values(sc).reduce((a, b) => a + b, 0);
            const pending = sc['Pending'] || 0;
            const inProgress = sc['In Progress'] || 0;
            const submitted = sc['Submitted'] || 0;

            document.getElementById('statTotal').textContent = totalSwos;
            document.getElementById('statPending').textContent = pending;
            document.getElementById('statInProgress').textContent = inProgress;
            document.getElementById('statSubmitted').textContent = submitted;

            // Update pending badge
            const badge = document.getElementById('pendingBadge');
            if (badge) {
                badge.textContent = pending;
                badge.style.display = pending > 0 ? 'inline-block' : 'none';
            }

            // Render user cards
            this.renderUserCards(data.data.user_stats || []);
            this.renderRecentActivity(data.data.recent_activity || []);
        } catch (err) {
            console.error('Failed to load admin stats:', err);
        }
    },

    renderUserCards(users) {
        const grid = document.getElementById('userCardsGrid');
        if (!grid) return;

        if (!users.length) {
            grid.innerHTML = '<div class="empty-state"><span class="empty-icon">👤</span><p>No system users found.</p></div>';
            return;
        }

        grid.innerHTML = users.map(u => `
            <div class="user-card" onclick="AdminDashboard.viewUserDetails(${u.id}, '${escapeHtml(u.username)}')">
                <div class="user-card-header">
                    <div class="user-card-avatar">${escapeHtml(u.username.charAt(0).toUpperCase())}</div>
                    <div>
                        <div class="user-card-name">${escapeHtml(u.username)}</div>
                        <div class="user-card-meta">System User</div>
                    </div>
                </div>
                <div class="user-card-stats">
                    <div class="user-card-stat">
                        <div class="val">${u.total_assigned}</div>
                        <div class="lbl">Assigned</div>
                    </div>
                    <div class="user-card-stat">
                        <div class="val">${u.in_progress}</div>
                        <div class="lbl">In Progress</div>
                    </div>
                    <div class="user-card-stat">
                        <div class="val">${u.submitted}</div>
                        <div class="lbl">Submitted</div>
                    </div>
                </div>
                ${renderProgressBar(u.completion_pct)}
            </div>
        `).join('');
    },

    renderRecentActivity(activities) {
        const el = document.getElementById('recentActivityList');
        if (!el) return;
        if (!activities.length) {
            el.innerHTML = '<div class="empty-state"><span class="empty-icon">📋</span><p>No recent activity.</p></div>';
            return;
        }
        el.innerHTML = activities.map(a => `
            <div style="padding:8px 0;border-bottom:1px solid #eee;font-size:13px;">
                <strong>${escapeHtml(a.username || 'System')}</strong>
                — ${escapeHtml(a.action)}
                ${a.swo_number ? `on <span style="color:#2e86de">${escapeHtml(a.swo_number)}</span>` : ''}
                <span class="text-muted" style="float:right">${formatDate(a.timestamp)}</span>
            </div>
        `).join('');
    },

    async loadSWOTable(statusFilter = '') {
        const tbody = document.getElementById('swoTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const data = await API.get('/swo/get_swo_list.php');
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Failed to load SWOs.</td></tr>';
                return;
            }

            let swos = data.data || [];
            if (statusFilter) swos = swos.filter(s => s.status === statusFilter);

            if (!swos.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No SWOs found.</td></tr>';
                return;
            }

            tbody.innerHTML = swos.map(s => {
                const actions = this.buildSWOActions(s);
                return `
                    <tr>
                        <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                        <td>${escapeHtml(s.station_name)}</td>
                        <td>${escapeHtml(s.swo_type)}</td>
                        <td>${getStatusBadge(s.status)}</td>
                        <td>${escapeHtml(s.created_by_name || '—')}</td>
                        <td>${escapeHtml(s.assigned_to_name || '—')}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading SWOs.</td></tr>';
        }
    },

    buildSWOActions(swo) {
        let html = `<div style="display:flex;gap:6px;flex-wrap:wrap;">`;
        if (swo.status === 'Pending') {
            html += `<button class="btn btn-success btn-sm" onclick="AdminDashboard.approveSWO(${swo.id})">Approve</button>`;
            html += `<button class="btn btn-danger btn-sm" onclick="AdminDashboard.showRejectModal(${swo.id})">Reject</button>`;
        }
        if (swo.status === 'Registered') {
            html += `<button class="btn btn-primary btn-sm" onclick="AdminDashboard.showAssignModal(${swo.id})">Assign</button>`;
        }
        html += `<button class="btn btn-secondary btn-sm" onclick="AdminDashboard.viewSWO(${swo.id})">View</button>`;
        html += '</div>';
        return html;
    },

    async approveSWO(swoId) {
        const confirmed = await confirmDialog('Approve this SWO? It will become Registered.');
        if (!confirmed) return;
        const data = await API.post('/swo/approve_swo.php', {swo_id: swoId});
        if (data && data.success) {
            showSuccess('SWO approved successfully');
            this.loadSWOTable();
            this.loadStats();
        } else {
            showError(data?.message || 'Failed to approve SWO');
        }
    },

    showRejectModal(swoId) {
        document.getElementById('rejectSwoId').value = swoId;
        document.getElementById('rejectReason').value = '';
        openModal('rejectModal');
    },

    async submitReject() {
        const swoId = document.getElementById('rejectSwoId').value;
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) { showWarning('Please enter a rejection reason.'); return; }
        const data = await API.post('/swo/reject_swo.php', {swo_id: swoId, reason});
        if (data && data.success) {
            showSuccess('SWO rejected');
            closeModal('rejectModal');
            this.loadSWOTable();
            this.loadStats();
        } else {
            showError(data?.message || 'Failed to reject');
        }
    },

    async showAssignModal(swoId) {
        document.getElementById('assignSwoId').value = swoId;
        // Load users
        const select = document.getElementById('assignUserSelect');
        select.innerHTML = '<option value="">Loading...</option>';
        openModal('assignModal');

        try {
            const resp = await fetch('/scada-checklist-system/api/dashboard/admin_stats.php');
            const data = await resp.json();
            const users = data.data?.user_stats || [];
            select.innerHTML = '<option value="">-- Select User --</option>' +
                users.map(u => `<option value="${u.id}">${escapeHtml(u.username)}</option>`).join('');
        } catch {
            select.innerHTML = '<option value="">Error loading users</option>';
        }
    },

    async submitAssign() {
        const swoId = document.getElementById('assignSwoId').value;
        const userId = document.getElementById('assignUserSelect').value;
        if (!userId) { showWarning('Please select a user.'); return; }
        const data = await API.post('/swo/assign_swo.php', {swo_id: swoId, user_id: userId});
        if (data && data.success) {
            showSuccess('SWO assigned successfully');
            closeModal('assignModal');
            this.loadSWOTable();
            this.loadStats();
        } else {
            showError(data?.message || 'Failed to assign');
        }
    },

    async viewSWO(swoId) {
        const data = await API.get('/swo/get_swo_details.php', {swo_id: swoId});
        if (!data || !data.success) { showError('Failed to load SWO details'); return; }
        const s = data.data.swo;
        document.getElementById('viewSwoContent').innerHTML = `
            <table style="width:100%;font-size:14px;">
                <tr><td style="padding:6px;font-weight:600;width:40%">SWO Number</td><td>${escapeHtml(s.swo_number)}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Station</td><td>${escapeHtml(s.station_name)}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Type</td><td>${escapeHtml(s.swo_type)}</td></tr>
                <tr><td style="padding:6px;font-weight:600">KCOR</td><td>${escapeHtml(s.kcor || '—')}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Status</td><td>${getStatusBadge(s.status)}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Created By</td><td>${escapeHtml(s.created_by_name || '—')}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Assigned To</td><td>${escapeHtml(s.assigned_to_name || '—')}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Description</td><td>${escapeHtml(s.description || '—')}</td></tr>
                <tr><td style="padding:6px;font-weight:600">Created</td><td>${formatDate(s.created_at)}</td></tr>
            </table>
        `;
        openModal('viewSwoModal');
    },

    viewUserDetails(userId, username) {
        window.location.href = '/scada-checklist-system/views/admin/statistics.php?user_id=' + userId;
    },

    updatePendingBadge() {
        API.get('/dashboard/admin_stats.php').then(data => {
            if (!data || !data.success) return;
            const pending = data.data.status_counts?.['Pending'] || 0;
            const badge = document.getElementById('pendingBadge');
            if (badge) {
                badge.textContent = pending;
                badge.style.display = pending > 0 ? 'inline-block' : 'none';
            }
        });
    },

    initFilters() {
        const filter = document.getElementById('statusFilter');
        if (filter) {
            filter.addEventListener('change', () => this.loadSWOTable(filter.value));
        }
    }
};

// Support Dashboard (legacy - now handled by support_dashboard.js)
const SupportDashboard = {
    async init() {
        this.loadData();
    },

    async loadData() {
        try {
            const data = await API.get('/dashboard/support_data.php');
            if (!data || !data.success) return;

            this.renderMySWOs(data.data.my_swos || []);
            this.renderPendingSubmissions(data.data.pending_submissions || []);
            this.renderStatusSummary(data.data.status_summary || {});
        } catch (err) {
            console.error('Support dashboard error:', err);
        }
    },

    renderStatusSummary(summary) {
        const total = Object.values(summary).reduce((a, b) => a + b, 0);
        const el = document.getElementById('supportSummaryCards');
        if (!el) return;
        const pendingSupport = (summary['Pending Support Review'] || 0) + (summary['Returned from Control'] || 0);
        const inProgress = summary['In Progress'] || 0;
        const sentToControl = summary['Pending Control Review'] || 0;
        el.innerHTML = `
            <div class="stat-card"><div class="stat-value">${total}</div><div class="stat-label">Total SWOs</div></div>
            <div class="stat-card border-warning"><div class="stat-value" id="statSupportPending">${pendingSupport}</div><div class="stat-label">Pending Review</div></div>
            <div class="stat-card border-success"><div class="stat-value">${inProgress}</div><div class="stat-label">In Progress</div></div>
            <div class="stat-card border-info"><div class="stat-value">${sentToControl}</div><div class="stat-label">Sent to Control</div></div>
        `;
    },

    renderMySWOs(swos) {
        const tbody = document.getElementById('mySwoTableBody');
        if (!tbody) return;
        if (!swos.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No SWOs created yet.</td></tr>';
            return;
        }
        tbody.innerHTML = swos.map(s => `
            <tr>
                <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                <td>${escapeHtml(s.station_name)}</td>
                <td>${getStatusBadge(s.status)}</td>
                <td>${formatDateShort(s.created_at)}</td>
                <td>${s.assigned_at ? formatDateShort(s.assigned_at) : '—'}</td>
                <td>${s.started_at ? formatDateShort(s.started_at) : '—'}</td>
                <td>${s.submitted_at ? formatDateShort(s.submitted_at) : '—'}</td>
            </tr>
        `).join('');
    },

    renderPendingSubmissions(swos) {
        const tbody = document.getElementById('pendingSubTableBody');
        if (!tbody) return;

        const badge = document.getElementById('submissionsBadge');
        if (badge) {
            badge.textContent = swos.length;
            badge.style.display = swos.length > 0 ? 'inline-block' : 'none';
        }

        if (!swos.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No pending submissions.</td></tr>';
            return;
        }
        tbody.innerHTML = swos.map(s => `
            <tr>
                <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                <td>${escapeHtml(s.station_name)}</td>
                <td>${escapeHtml(s.assigned_to_name || '—')}</td>
                <td>${getStatusBadge(s.status)}</td>
                <td>${formatDate(s.submitted_at)}</td>
                <td>
                    <a class="btn btn-primary btn-sm"
                       href="/scada-checklist-system/views/support/review_swo.php?swo_id=${s.id}">
                        Review
                    </a>
                </td>
            </tr>
        `).join('');
    }
};

// Initialize based on page
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.dataset.page === 'admin') {
        AdminDashboard.init();
    }
    // support and control pages initialise via their own scripts
});
