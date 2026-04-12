<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('user');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">My Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <span style="font-size:13px;color:#666;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary Stats -->
        <div class="stats-grid" id="userSummaryCards" style="margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-value" id="statAssigned">—</div>
                <div class="stat-label">Assigned SWOs</div>
            </div>
            <div class="stat-card border-success">
                <div class="stat-value" id="statInProgress">—</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card border-info">
                <div class="stat-value" id="statSubmitted">—</div>
                <div class="stat-label">Submitted</div>
            </div>
            <div class="stat-card border-warning">
                <div class="stat-value" id="statOverallPct">—</div>
                <div class="stat-label">Overall Progress</div>
            </div>
        </div>

        <h3>Assigned SWOs</h3>
        <div class="swo-cards-grid" id="swoCardsContainer">
            <div class="loading-overlay"><div class="loading-spinner"></div></div>
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

async function loadUserDashboard() {
    const container = document.getElementById('swoCardsContainer');
    try {
        const data = await API.get('/dashboard/user_data.php');
        if (!data || !data.success) {
            container.innerHTML = '<div class="alert alert-danger">Failed to load data.</div>';
            return;
        }

        const swos = data.data.swos || [];
        updateSummary(swos);
        renderSWOCards(swos, container);
    } catch (err) {
        container.innerHTML = '<div class="alert alert-danger">Connection error.</div>';
    }
}

function updateSummary(swos) {
    document.getElementById('statAssigned').textContent = swos.length;
    document.getElementById('statInProgress').textContent = swos.filter(s => s.status === 'In Progress').length;
    document.getElementById('statSubmitted').textContent = swos.filter(s =>
        ['Pending Support Review', 'Pending Control Review'].includes(s.status)
    ).length;

    if (swos.length > 0) {
        const avgPct = Math.round(swos.reduce((sum, s) => sum + parseFloat(s.progress || 0), 0) / swos.length);
        document.getElementById('statOverallPct').textContent = avgPct + '%';
    } else {
        document.getElementById('statOverallPct').textContent = '—';
    }
}

function renderSWOCards(swos, container) {
    if (!swos.length) {
        container.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <span class="empty-icon">📋</span>
                <p>No SWOs assigned to you yet.</p>
            </div>`;
        return;
    }

    const editableStatuses = ['In Progress'];
    const reviewStatuses   = ['Pending Support Review', 'Pending Control Review', 'Returned from Control', 'Completed'];

    container.innerHTML = swos.map(swo => {
        const counts = swo.checklist_counts || {};
        const isEditable = editableStatuses.includes(swo.status);
        const canOpenChecklist = isEditable || reviewStatuses.includes(swo.status);
        const statusClass = swo.status === 'Completed' ? 'status-completed' : '';

        let statusNotice = '';
        if (swo.rejection_reason && swo.status === 'In Progress') {
            statusNotice = `<div class="alert alert-warning" style="margin-top:8px;font-size:12px;padding:6px 10px;">
                ⚠️ Rejected by Support: ${escapeHtml(swo.rejection_reason)}</div>`;
        } else if (swo.status === 'Pending Support Review') {
            statusNotice = `<div class="alert alert-info" style="margin-top:8px;font-size:12px;padding:6px 10px;">
                ℹ️ Submitted — awaiting Support review.</div>`;
        } else if (swo.status === 'Pending Control Review') {
            statusNotice = `<div class="alert alert-info" style="margin-top:8px;font-size:12px;padding:6px 10px;">
                ℹ️ Accepted by Support — awaiting Control approval.</div>`;
        } else if (swo.status === 'Returned from Control') {
            statusNotice = `<div class="alert alert-warning" style="margin-top:8px;font-size:12px;padding:6px 10px;">
                🔄 Returned by Control — under Support re-review.</div>`;
        } else if (swo.status === 'Completed') {
            statusNotice = `<div class="alert alert-success" style="margin-top:8px;font-size:12px;padding:6px 10px;">
                ✅ Completed — approved by Control.</div>`;
        }

        return `
        <div class="swo-card ${statusClass}">
            <div class="swo-card-header">
                <div>
                    <div class="swo-number">${escapeHtml(swo.swo_number)}</div>
                    <div class="swo-station">${escapeHtml(swo.station_name)}</div>
                    <div class="swo-type">${escapeHtml(swo.swo_type)} ${swo.kcor ? '| ' + escapeHtml(swo.kcor) : ''}</div>
                </div>
                ${getStatusBadge(swo.status)}
            </div>

            ${statusNotice}

            <div class="swo-item-counts">
                <span class="item-count-badge status-done">✓ ${counts.done || 0} Done</span>
                <span class="item-count-badge status-na">— ${counts.na || 0} N/A</span>
                <span class="item-count-badge status-still">⏳ ${counts.still || 0} Still</span>
                <span class="item-count-badge status-not_yet">✗ ${counts.not_yet || 0} Not Yet</span>
                <span class="item-count-badge status-empty">○ ${counts.empty || 0} Empty</span>
            </div>

            ${renderProgressBar(swo.progress)}

            <div class="swo-card-actions">
                ${isEditable ? `
                    <a href="/scada-checklist-system/views/user/checklist.php?swo_id=${swo.id}"
                       class="btn btn-primary btn-sm">📝 Edit Checklist</a>` : ''}
                ${!isEditable && canOpenChecklist ? `
                    <a href="/scada-checklist-system/views/user/checklist.php?swo_id=${swo.id}"
                       class="btn btn-secondary btn-sm">👁 View Checklist</a>` : ''}
                ${swo.status === 'In Progress' && (counts.empty || 0) === 0 ? `
                    <button class="btn btn-success btn-sm" onclick="submitChecklist(${swo.id})">📤 Submit</button>` : ''}
            </div>
        </div>`;
    }).join('');
}

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

document.addEventListener('DOMContentLoaded', loadUserDashboard);
</script>
