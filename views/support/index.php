<?php
$pageTitle = 'Support Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Support Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/create_swo.php" class="btn btn-primary btn-sm">➕ Create SWO</a>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary -->
        <div class="stats-grid" id="supportSummaryCards">
            <div class="stat-card"><div class="stat-value">—</div><div class="stat-label">Total SWOs</div></div>
            <div class="stat-card border-warning"><div class="stat-value" id="statSupportPending">—</div><div class="stat-label">Pending Review</div></div>
            <div class="stat-card border-success"><div class="stat-value">—</div><div class="stat-label">In Progress</div></div>
            <div class="stat-card border-info"><div class="stat-value">—</div><div class="stat-label">Sent to Control</div></div>
        </div>

        <!-- Tabs -->
        <div id="mainTabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-submissions">
                    📬 Pending Reviews
                    <span class="nav-badge" id="submissionsBadge" style="display:none">0</span>
                </button>
                <button class="tab-btn" data-tab="tab-myswos">📁 My SWOs</button>
                <button class="tab-btn" data-tab="tab-submitted" onclick="loadSubmittedSWOs()">📤 Submitted SWOs</button>
            </div>

            <!-- Pending Reviews Tab -->
            <div class="tab-content active" id="tab-submissions">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Checklists Awaiting Review</h3>
                        <button class="btn btn-secondary btn-sm" onclick="SupportDashboard.loadData()">🔄 Refresh</button>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>SWO Number</th>
                                    <th>Station</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="pendingSubTableBody">
                                <tr><td colspan="6" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- My SWOs -->
            <div class="tab-content" id="tab-myswos">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My SWOs</h3>
                        <a href="/scada-checklist-system/views/support/create_swo.php" class="btn btn-primary btn-sm">➕ New SWO</a>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>SWO Number</th>
                                    <th>Station</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Sent to User</th>
                                    <th>User Started</th>
                                    <th>User Submitted</th>
                                </tr>
                            </thead>
                            <tbody id="mySwoTableBody">
                                <tr><td colspan="7" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Submitted SWOs -->
            <div class="tab-content" id="tab-submitted">
                <?php include __DIR__ . '/submitted_swos.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Support Review Modal -->
<div class="modal-overlay" id="supportReviewModal">
    <div class="modal" style="max-width:900px;width:95vw;">
        <div class="modal-header">
            <span class="modal-title" id="supportModalTitle">Support Review</span>
            <button class="modal-close" onclick="closeModal('supportReviewModal')">✕</button>
        </div>
        <div id="supportModalSwoInfo" style="font-size:13px;color:#666;margin-bottom:16px;"></div>

        <div class="table-wrapper" style="max-height:50vh;overflow-y:auto;">
            <table id="supportReviewTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Item</th>
                        <th style="width:120px">User Status</th>
                        <th style="width:140px">Support Decision</th>
                        <th>Support Comment</th>
                    </tr>
                </thead>
                <tbody id="supportReviewTableBody">
                    <tr><td colspan="5" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            <label style="font-weight:600;display:block;margin-bottom:6px;">Overall Comments:</label>
            <textarea id="supportOverallComments" class="form-control" rows="3" placeholder="Add overall review comments..."></textarea>
        </div>

        <div class="modal-footer" style="margin-top:16px;">
            <button class="btn btn-secondary" onclick="closeModal('supportReviewModal')">Cancel</button>
            <button class="btn btn-danger" id="supportRejectBtn" onclick="SupportDashboard.submitReject()">
                ❌ Reject (Back to User)
            </button>
            <button class="btn btn-success" id="supportAcceptBtn" onclick="SupportDashboard.submitAccept()">
                ✅ Accept (Send to Control)
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/dashboard.js"></script>
<script src="/scada-checklist-system/assets/js/support_dashboard.js"></script>
<script>
document.body.dataset.page = 'support';
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
document.addEventListener('DOMContentLoaded', function() {
    initTabs('mainTabs');
    SupportDashboard.init();

    const AUTO_REFRESH_MS = getDashboardRefreshMs(10000);
    setInterval(() => {
        if (document.hidden) return;
        if (document.querySelector('.modal-overlay.active')) return;
        SupportDashboard.loadData();
    }, AUTO_REFRESH_MS);
});
</script>
