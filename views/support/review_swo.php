<?php
$pageTitle = 'Support Review';
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
<link rel="stylesheet" href="/scada-checklist-system/assets/css/review_page.css">

<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title" id="reviewTitle">Loading…</h1>
        </div>
        <div class="topbar-actions" style="display:flex;align-items:center;gap:12px;">
            <span class="save-indicator hidden" id="saveIndicator"></span>
            <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <div class="review-page" id="reviewContent">

            <!-- Info bar (populated by JS) -->
            <div class="review-info-bar">
                <div class="info-item">
                    <span>📍</span>
                    <strong id="reviewStation">—</strong>
                </div>
                <div class="info-item">
                    <span>Status:</span>
                    <span class="badge badge-submitted">Pending Support Review</span>
                </div>
            </div>

            <!-- Progress -->
            <div class="review-progress">
                <div class="review-progress-header">
                    <span>Overall Progress</span>
                    <strong class="review-progress-pct" id="reviewProgressPct">—</strong>
                </div>
                <div class="progress-bar-wrapper" style="height:10px;">
                    <div class="progress-bar" id="reviewProgressBar" style="width:0%"></div>
                </div>
                <div class="review-progress-text" id="reviewProgressText"></div>
            </div>

            <!-- Checklist sections (populated by JS) -->
            <div id="reviewSections">
                <div class="loading-overlay"><div class="loading-spinner"></div></div>
            </div>

            <!-- Overall comments -->
            <div class="review-overall-comments">
                <label for="overallCommentsTextarea">Overall Review Comments:</label>
                <textarea id="overallCommentsTextarea"
                          rows="4"
                          placeholder="Add overall review comments here… (auto-saved on blur)"></textarea>
            </div>

            <!-- Action buttons -->
            <div class="review-actions">
                <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-danger" id="rejectBtn"
                        onclick="SupportReview.rejectAndSendToUser()">
                    ❌ Reject (Back to User)
                </button>
                <button class="btn btn-success" id="acceptBtn"
                        onclick="SupportReview.acceptAndSendToControl()">
                    ✅ Accept (Send to Control)
                </button>
            </div>

        </div><!-- /review-page -->
    </div><!-- /page-content -->
</div><!-- /main-content -->

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/support_review.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function () {
    SupportReview.init(<?php echo $swo_id; ?>);
});
</script>
