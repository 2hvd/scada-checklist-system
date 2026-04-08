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
            <div class="stat-card border-warning"><div class="stat-value">—</div><div class="stat-label">Pending Approval</div></div>
            <div class="stat-card border-success"><div class="stat-value">—</div><div class="stat-label">In Progress</div></div>
            <div class="stat-card border-info"><div class="stat-value">—</div><div class="stat-label">Awaiting Review</div></div>
        </div>

        <!-- Tabs -->
        <div id="mainTabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-submissions">
                    📬 Pending Submissions
                    <span class="nav-badge" id="submissionsBadge" style="display:none">0</span>
                </button>
                <button class="tab-btn" data-tab="tab-myswos">📁 My SWOs</button>
            </div>

            <!-- Pending Submissions -->
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
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="pendingSubTableBody">
                                <tr><td colspan="5" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
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
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="mySwoTableBody">
                                <tr><td colspan="6" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/dashboard.js"></script>
<script>
document.body.dataset.page = 'support';
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
</script>
