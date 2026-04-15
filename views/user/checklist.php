<?php
$pageTitle = 'Checklist';
require_once __DIR__ . '/../../config/functions.php';
requireRole('user');

$swo_id = isset($_GET['swo_id']) ? intval($_GET['swo_id']) : null;
if (!$swo_id) {
    header('Location: /scada-checklist-system/views/user/index.php');
    exit;
}

// Verify assignment
require_once __DIR__ . '/../../config/db_config.php';
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT swo_number, station_name, swo_type, swo_type_id, kcor, status, rejection_reason FROM swo_list WHERE id = ? AND assigned_to = ?");
$stmt->bind_param('ii', $swo_id, $_SESSION['user_id']);
$stmt->execute();
$swo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$swo) {
    $conn->close();
    header('Location: /scada-checklist-system/views/user/index.php');
    exit;
}

// Determine whether the Support Comment column should be shown:
// true if rejection_reason is set OR if there are saved support item reviews
$hasSupportReviews = !empty($swo['rejection_reason']);
if (!$hasSupportReviews) {
    $checkStmt = $conn->prepare("SELECT 1 FROM support_item_reviews WHERE swo_id = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('i', $swo_id);
        $checkStmt->execute();
        $hasSupportReviews = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
    }
}

$conn->close();

$readOnly = !in_array($swo['status'], ['In Progress']);
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div>
                <h1 class="topbar-title" style="margin:0;"><?php echo htmlspecialchars($swo['swo_number']); ?></h1>
                <div style="font-size:12px;color:#666;"><?php echo htmlspecialchars($swo['station_name']); ?> &mdash; <?php echo htmlspecialchars($swo['swo_type']); ?></div>
            </div>
        </div>
        <div class="topbar-actions">
            <span class="save-indicator hidden" id="saveIndicator"></span>
            <a href="/scada-checklist-system/views/user/index.php" class="btn btn-secondary btn-sm">← Back</a>
            <button class="btn btn-secondary btn-sm" onclick="ChecklistPage.exportCSV()">📥 CSV</button>
            <?php if (!$readOnly): ?>
            <button class="btn btn-success btn-sm" id="submitBtn" onclick="ChecklistPage.submitChecklist()" disabled title="Complete all items first">
                📤 Submit
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-content">
        <?php if ($readOnly): ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
            <?php if ($swo['status'] === 'Pending Support Review'): ?>
                ℹ️ This checklist has been submitted and is awaiting Support review.
            <?php elseif ($swo['status'] === 'Pending Control Review'): ?>
                ℹ️ This checklist has been accepted by Support and is awaiting Control approval.
            <?php elseif ($swo['status'] === 'Returned from Control'): ?>
                🔄 This checklist was returned by Control and is currently under Support re-review.
            <?php elseif ($swo['status'] === 'Completed'): ?>
                ✅ This checklist has been approved by Control and is Completed.
            <?php else: ?>
                ℹ️ This checklist is currently under review (<?php echo htmlspecialchars($swo['status']); ?>).
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Progress Card -->
        <div class="card" style="margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <strong style="font-size:15px;">Overall Progress</strong>
                <strong style="font-size:18px;color:#2e86de;" id="progressPct">—</strong>
            </div>
            <div class="progress-bar-wrapper" style="height:12px;">
                <div class="progress-bar" id="progressBar" style="width:0%"></div>
            </div>
            <div class="progress-text" id="progressText"></div>
        </div>

        <!-- Checklist Sections -->
        <div id="checklistContainer">
            <div class="loading-overlay"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/checklist.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}
document.addEventListener('DOMContentLoaded', function() {
    ChecklistPage.init(
        <?php echo $swo_id; ?>,
        <?php echo $readOnly ? 'true' : 'false'; ?>,
        <?php echo json_encode($swo['status']); ?>,
        <?php echo $hasSupportReviews ? 'true' : 'false'; ?>,
        <?php echo !empty($swo['swo_type_id']) ? intval($swo['swo_type_id']) : 'null'; ?>
    );
});
</script>
