<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../config/functions.php';
requireRole('admin');
require_once __DIR__ . '/../components/header.php';
require_once __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <div class="topbar">
        <div class="topbar-heading">
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <h1 class="topbar-title">Admin Dashboard</h1>
        </div>
        <div class="topbar-actions">
            <span class="welcome-text">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
        </div>
    </div>

    <div class="page-content">
        <!-- Summary Stats -->
        <div class="stats-grid" id="adminStatsContainer">
            <div class="stat-card">
                <div class="stat-value" id="statTotal">—</div>
                <div class="stat-label">Total SWOs</div>
            </div>
            <div class="stat-card border-warning">
                <div class="stat-value" id="statPending">—</div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card border-success">
                <div class="stat-value" id="statInProgress">—</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card border-info">
                <div class="stat-value" id="statSubmitted">—</div>
                <div class="stat-label">Pending Support Review</div>
            </div>
        </div>

        <!-- Tabs -->
        <div id="mainTabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="tab-statistics">📈 Statistics</button>
                <button class="tab-btn" data-tab="tab-swo">📋 SWO Management</button>
                <button class="tab-btn" data-tab="tab-activity">🕐 Recent Activity</button>
                <button class="tab-btn" data-tab="tab-checklist-items">📝 Manage Checklist Items</button>
                <button class="tab-btn" data-tab="tab-swo-types">📌 Manage SWO Types</button>
                <button class="tab-btn" data-tab="tab-timeline">📊 SWO Timeline</button>
            </div>

            <!-- Tab: Statistics -->
            <div class="tab-content active" id="tab-statistics">
                <h3>System User Performance</h3>
                <div class="user-cards-grid" id="userCardsGrid">
                    <div class="loading-overlay"><div class="loading-spinner"></div></div>
                </div>
            </div>

            <!-- Tab: SWO Management -->
            <div class="tab-content" id="tab-swo">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All SWOs</h3>
                        <div class="filter-bar filter-bar-compact">
                            <select id="statusFilter" class="form-control form-control-auto">
                                <option value="">All Statuses</option>
                                <option value="Draft">Draft</option>
                                <option value="Pending">Pending</option>
                                <option value="Registered">Registered</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Pending Support Review">Pending Support Review</option>
                                <option value="Pending Control Review">Pending Control Review</option>
                                <option value="Returned from Control">Returned from Control</option>
                                <option value="Submitted">Submitted</option>
                                <option value="Completed">Completed</option>
                                <option value="Closed">Closed</option>
                            </select>
                            <button class="btn btn-primary btn-sm ci-refresh-btn" onclick="AdminDashboard.loadSWOTable(document.getElementById('statusFilter').value)">🔄 Refresh</button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>SWO Number</th>
                                    <th>Station</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="swoTableBody">
                                <tr><td colspan="7" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Recent Activity -->
            <div class="tab-content" id="tab-activity">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div id="recentActivityList">
                        <div class="loading-overlay"><div class="loading-spinner"></div></div>
                    </div>
                </div>
            </div>

            <!-- Tab: Manage Checklist Items -->
            <div class="tab-content" id="tab-checklist-items">
                <!-- Statistics Cards -->
                <div class="stats-grid" id="checklistItemsStats">
                    <div class="stat-card">
                        <div class="stat-value" id="ciStatTotal">—</div>
                        <div class="stat-label">Total Items</div>
                    </div>
                    <div class="stat-card border-success">
                        <div class="stat-value" id="ciStatActive">—</div>
                        <div class="stat-label">Active Items</div>
                    </div>
                    <div class="stat-card border-info">
                        <div class="stat-value" id="ciStatConfig">—</div>
                        <div class="stat-label">During Config</div>
                    </div>
                    <div class="stat-card border-warning">
                        <div class="stat-value" id="ciStatCommissioning">—</div>
                        <div class="stat-label">During Commissioning</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header ci-card-header">
                        <h3 class="card-title">Checklist Items</h3>
                        <div class="ci-card-actions">
                            <select id="ciSectionFilter" class="form-control form-control-auto">
                                <option value="">All Sections</option>
                                <option value="during_config">During Configuration</option>
                                <option value="during_commissioning">During Commissioning</option>
                                <option value="after_commissioning">After Commissioning</option>
                            </select>
                            <select id="ciStatusFilter" class="form-control form-control-auto">
                                <option value="all">All Items</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                            <select id="ciSwoTypeFilter" class="form-control form-control-auto">
                                <option value="">All Types</option>
                            </select>
                            <input type="text" id="ciSearchFilter" class="form-control search-control-wide" placeholder="🔍 Search description…">
                            <button class="btn btn-primary btn-sm" onclick="ChecklistItems.openAddModal()">+ Add Item</button>
                            <button class="btn btn-secondary btn-sm ci-refresh-btn" onclick="ChecklistItems.load()">🔄 Refresh</button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="ci-items-table">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>Key</th>
                                    <th>SWO Type</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created</th>
                                    <th>Used In</th>
                                    <th>Role Mapping</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ciTableBody">
                                <tr><td colspan="11" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Manage SWO Types -->
            <div class="tab-content" id="tab-swo-types">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">SWO Types</h3>
                        <button class="btn btn-primary btn-sm" onclick="SWOTypes.openAddModal()">+ Add Type</button>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="swoTypesTableBody">
                                <tr><td colspan="5" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: SWO Timeline -->
            <div class="tab-content" id="tab-timeline">
                <?php include __DIR__ . '/swo_timeline.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Checklist Item Modal -->
