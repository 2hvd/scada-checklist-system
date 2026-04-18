<?php
$pageTitle = 'Submissions';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');

$swoId = isset($_GET['swo_id']) ? intval($_GET['swo_id']) : null;

require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Submissions</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <?php if ($swoId): ?>
        <!-- Single SWO Checklist Review -->
        <div id="checklistReview">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="swoTitle">Loading SWO...</h3>
                    <button class="btn btn-primary btn-sm" onclick="ChecklistPage.exportCSV()">📥 Export CSV</button>
                </div>
                <div id="swoInfoBar" class="review-modal-info"></div>

                <!-- Progress -->
                <div style="margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="font-weight:600">Progress</span>
                        <span id="progressPct" style="font-weight:700;color:#2e86de">—</span>
                    </div>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar" id="progressBar" style="width:0%"></div>
                    </div>
                    <div class="progress-text" id="progressText"></div>
                </div>
            </div>

            <div id="checklistContainer">
                <div class="loading-overlay"><div class="loading-spinner"></div></div>
            </div>

            <!-- Comments -->
            <div class="card comments-section">
                <div class="card-header">
                    <h3 class="card-title">Comments</h3>
                </div>
                <div id="commentsContainer"></div>
                <form id="commentForm" class="mt-2">
                    <div class="comment-form">
                        <textarea class="form-control" placeholder="Add a comment or review note..." required></textarea>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- List all submitted SWOs -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Checklists Awaiting Review</h3>
                <button class="btn btn-secondary btn-sm" onclick="loadSubmissions()">🔄 Refresh</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>SWO Number</th>
                            <th>Station</th>
                            <th>Type</th>
                            <th>Assigned To</th>
                            <th>Submitted At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="submissionsTableBody">
                        <tr><td colspan="6" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/checklist.js"></script>
<script src="/scada-checklist-system/assets/js/support_submissions.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

<?php if ($swoId): ?>
document.addEventListener('DOMContentLoaded', async function() {
    const swoId = <?php echo $swoId; ?>;

    // Load SWO details
    const details = await API.get('/swo/get_swo_details.php', {swo_id: swoId});
    if (details && details.success) {
        const s = details.data.swo;
        document.getElementById('swoTitle').textContent = `${s.swo_number} — ${s.station_name}`;
        document.getElementById('swoInfoBar').innerHTML =
            `Type: <strong>${escapeHtml(s.swo_type)}</strong> | 
             Status: ${getStatusBadge(s.status)} | 
             Assigned To: <strong>${escapeHtml(s.assigned_to_name || '—')}</strong> | 
             Submitted: <strong>${formatDate(s.submitted_at)}</strong>`;
    }

    // Load checklist in read-only mode with support review columns
    SupportSubmissions.init(swoId);
});
<?php else: ?>
async function loadSubmissions() {
    const data = await API.get('/dashboard/support_data.php');
    const tbody = document.getElementById('submissionsTableBody');
    if (!data || !data.success) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load.</td></tr>';
        return;
    }
    const submissions = data.data.pending_submissions || [];
    if (!submissions.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No pending submissions.</td></tr>';
        return;
    }
    tbody.innerHTML = submissions.map(s => `
        <tr>
            <td><strong>${escapeHtml(s.swo_number)}</strong></td>
            <td>${escapeHtml(s.station_name)}</td>
            <td>${escapeHtml(s.swo_type)}</td>
            <td>${escapeHtml(s.assigned_to_name || '—')}</td>
            <td>${formatDate(s.submitted_at)}</td>
            <td>
                <a href="?swo_id=${s.id}" class="btn btn-primary btn-sm">Review</a>
            </td>
        </tr>
    `).join('');
}

document.addEventListener('DOMContentLoaded', loadSubmissions);
<?php endif; ?>
</script>
