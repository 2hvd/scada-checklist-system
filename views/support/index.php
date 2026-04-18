<?php
$pageTitle = 'Support Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Support Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/create_swo.php" class="btn btn-primary btn-sm">âž• Create SWO</a>
        </div>
    </div>

        <!-- Summary -->
        <div class="stats-grid" id="supportSummaryCards" style="margin-bottom:32px;">
            <div class="stat-card dash-filter-card" onclick="switchSupportTab('tab-myswos')" id="filterTotal" title="View My SWOs">
                <div class="stat-value" id="statSupportTotal">—</div><div class="stat-label">Total SWOs</div>
            </div>
            <div class="stat-card border-warning dash-filter-card" onclick="switchSupportTab('tab-submissions')" id="filterPending" title="View Pending Reviews">
                <div class="stat-value" id="statSupportPending">—</div><div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card border-success dash-filter-card" onclick="switchSupportTab('tab-myswos')" id="filterInProgress" title="View In Progress">
                <div class="stat-value" id="statSupportInProgress">—</div><div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card border-info dash-filter-card" onclick="switchSupportTab('tab-submitted')" id="filterSent" title="View Sent to Control">
                <div class="stat-value" id="statSupportSent">—</div><div class="stat-label">Sent to Control</div>
            </div>
        </div>

        <style>
        .dash-filter-card {
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, outline 0.15s;
            outline: 2px solid transparent;
        }
        .dash-filter-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        .dash-filter-card.filter-active {
            outline: 2px solid currentColor;
            box-shadow: 0 4px 16px rgba(99,102,241,0.18);
            transform: translateY(-2px);
        }
        #filterTotal.filter-active       { outline-color: #6366f1; }
        #filterPending.filter-active     { outline-color: #f59e0b; }
        #filterInProgress.filter-active  { outline-color: #22c55e; }
        #filterSent.filter-active        { outline-color: #3b82f6; }
        </style>

            <!-- Pending Reviews Tab -->
            <div class="tab-content active" id="tab-submissions">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Checklists Awaiting Review</h3>
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
                        <a href="/scada-checklist-system/views/support/create_swo.php" class="btn btn-primary btn-sm">âž• New SWO</a>
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
    <div class="modal modal-review">
        <div class="modal-header">
            <span class="modal-title" id="supportModalTitle">Support Review</span>
            <button class="modal-close" onclick="closeModal('supportReviewModal')">âœ•</button>
        </div>
        <div id="supportModalSwoInfo" class="review-modal-info"></div>

        <div class="table-wrapper modal-table-wrapper">
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

        <div class="modal-form-section">
            <label class="modal-form-label">Overall Comments:</label>
            <textarea id="supportOverallComments" class="form-control" rows="3" placeholder="Add overall review comments..."></textarea>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('supportReviewModal')">Cancel</button>
            <button class="btn btn-danger" id="supportRejectBtn" onclick="SupportDashboard.submitReject()">
                âŒ Reject (Back to User)
            </button>
            <button class="btn btn-success" id="supportAcceptBtn" onclick="SupportDashboard.submitAccept()">
                âœ… Accept (Send to Control)
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
    // Activate tab based on sidebar click or default
    const hash = location.hash.replace('#','') || 'tab-submissions';
    switchSupportTab(hash);
    SupportDashboard.init();

    const AUTO_REFRESH_MS = getDashboardRefreshMs(10000);
    setInterval(() => {
        if (document.hidden) return;
        if (document.querySelector('.modal-overlay.active')) return;
        SupportDashboard.loadData();
    }, AUTO_REFRESH_MS);
});

function switchSupportTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');

    // Update sidebar active state
    document.querySelectorAll('.support-tab-nav').forEach(el => el.classList.remove('active'));
    const navItem = document.querySelector(`.support-tab-nav[data-tab="${tabId}"]`);
    if (navItem) navItem.classList.add('active');

    // Update stat card active outline
    document.querySelectorAll('.dash-filter-card').forEach(el => el.classList.remove('filter-active'));
    if (tabId === 'tab-submissions') {
        document.getElementById('filterPending')?.classList.add('filter-active');
    } else if (tabId === 'tab-myswos') {
        document.getElementById('filterTotal')?.classList.add('filter-active');
    } else if (tabId === 'tab-submitted') {
        document.getElementById('filterSent')?.classList.add('filter-active');
    }

    // Load submitted SWOs when that tab is activated
    if (tabId === 'tab-submitted') loadSubmittedSWOs();
    location.hash = tabId;
}
</script>