<div class="modal-overlay" id="addChecklistItemModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Checklist Item</span>
            <button class="modal-close" onclick="closeModal('addChecklistItemModal')">×</button>
        </div>
        <div class="form-group">
            <label>Section *</label>
            <select id="ciAddSection" class="form-control" onchange="ChecklistItems.suggestNumber()">
                <option value="">-- Select Section --</option>
                <option value="during_config">During Configuration</option>
                <option value="during_commissioning">During Commissioning</option>
                <option value="after_commissioning">After Commissioning</option>
            </select>
        </div>
        <div class="form-group">
            <label>SWO Type <small class="text-muted">(Optional)</small></label>
            <select id="ciAddSwoType" class="form-control" onchange="ChecklistItems.onSwoTypeChange()">
                <option value="">-- Select SWO Type --</option>
            </select>
        </div>
        <div class="form-group">
            <label>Role Visibility & Child Mapping *</label>
            <div class="ci-role-grid">
                <div class="ci-role-card">
                    <label class="ci-role-title">
                        <input type="checkbox" id="ciAddRoleUserVisible" checked>
                        <span>User</span>
                    </label>
                    <label class="ci-role-child-label">Child</label>
                    <select id="ciAddRoleUserParent" class="form-control">
                        <option value="">-- No Parent (Top-level) --</option>
                    </select>
                </div>
                <div class="ci-role-card">
                    <label class="ci-role-title">
                        <input type="checkbox" id="ciAddRoleSupportVisible" checked>
                        <span>Support</span>
                    </label>
                    <label class="ci-role-child-label">Child</label>
                    <select id="ciAddRoleSupportParent" class="form-control">
                        <option value="">-- No Parent (Top-level) --</option>
                    </select>
                </div>
                <div class="ci-role-card">
                    <label class="ci-role-title">
                        <input type="checkbox" id="ciAddRoleControlVisible" checked>
                        <span>Control</span>
                    </label>
                    <label class="ci-role-child-label">Child</label>
                    <select id="ciAddRoleControlParent" class="form-control">
                        <option value="">-- No Parent (Top-level) --</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Item Number * <small class="text-muted">(auto-suggested)</small></label>
            <input type="number" id="ciAddNumber" class="form-control" min="1" max="99" placeholder="e.g. 9">
        </div>
        <div class="form-group">
            <label>Description *</label>
            <textarea id="ciAddDescription" class="form-control" rows="3" placeholder="Enter checklist item description…"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addChecklistItemModal')">Cancel</button>
            <button class="btn btn-primary" onclick="ChecklistItems.submitAdd()">Create Item</button>
        </div>
    </div>
</div>

<!-- Checklist Item Role Mapping Modal -->
<div class="modal-overlay" id="checklistItemRoleMapModal">
    <div class="modal ci-role-map-modal">
        <div class="modal-header">
            <span class="modal-title">Role Mapping</span>
            <button class="modal-close" onclick="closeModal('checklistItemRoleMapModal')">×</button>
        </div>
        <input type="hidden" id="ciRoleMapItemId">
        <div class="ci-role-map-title" id="ciRoleMapItemTitle"></div>
        <div class="ci-role-grid">
            <div class="ci-role-card">
                <label class="ci-role-title">
                    <input type="checkbox" id="ciRoleMapUserVisible" checked>
                    <span>User</span>
                </label>
                <label class="ci-role-child-label">Child</label>
                <select id="ciRoleMapUserParent" class="form-control">
                    <option value="">-- No Parent (Top-level) --</option>
                </select>
            </div>
            <div class="ci-role-card">
                <label class="ci-role-title">
                    <input type="checkbox" id="ciRoleMapSupportVisible" checked>
                    <span>Support</span>
                </label>
                <label class="ci-role-child-label">Child</label>
                <select id="ciRoleMapSupportParent" class="form-control">
                    <option value="">-- No Parent (Top-level) --</option>
                </select>
            </div>
            <div class="ci-role-card">
                <label class="ci-role-title">
                    <input type="checkbox" id="ciRoleMapControlVisible" checked>
                    <span>Control</span>
                </label>
                <label class="ci-role-child-label">Child</label>
                <select id="ciRoleMapControlParent" class="form-control">
                    <option value="">-- No Parent (Top-level) --</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('checklistItemRoleMapModal')">Cancel</button>
            <button class="btn btn-primary" onclick="ChecklistItems.submitRoleMapping()">Save</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Reject SWO</span>
            <button class="modal-close" onclick="closeModal('rejectModal')">×</button>
        </div>
        <input type="hidden" id="rejectSwoId">
        <div class="form-group">
            <label>Rejection Reason *</label>
            <textarea id="rejectReason" class="form-control" placeholder="Enter reason for rejection..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn btn-danger" onclick="AdminDashboard.submitReject()">Reject SWO</button>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Assign SWO</span>
            <button class="modal-close" onclick="closeModal('assignModal')">×</button>
        </div>
        <input type="hidden" id="assignSwoId">
        <div class="form-group">
            <label>Assign to System User *</label>
            <select id="assignUserSelect" class="form-control">
                <option value="">-- Select User --</option>
            </select>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
            <button class="btn btn-primary" onclick="AdminDashboard.submitAssign()">Assign</button>
        </div>
    </div>
