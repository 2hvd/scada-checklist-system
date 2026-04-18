﻿<?php
$pageTitle = 'SWO Management';
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">SWO Management</h1>
        </div>
        <div class="topbar-actions">
        </div>
    </div>

    <div class="page-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All SWOs</h3>
                <div class="filter-bar filter-bar-compact">
                    <select id="statusFilter" class="form-control form-control-auto" onchange="loadSWOs()">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Registered">Registered</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Pending Support Review">Pending Support Review</option>
                        <option value="Pending Control Review">Pending Control Review</option>
                        <option value="Returned from Control">Returned from Control</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Completed">Completed</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="swo-management-table">
                    <thead>
                        <tr>
                            <th>SWO #</th>
                            <th>Station</th>
                            <th>Type</th>
                            <th>KCOR</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Assigned To</th>
                            <th>Assigned Date</th>
                            <th>User Submitted</th>
                            <th>Support Review</th>
                            <th>Control Review</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="swoTableBody">
                        <tr><td colspan="12" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Reject SWO</span>
            <button class="modal-close" onclick="closeModal('rejectModal')">Ã—</button>
        </div>
        <input type="hidden" id="rejectSwoId">
        <div class="form-group">
            <label>Rejection Reason *</label>
            <textarea id="rejectReason" class="form-control" placeholder="Enter reason..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn btn-danger" onclick="submitReject()">Reject</button>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Assign SWO to User</span>
            <button class="modal-close" onclick="closeModal('assignModal')">Ã—</button>
        </div>
        <input type="hidden" id="assignSwoId">
        <div class="form-group">
            <label>Select System User *</label>
            <select id="assignUserSelect" class="form-control"></select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitAssign()">Assign</button>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewSwoModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">SWO Details</span>
            <button class="modal-close" onclick="closeModal('viewSwoModal')">Ã—</button>
        </div>
        <div id="viewSwoContent"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewSwoModal')">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/dashboard.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

async function loadSWOs() {
    const filter = document.getElementById('statusFilter').value;
    const tbody = document.getElementById('swoTableBody');
    tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

    try {
        const params = filter ? '?status=' + encodeURIComponent(filter) : '';
        const resp = await fetch('/scada-checklist-system/api/admin/get_swo_management_list.php' + params);
        const data = await resp.json();

        if (!data || !data.success) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">Failed to load SWOs.</td></tr>';
            return;
        }

        const swos = data.data || [];
        if (!swos.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">No SWOs found.</td></tr>';
            return;
        }

        tbody.innerHTML = swos.map(s => `
            <tr>
                <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                <td>${escapeHtml(s.station_name)}</td>
                <td>${escapeHtml(s.swo_type)}</td>
                <td>${escapeHtml(s.kcor || '"”')}</td>
                <td>${getStatusBadge(s.status)}</td>
                <td>${s.created_at ? formatDateShort(s.created_at) : '"”'}</td>
                <td>${escapeHtml(s.assigned_to || '"”')}</td>
                <td>${s.assigned_at ? formatDateShort(s.assigned_at) : '"”'}</td>
                <td>${s.submitted_at ? formatDateShort(s.submitted_at) : '"”'}</td>
                <td>
                    ${s.support_reviewed_at ? formatDateShort(s.support_reviewed_at) : '"”'}
                    <div class="text-muted" style="font-size:11px;">${escapeHtml(s.support_reviewer_name || '"”')}</div>
                </td>
                <td>
                    ${s.control_reviewed_at ? formatDateShort(s.control_reviewed_at) : '"”'}
                    <div class="text-muted" style="font-size:11px;">${escapeHtml(s.control_reviewer_name || '"”')}</div>
                </td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="AdminDashboard.viewSWO(${s.id})">View</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger">Error loading SWOs.</td></tr>';
    }
}

async function submitReject() {
    AdminDashboard.submitReject();
}

async function submitAssign() {
    AdminDashboard.submitAssign();
}

document.addEventListener('DOMContentLoaded', function() {
    loadSWOs();
    // Load users for assign modal
    fetch('/scada-checklist-system/api/dashboard/admin_stats.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const users = data.data.user_stats || [];
                const select = document.getElementById('assignUserSelect');
                select.innerHTML = '<option value="">-- Select User --</option>' +
                    users.map(u => `<option value="${u.id}">${escapeHtml(u.username)}</option>`).join('');
            }
        });
});

// Override showAssignModal to use cached users
AdminDashboard.showAssignModal = function(swoId) {
    document.getElementById('assignSwoId').value = swoId;
    openModal('assignModal');
};
</script>
