<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('user');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">My Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <span class="welcome-text">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary Stats (clickable filters) -->
        <div class="stats-grid" id="userSummaryCards" style="margin-bottom:32px;">
            <div class="stat-card dash-filter-card" id="filterAll" onclick="setFilter('all')" title="Show all SWOs">
                <div class="stat-value" id="statAssigned">—</div>
                <div class="stat-label">All SWOs</div>
            </div>
            <div class="stat-card border-success dash-filter-card" id="filterInProgress" onclick="setFilter('in_progress')" title="Filter: In Progress">
                <div class="stat-value" id="statInProgress">—</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card border-info dash-filter-card" id="filterSubmitted" onclick="setFilter('submitted')" title="Filter: Submitted">
                <div class="stat-value" id="statSubmitted">—</div>
                <div class="stat-label">Submitted</div>
            </div>
            <div class="stat-card border-warning dash-filter-card" id="filterCompleted" onclick="setFilter('completed')" title="Filter: Completed">
                <div class="stat-value" id="statCompleted">—</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card border-primary" style="cursor:default;">
                <div class="stat-value" id="statOverallPct">—</div>
                <div class="stat-label">Overall Progress</div>
            </div>
        </div>

        <!-- SWOs Table -->
        <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:var(--shadow-soft);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h3 style="margin:0;font-size:16px;color:var(--gray-800);" id="tableTitle">All SWOs</h3>
                <a href="/scada-checklist-system/views/user/my_checklists.php" class="btn btn-secondary btn-sm">View Cards →</a>
            </div>
            <div id="swoTable">
                <div class="loading-overlay" style="position:relative;height:80px;"><div class="loading-spinner"></div></div>
            </div>
        </div>
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
#filterAll.filter-active       { outline-color: #6366f1; }
#filterInProgress.filter-active { outline-color: #22c55e; }
#filterSubmitted.filter-active  { outline-color: #3b82f6; }
#filterCompleted.filter-active  { outline-color: #f59e0b; }
.swo-table-row:hover { background: #f8f9ff !important; }
</style>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

let allSwos = [];
let activeFilter = 'all';

const filterGroups = {
    all:         { label: 'All SWOs',   match: () => true },
    in_progress: { label: 'In Progress', match: s => s.status === 'In Progress' },
    submitted:   { label: 'Submitted',   match: s => ['Pending Support Review','Pending Control Review','Returned from Control'].includes(s.status) },
    completed:   { label: 'Completed',   match: s => s.status === 'Completed' },
};

function setFilter(f) {
    activeFilter = f;
    // Update active card highlight
    ['filterAll','filterInProgress','filterSubmitted','filterCompleted'].forEach(id => {
        document.getElementById(id)?.classList.remove('filter-active');
    });
    const map = {all:'filterAll', in_progress:'filterInProgress', submitted:'filterSubmitted', completed:'filterCompleted'};
    document.getElementById(map[f])?.classList.add('filter-active');
    document.getElementById('tableTitle').textContent = filterGroups[f].label;
    renderTable(allSwos.filter(filterGroups[f].match));
}

async function loadUserDashboard() {
    try {
        const data = await API.get('/dashboard/user_data.php');
        if (!data || !data.success) return;
        allSwos = data.data.swos || [];
        updateSummary(allSwos);
        renderTable(allSwos.filter(filterGroups[activeFilter].match));
    } catch (err) { /* silent */ }
}

function updateSummary(swos) {
    document.getElementById('statAssigned').textContent    = swos.length;
    document.getElementById('statInProgress').textContent  = swos.filter(filterGroups.in_progress.match).length;
    document.getElementById('statSubmitted').textContent   = swos.filter(filterGroups.submitted.match).length;
    document.getElementById('statCompleted').textContent   = swos.filter(filterGroups.completed.match).length;
    if (swos.length > 0) {
        const avg = Math.round(swos.reduce((sum,s) => sum + parseFloat(s.progress||0), 0) / swos.length);
        document.getElementById('statOverallPct').textContent = avg + '%';
    } else {
        document.getElementById('statOverallPct').textContent = '—';
    }
}

function renderTable(swos) {
    const el = document.getElementById('swoTable');
    if (!swos.length) {
        el.innerHTML = '<p style="color:var(--gray-400);margin:0;text-align:center;padding:24px 0;">No SWOs in this category.</p>';
        return;
    }
    const sorted = [...swos].sort((a,b) => b.id - a.id);
    el.innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gray-900);border-radius:8px;">
                    <th style="padding:10px 14px;text-align:left;color:#9ca3af;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">SWO #</th>
                    <th style="padding:10px 14px;text-align:left;color:#9ca3af;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Station</th>
                    <th style="padding:10px 14px;text-align:left;color:#9ca3af;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Type</th>
                    <th style="padding:10px 14px;text-align:left;color:#9ca3af;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Status</th>
                    <th style="padding:10px 14px;text-align:right;color:#9ca3af;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.05em;">Progress</th>
                </tr>
            </thead>
            <tbody>
                ${sorted.map(s => {
                    const pct = parseFloat(s.progress||0).toFixed(0);
                    const color = pct>=100?'#22c55e':pct>=50?'#f59e0b':'#ef4444';
                    return `<tr class="swo-table-row" style="border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background .15s;"
                        onclick="location.href='/scada-checklist-system/views/user/checklist.php?swo_id=${s.id}'">
                        <td style="padding:12px 14px;font-weight:700;color:var(--gray-800);">${escapeHtml(s.swo_number)}</td>
                        <td style="padding:12px 14px;color:var(--gray-600);">${escapeHtml(s.station_name)}</td>
                        <td style="padding:12px 14px;color:var(--gray-600);">${escapeHtml(s.swo_type)}</td>
                        <td style="padding:12px 14px;">${getStatusBadge(s.status)}</td>
                        <td style="padding:12px 14px;text-align:right;">
                            <span style="font-weight:700;color:${color};">${pct}%</span>
                            <div style="margin-top:4px;background:#f1f5f9;border-radius:4px;height:4px;width:80px;margin-left:auto;">
                                <div style="width:${Math.min(pct,100)}%;background:${color};height:4px;border-radius:4px;transition:width .3s;"></div>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
}

<<<<<<< HEAD
document.addEventListener('DOMContentLoaded', function () {
    // Default: all active
    document.getElementById('filterAll').classList.add('filter-active');
    loadUserDashboard();
    const AUTO_REFRESH_MS = getDashboardRefreshMs(10000);
    setInterval(() => { if (!document.hidden) loadUserDashboard(); }, AUTO_REFRESH_MS);
=======
async function submitChecklist(swoId) {
    const confirmed = await confirmDialog('Submit this checklist for review?');
    if (!confirmed) return;
    const data = await API.post('/checklist/submit_checklist.php', {swo_id: swoId});
    if (data && data.success) {
        showSuccess('Checklist submitted for review!');
        setTimeout(() => loadUserDashboard(), 1500);
    } else {
        showError(data?.message || 'Failed to submit');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    loadUserDashboard();

    const AUTO_REFRESH_MS = getDashboardRefreshMs(10000);
    setInterval(() => {
        if (document.hidden) return;
        loadUserDashboard();
    }, AUTO_REFRESH_MS);
>>>>>>> 65803cba57c3364051c6904add3c2d520a37afb9
});
</script>
