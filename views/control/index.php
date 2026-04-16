<?php
$pageTitle = 'Control Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('control');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Control Dashboard</h1>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary -->
        <div class="stats-grid" id="controlSummaryCards">
            <div class="stat-card border-warning">
                <div class="stat-value" id="statControlPending">—</div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card border-success">
                <div class="stat-value" id="statControlCompleted">—</div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Tabs -->
        <div id="mainTabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-pending">
                    📋 Pending Reviews
                    <span class="nav-badge" id="controlPendingBadge" style="display:none">0</span>
                </button>
                <button class="tab-btn" data-tab="tab-completed">✅ Completed</button>
            </div>

            <!-- Pending Reviews Tab -->
            <div class="tab-content active" id="tab-pending">
                <?php include __DIR__ . '/pending_swos.php'; ?>
            </div>

            <!-- Completed Tab -->
            <div class="tab-content" id="tab-completed">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Completed Reviews</h3>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>SWO Number</th>
                                    <th>Station</th>
                                    <th>Assigned To</th>
                                    <th>Completed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="controlCompletedTableBody">
                                <tr><td colspan="5" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Control Review Modal -->
<div class="modal-overlay" id="controlReviewModal">
    <div class="modal" style="max-width:900px;width:95vw;">
        <div class="modal-header">
            <span class="modal-title" id="controlModalTitle">Control Review</span>
            <button class="modal-close" onclick="closeModal('controlReviewModal')">✕</button>
        </div>
        <div id="controlModalSwoInfo" style="font-size:13px;color:#666;margin-bottom:16px;"></div>

        <div class="table-wrapper" style="max-height:50vh;overflow-y:auto;">
            <table id="controlReviewTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Item</th>
                        <th style="width:120px">User Status</th>
                        <th style="width:140px">Support Decision</th>
                        <th>Support Comment</th>
                        <th style="width:140px">Control Decision</th>
                        <th>Control Comment</th>
                    </tr>
                </thead>
                <tbody id="controlReviewTableBody">
                    <tr><td colspan="7" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            <label style="font-weight:600;display:block;margin-bottom:6px;">Overall Comments:</label>
            <textarea id="controlOverallComments" class="form-control" rows="3" placeholder="Add overall review comments..."></textarea>
        </div>

        <div class="modal-footer" style="margin-top:16px;">
            <button class="btn btn-secondary" onclick="closeModal('controlReviewModal')">Cancel</button>
            <button class="btn btn-warning" id="controlReturnBtn" onclick="ControlDashboard.submitReturn()">
                ↩ Return to Support
            </button>
            <button class="btn btn-success" id="controlApproveBtn" onclick="ControlDashboard.submitApprove()">
                ✅ Approve (Complete)
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/control_dashboard.js"></script>
<script>
document.body.dataset.page = 'control';
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
document.addEventListener('DOMContentLoaded', function() {
    initTabs('mainTabs');
    ControlDashboard.loadData();

    const AUTO_REFRESH_MS = 10000;
    setInterval(() => {
        if (document.hidden) return;
        if (document.querySelector('.modal-overlay.active')) return;
        ControlDashboard.loadData();
    }, AUTO_REFRESH_MS);
});
</script>
