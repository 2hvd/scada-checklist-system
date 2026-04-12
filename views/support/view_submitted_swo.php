<?php
$pageTitle = 'View Submitted SWO';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');

$swo_id = isset($_GET['swo_id']) ? intval($_GET['swo_id']) : null;
if (!$swo_id) {
    header('Location: /scada-checklist-system/views/support/index.php');
    exit;
}

require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title" id="viewTitle">Loading…</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <div id="swoInfoBar" style="background:#fff;border-radius:8px;padding:16px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);display:flex;gap:20px;flex-wrap:wrap;font-size:14px;">
            <div><strong>SWO:</strong> <span id="infoSwoNumber">—</span></div>
            <div><strong>Station:</strong> <span id="infoStation">—</span></div>
            <div><strong>Status:</strong> <span id="infoStatus">—</span></div>
            <div><strong>Submitted:</strong> <span id="infoSubmitted">—</span></div>
            <div><strong>Support Review:</strong> <span id="infoSupportReview">—</span></div>
            <div><strong>Control Review:</strong> <span id="infoControlReview">—</span></div>
        </div>

        <div id="swoFeedbackContent">
            <div class="loading-overlay" style="position:relative;height:100px;"><div class="loading-spinner"></div></div>
        </div>

        <div id="swoActions" style="margin-top:20px;display:flex;gap:10px;" class="hidden">
            <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary">← Back</a>
            <a id="editSwoBtn" href="#" class="btn btn-warning" style="display:none;">✏️ Edit &amp; Resubmit</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script>
const SWO_ID = <?= $swo_id ?>;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

async function loadSWOView() {
    try {
        const [swoData, checklistData, supportReviews, controlReviews] = await Promise.all([
            API.get('/swo/get_swo_details.php', {swo_id: SWO_ID}),
            API.get('/checklist/get_checklist.php', {swo_id: SWO_ID}),
            API.get('/swo/get_support_item_reviews.php', {swo_id: SWO_ID}),
            API.get('/swo/get_control_item_reviews.php', {swo_id: SWO_ID}),
        ]);

        if (!swoData || !swoData.success) {
            document.getElementById('swoFeedbackContent').innerHTML =
                '<div class="alert alert-danger">Failed to load SWO details.</div>';
            return;
        }

        const swo = swoData.data.swo;

        // Populate info bar
        document.getElementById('viewTitle').textContent = 'SWO: ' + swo.swo_number;
        document.getElementById('infoSwoNumber').textContent = swo.swo_number;
        document.getElementById('infoStation').textContent = swo.station_name;
        document.getElementById('infoStatus').innerHTML = getStatusBadge(swo.status);
        document.getElementById('infoSubmitted').textContent = swo.submitted_at ? formatDateShort(swo.submitted_at) : '—';
        document.getElementById('infoSupportReview').textContent = swo.support_reviewed_at ? formatDateShort(swo.support_reviewed_at) : '—';
        document.getElementById('infoControlReview').textContent = swo.control_reviewed_at ? formatDateShort(swo.control_reviewed_at) : '—';

        // Show edit button if returned
        const editBtn = document.getElementById('editSwoBtn');
        if (swo.status === 'Returned from Control') {
            editBtn.href = '/scada-checklist-system/views/support/review_swo.php?swo_id=' + swo.id;
            editBtn.style.display = 'inline-block';
        }
        document.getElementById('swoActions').classList.remove('hidden');

        if (!checklistData || !checklistData.success) {
            document.getElementById('swoFeedbackContent').innerHTML =
                '<div class="alert alert-warning">No checklist data available.</div>';
            return;
        }

        // Build lookup maps
        const srMap = {};
        if (supportReviews && supportReviews.success) {
            (supportReviews.data || []).forEach(r => { srMap[r.item_key] = r; });
        }
        const crMap = {};
        if (controlReviews && controlReviews.success) {
            (controlReviews.data || []).forEach(r => { crMap[r.item_key] = r; });
        }

        // Render sections
        const sections = checklistData.data.sections || [];
        let html = '';

        sections.forEach(section => {
            let rowNum = 0;
            let rowsHtml = '';

            section.items.forEach(item => {
                rowNum++;
                const sr = srMap[item.key] || {};
                const cr = crMap[item.key] || {};
                const controlComment = cr.control_comment || '';

                rowsHtml += `
                    <tr>
                        <td style="text-align:center">${rowNum}</td>
                        <td>${escapeHtml(item.label)}</td>
                        <td>${getChecklistStatusBadge(item.status)}</td>
                        <td>${sr.support_decision ? getChecklistStatusBadge(sr.support_decision) : '<span class="text-muted">—</span>'}</td>
                        <td style="font-size:13px">${escapeHtml(sr.support_comment || '—')}</td>
                        <td>${cr.control_decision ? getChecklistStatusBadge(cr.control_decision) : '<span class="text-muted">—</span>'}</td>
                        <td>${controlComment
                            ? `<div class="feedback-comment">${escapeHtml(controlComment)}</div>`
                            : '<span class="text-muted">—</span>'
                        }</td>
                    </tr>
                `;
            });

            html += `
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3 class="card-title">${escapeHtml(section.label)}</h3>
                    </div>
                    <div class="table-wrapper" style="overflow-x:auto;">
                        <table class="feedback-table">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Item</th>
                                    <th style="width:110px">User Status</th>
                                    <th style="width:110px">Your Decision</th>
                                    <th>Your Comment</th>
                                    <th style="width:110px">Control Decision</th>
                                    <th>Control Comment</th>
                                </tr>
                            </thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        document.getElementById('swoFeedbackContent').innerHTML = html || '<p class="text-muted">No checklist items found.</p>';

    } catch (err) {
        console.error(err);
        document.getElementById('swoFeedbackContent').innerHTML =
            '<div class="alert alert-danger">Error loading data.</div>';
    }
}

document.addEventListener('DOMContentLoaded', loadSWOView);
</script>
