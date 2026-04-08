<?php
$pageTitle = 'Create SWO';
require_once __DIR__ . '/../../config/functions.php';
requireRole('support');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Create New SWO</h1>
        </div>
        <div class="topbar-actions">
            <a href="/scada-checklist-system/views/support/index.php" class="btn btn-secondary btn-sm">← Back</a>
        </div>
    </div>

    <div class="page-content">
        <div class="card" style="max-width:700px;margin:0 auto;">
            <div class="card-header">
                <h3 class="card-title">SWO Details</h3>
            </div>

            <div id="alertMsg"></div>

            <form id="createSwoForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="swo_number">SWO Number *</label>
                        <input type="text" id="swo_number" class="form-control" placeholder="e.g., SWO-2024-004" required>
                    </div>
                    <div class="form-group">
                        <label for="station_name">Station Name *</label>
                        <input type="text" id="station_name" class="form-control" placeholder="e.g., Substation Delta" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="swo_type">SWO Type *</label>
                        <select id="swo_type" class="form-control" required>
                            <option value="">-- Select Type --</option>
                            <option value="Configuration">Configuration</option>
                            <option value="Commissioning">Commissioning</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Upgrade">Upgrade</option>
                            <option value="Troubleshooting">Troubleshooting</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="kcor">KCOR</label>
                        <input type="text" id="kcor" class="form-control" placeholder="e.g., KCOR-004">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" class="form-control" rows="4" placeholder="Describe the scope of work..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="submitSWO('draft')">💾 Save as Draft</button>
                    <button type="button" class="btn btn-primary" onclick="submitSWO('submit')">📤 Submit for Approval</button>
                </div>
            </form>
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

async function submitSWO(action) {
    const form = document.getElementById('createSwoForm');
    const swo_number = document.getElementById('swo_number').value.trim();
    const station_name = document.getElementById('station_name').value.trim();
    const swo_type = document.getElementById('swo_type').value;

    if (!swo_number || !station_name || !swo_type) {
        showError('Please fill in all required fields.');
        return;
    }

    const payload = {
        swo_number,
        station_name,
        swo_type,
        kcor: document.getElementById('kcor').value.trim(),
        description: document.getElementById('description').value.trim(),
        action
    };

    const btns = form.querySelectorAll('button');
    btns.forEach(b => b.disabled = true);

    try {
        const data = await API.post('/swo/create_swo.php', payload);
        if (data && data.success) {
            showSuccess(action === 'submit' ? 'SWO submitted for approval!' : 'SWO saved as draft!');
            setTimeout(() => window.location.href = '/scada-checklist-system/views/support/my_swo.php', 1500);
        } else {
            showError(data?.message || 'Failed to create SWO');
            btns.forEach(b => b.disabled = false);
        }
    } catch (err) {
        showError('Connection error');
        btns.forEach(b => b.disabled = false);
    }
}
</script>
