<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Admin Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <span style="font-size:13px;color:#666;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary Stats -->
        <div class="stats-grid" id="adminStatsContainer">
            <div class="stat-card">
                <div class="stat-value" id="statTotal">—</div>
                <div class="stat-label">Total SWOs</div>
            </div>
            <div class="stat-card border-warning">
                <div class="stat-value" id="statPending">—</div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card border-success">
                <div class="stat-value" id="statInProgress">—</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card border-info">
                <div class="stat-value" id="statSubmitted">—</div>
                <div class="stat-label">Submitted</div>
            </div>
        </div>

        <!-- Tabs -->
        <div id="mainTabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-statistics">📈 Statistics</button>
                <button class="tab-btn" data-tab="tab-swo">📋 SWO Management</button>
                <button class="tab-btn" data-tab="tab-activity">🕐 Recent Activity</button>
            </div>

            <!-- Tab: Statistics -->
            <div class="tab-content active" id="tab-statistics">
                <h3>System User Performance</h3>
                <div class="user-cards-grid" id="userCardsGrid">
                    <div class="loading-overlay"><div class="loading-spinner"></div></div>
                </div>
            </div>

            <!-- Tab: SWO Management -->
            <div class="tab-content" id="tab-swo">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All SWOs</h3>
                        <div class="filter-bar" style="margin:0">
                            <select id="statusFilter" class="form-control" style="width:auto">
                                <option value="">All Statuses</option>
                                <option value="Draft">Draft</option>
                                <option value="Pending">Pending</option>
                                <option value="Registered">Registered</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Completed">Completed</option>
                                <option value="Closed">Closed</option>
                            </select>
                            <button class="btn btn-primary btn-sm" onclick="AdminDashboard.loadSWOTable(document.getElementById('statusFilter').value)">🔄 Refresh</button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>SWO Number</th>
                                    <th>Station</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="swoTableBody">
                                <tr><td colspan="7" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Recent Activity -->
            <div class="tab-content" id="tab-activity">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div id="recentActivityList">
                        <div class="loading-overlay"><div class="loading-spinner"></div></div>
                    </div>
                </div>
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
            <textarea id="rejectReason" class="form-control" placeholder="Enter reason for rejection..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn btn-danger" onclick="AdminDashboard.submitReject()">Reject SWO</button>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Assign SWO</span>
            <button class="modal-close" onclick="closeModal('assignModal')">×</button>
        </div>
        <input type="hidden" id="assignSwoId">
        <div class="form-group">
            <label>Assign to System User *</label>
            <select id="assignUserSelect" class="form-control">
                <option value="">-- Select User --</option>
            </select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
            <button class="btn btn-primary" onclick="AdminDashboard.submitAssign()">Assign</button>
        </div>
    </div>
</div>

<!-- View SWO Modal -->
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
document.body.dataset.page = 'admin';
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
</script>
