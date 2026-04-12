<?php
$pageTitle = 'Review Feedback';
require_once __DIR__ . '/../../config/functions.php';
requireRole('user');

$swo_id = isset($_GET['swo_id']) ? intval($_GET['swo_id']) : null;
if (!$swo_id) {
    header('Location: /scada-checklist-system/views/user/index.php');
    exit;
}

require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title" id="feedbackTitle">Loading…</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/user/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <div id="feedbackAlert" style="display:none;"></div>

        <div id="swoInfoBar" style="background:#fff;border-radius:8px;padding:16px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);display:flex;gap:20px;flex-wrap:wrap;font-size:14px;">
            <div><strong>SWO:</strong> <span id="infoSwoNumber">—</span></div>
            <div><strong>Station:</strong> <span id="infoStation">—</span></div>
            <div><strong>Status:</strong> <span id="infoStatus">—</span></div>
        </div>

        <div id="feedbackContent">
            <div class="loading-overlay" style="position:relative;height:100px;"><div class="loading-spinner"></div></div>
        </div>

        <div id="feedbackActions" style="margin-top:20px;display:flex;gap:10px;" class="hidden">
            <a href="/scada-checklist-system/views/user/index.php" class="btn btn-secondary">← Back</a>
            <a id="editChecklistBtn" href="#" class="btn btn-primary">✏️ Edit Checklist</a>
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

async function loadFeedback() {
    try {
        const [swoData, checklistData, supportReviews, controlReviews] = await Promise.all([
            API.get('/swo/get_swo_details.php', {swo_id: SWO_ID}),
            API.get('/checklist/get_checklist.php', {swo_id: SWO_ID}),
            API.get('/swo/get_support_item_reviews.php', {swo_id: SWO_ID}),
            API.get('/swo/get_control_item_reviews.php', {swo_id: SWO_ID}),
        ]);

        if (!swoData || !swoData.success) {
            document.getElementById('feedbackContent').innerHTML =
                '<div class="alert alert-danger">Failed to load SWO details.</div>';
            return;
        }

        const swo = swoData.data.swo;

        // Populate info bar
        document.getElementById('feedbackTitle').textContent = 'Feedback: ' + swo.swo_number;
        document.getElementById('infoSwoNumber').textContent = swo.swo_number;
        document.getElementById('infoStation').textContent = swo.station_name;
        document.getElementById('infoStatus').innerHTML = getStatusBadge(swo.status);

        // Show feedback alert banner
        const alertEl = document.getElementById('feedbackAlert');
        if (swo.status === 'In Progress' && swo.rejection_reason) {
            alertEl.style.display = 'block';
            alertEl.className = 'alert alert-warning';
            alertEl.innerHTML = `<strong>⚠️ Rejected by Support</strong><p style="margin:4px 0 0">Please review the comments below and make the necessary changes before resubmitting.</p>`;
        } else if (swo.status === 'Returned from Control') {
            alertEl.style.display = 'block';
            alertEl.className = 'alert alert-warning';
            alertEl.innerHTML = `<strong>🔄 Returned by Control</strong><p style="margin:4px 0 0">Please review the comments below and make the necessary changes.</p>`;
        }

        // Set edit link
        document.getElementById('editChecklistBtn').href =
            '/scada-checklist-system/views/user/checklist.php?swo_id=' + swo.id;
        document.getElementById('feedbackActions').classList.remove('hidden');

        if (!checklistData || !checklistData.success) {
            document.getElementById('feedbackContent').innerHTML =
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

        // Determine which feedback to show based on status
        const showSupportFeedback = swo.status === 'In Progress' && swo.rejection_reason;
        const showControlFeedback = swo.status === 'Returned from Control';

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

                let feedbackComment = '';
                if (showSupportFeedback && sr.support_comment) {
                    feedbackComment = sr.support_comment;
                } else if (showControlFeedback && cr.control_comment) {
                    feedbackComment = cr.control_comment;
                }

                rowsHtml += `
                    <tr>
                        <td style="text-align:center">${rowNum}</td>
                        <td>${escapeHtml(item.label)}</td>
                        <td>${getChecklistStatusBadge(item.status)}</td>
                        <td>${feedbackComment
                            ? `<div class="feedback-alert"><strong>Feedback:</strong> ${escapeHtml(feedbackComment)}</div>`
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
                                    <th style="width:110px">Your Status</th>
                                    <th>Reviewer Feedback</th>
                                </tr>
                            </thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        document.getElementById('feedbackContent').innerHTML = html || '<p class="text-muted">No checklist items found.</p>';

    } catch (err) {
        console.error(err);
        document.getElementById('feedbackContent').innerHTML =
            '<div class="alert alert-danger">Error loading feedback data.</div>';
    }
}

document.addEventListener('DOMContentLoaded', loadFeedback);
</script>
