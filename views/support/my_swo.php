<?php
$pageTitle = 'My SWOs';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">My SWOs</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/create_swo.php" class="btn btn-primary btn-sm">➕ New SWO</a>
        </div>
    </div>

    <div class="page-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">SWOs Created by Me</h3>
                <div class="filter-bar" style="margin:0">
                    <select id="statusFilter" onchange="filterTable()" class="form-control" style="width:auto">
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
                <table id="swoTable">
                    <thead>
                        <tr>
                            <th>SWO Number</th>
                            <th>Station</th>
                            <th>Type</th>
                            <th>KCOR</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody id="swoTableBody">
                        <tr><td colspan="7" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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

async function loadSWOs() {
    const data = await API.get('/swo/get_swo_list.php');
    if (!data || !data.success) { showError('Failed to load SWOs'); return; }
    allSwos = data.data || [];
    renderTable(allSwos);
}

function renderTable(swos) {
    const tbody = document.getElementById('swoTableBody');
    if (!swos.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No SWOs found.</td></tr>';
        return;
    }
    tbody.innerHTML = swos.map(s => `
        <tr>
            <td><strong>${escapeHtml(s.swo_number)}</strong></td>
            <td>${escapeHtml(s.station_name)}</td>
            <td>${escapeHtml(s.swo_type)}</td>
            <td>${escapeHtml(s.kcor || '—')}</td>
            <td>${getStatusBadge(s.status)}</td>
            <td>${escapeHtml(s.assigned_to_name || '—')}</td>
            <td>${formatDateShort(s.created_at)}</td>
        </tr>
    `).join('');
}

function filterTable() {
    const filter = document.getElementById('statusFilter').value;
    const filtered = filter ? allSwos.filter(s => s.status === filter) : allSwos;
    renderTable(filtered);
}

document.addEventListener('DOMContentLoaded', loadSWOs);
</script>