</div>

<!-- View SWO Modal -->
<div class="modal-overlay" id="viewSwoModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">SWO Details</span>
            <button class="modal-close" onclick="closeModal('viewSwoModal')">×</button>
        </div>
        <div id="viewSwoContent"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('viewSwoModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add SWO Type Modal -->
<div class="modal-overlay" id="addSwoTypeModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add SWO Type</span>
            <button class="modal-close" onclick="closeModal('addSwoTypeModal')">×</button>
        </div>
        <div class="form-group">
            <label>Name *</label>
            <input type="text" id="swoTypeAddName" class="form-control" placeholder="e.g., S/S, Power Plant" maxlength="100">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="swoTypeAddDescription" class="form-control" rows="3" placeholder="Optional description…"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addSwoTypeModal')">Cancel</button>
            <button class="btn btn-primary" onclick="SWOTypes.submitAdd()">Add Type</button>
        </div>
    </div>
</div>

<!-- Edit SWO Type Modal -->
<div class="modal-overlay" id="editSwoTypeModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit SWO Type</span>
            <button class="modal-close" onclick="closeModal('editSwoTypeModal')">×</button>
        </div>
        <input type="hidden" id="swoTypeEditId">
        <div class="form-group">
            <label>Name *</label>
            <input type="text" id="swoTypeEditName" class="form-control" maxlength="100">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="swoTypeEditDescription" class="form-control" rows="3"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editSwoTypeModal')">Cancel</button>
            <button class="btn btn-primary" onclick="SWOTypes.submitEdit()">Update Type</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>
<script src="/scada-checklist-system/assets/js/utils.js"></script>
<script src="/scada-checklist-system/assets/js/notifications.js"></script>
<script src="/scada-checklist-system/assets/js/api.js"></script>
<script src="/scada-checklist-system/assets/js/dashboard.js"></script>
<script src="/scada-checklist-system/assets/js/checklist_items.js"></script>
<script src="/scada-checklist-system/assets/js/swo_types.js"></script>
<script>
document.body.dataset.page = 'admin';
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

const AdminTimeline = {
    loaded: false,

    async load() {
        const tbody = document.getElementById('timelineTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';
        try {
            const data = await API.get('/swo/get_swo_list.php');
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">Failed to load SWOs.</td></tr>';
                return;
            }
            const swos = data.data || [];
            if (!swos.length) {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted">No SWOs found.</td></tr>';
                return;
            }
            tbody.innerHTML = swos.map(s => `
                <tr>
                    <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                    <td>${escapeHtml(s.station_name)}</td>
                    <td>${getStatusBadge(s.status)}</td>
                    <td>${s.created_at ? formatDateShort(s.created_at) : '—'}</td>
                    <td>${escapeHtml(s.created_by_name || '—')}</td>
                    <td>
                        ${s.approved_at ? formatDateShort(s.approved_at) : '—'}
                        <div class="text-muted" style="font-size:11px;">${escapeHtml(s.approved_by_name || '—')}</div>
                    </td>
                    <td>${escapeHtml(s.assigned_to_name || '—')}</td>
                    <td>${s.started_at ? formatDateShort(s.started_at) : '—'}</td>
                    <td>${s.submitted_at ? formatDateShort(s.submitted_at) : '—'}</td>
                    <td>
                        ${s.support_reviewed_at ? formatDateShort(s.support_reviewed_at) : '—'}
                        <div class="text-muted" style="font-size:11px;">${escapeHtml(s.support_reviewer_name || '—')}</div>
                    </td>
                    <td>
                        ${s.control_reviewed_at ? formatDateShort(s.control_reviewed_at) : '—'}
                        <div class="text-muted" style="font-size:11px;">${escapeHtml(s.control_reviewer_name || '—')}</div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-secondary" onclick="AdminDashboard.viewSWO(${s.id})">View</button>
                    </td>
                </tr>
            `).join('');
            this.loaded = true;
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger">Error loading timeline.</td></tr>';
        }
    }
};

// Load timeline when tab is activated (only first time; Refresh button reloads)
document.addEventListener('DOMContentLoaded', function() {
    const timelineBtn = document.querySelector('[data-tab="tab-timeline"]');
    if (timelineBtn) {
        timelineBtn.addEventListener('click', function() {
            if (!AdminTimeline.loaded) {
                AdminTimeline.load();
            }
        });
    }
});
</script>
