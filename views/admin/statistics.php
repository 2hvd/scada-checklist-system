<?php
$pageTitle = 'Statistics';
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';

$filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Statistics</h1>
        </div>
    </div>

    <div class="page-content">
        <div class="stats-grid" id="adminStatsContainer">
            <div class="stat-card">
                <div class="stat-value" id="statTotal">—</div>
                <div class="stat-label">Total SWOs</div>
            </div>
            <div class="stat-card border-warning">
                <div class="stat-value" id="statPending">—</div>
                <div class="stat-label">Pending</div>
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

        <h3>System User Performance</h3>
        <div class="user-cards-grid" id="userCardsGrid">
            <div class="loading-overlay"><div class="loading-spinner"></div></div>
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
document.addEventListener('DOMContentLoaded', function() {
    AdminDashboard.loadStats();
});
</script>
