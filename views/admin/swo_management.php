<?php
$pageTitle = 'SWO Management';
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">SWO Management</h1>
        </div>
        <div class="topbar-actions">
            <button class="btn btn-primary btn-sm" onclick="loadSWOs()">🔄 Refresh</button>
        </div>
    </div>

    <div class="page-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All SWOs</h3>
                <div class="filter-bar" style="margin:0">
                    <select id="statusFilter" class="form-control" style="width:auto" onchange="loadSWOs()">
                        <option value="">All Statuses</option>
                        <option value="Draft">Draft</option>
                        <option value="Pending">Pending</option>
                        <option value="Registered">Registered</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Submitted">Submitted</option>
                        <option value="Completed">Completed</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>SWO Number</th>
                            <th>Station</th>
                            <th>Type</th>
                            <th>KCOR</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Assigned To</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="swoTableBody">
                        <tr><td colspan="9" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
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
            <button class="modal-close" onclick="closeModal('rejectModal')">×</button>
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
            <button class="modal-close" onclick="closeModal('assignModal')">×</button>
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
            <button class="modal-close" onclick="closeModal('viewSwoModal')">×</button>
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
    AdminDashboard.loadSWOTable(filter);
}

async function submitReject() {
    AdminDashboard.submitReject();
}

async function submitAssign() {
    AdminDashboard.submitAssign();
}

document.addEventListener('DOMContentLoaded', function() {
    AdminDashboard.loadSWOTable();
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
