/* checklist_items.js - Admin Checklist Items Management */

const ChecklistItems = {
    _items: [],
    _sectionCounts: {},   // {section: maxNumber} cached per load

    async load() {
        const tbody   = document.getElementById('ciTableBody');
        const section = document.getElementById('ciSectionFilter')?.value || '';
        const search  = document.getElementById('ciSearchFilter')?.value  || '';

        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const data = await API.get('/checklist/get_items_list.php', { section, search });
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load items.</td></tr>';
                return;
            }

            this._items = data.data.items || [];
            this._updateStats(data.data);
            this._renderTable(this._items, tbody);
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Connection error.</td></tr>';
        }
    },

    _updateStats(data) {
        const bySection = data.by_section || [];

        const el = (id) => document.getElementById(id);
        if (el('ciStatTotal'))        el('ciStatTotal').textContent        = data.total_items  ?? '—';
        if (el('ciStatActive'))       el('ciStatActive').textContent       = data.active_items ?? '—';

        let configCount       = '—';
        let commissionCount   = '—';
        this._sectionCounts   = {};

        bySection.forEach(s => {
            // Track max number per section for auto-suggest
            const sectionItems = this._items.filter(i => i.section === s.section);
            const maxNum = sectionItems.reduce((m, i) => Math.max(m, parseInt(i.section_number) || 0), 0);
            this._sectionCounts[s.section] = maxNum;

            if (s.section === 'during_config')       configCount     = s.total;
            if (s.section === 'during_commissioning') commissionCount = s.total;
        });

        if (el('ciStatConfig'))        el('ciStatConfig').textContent        = configCount;
        if (el('ciStatCommissioning')) el('ciStatCommissioning').textContent = commissionCount;
    },

    _renderTable(items, tbody) {
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No checklist items found.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const activeBadge = item.is_active == 1
                ? '<span class="badge" style="background:#27ae60;color:#fff">Active</span>'
                : '<span class="badge" style="background:#e74c3c;color:#fff">Inactive</span>';

            const toggleLabel  = item.is_active == 1 ? '⏸ Deactivate' : '▶ Activate';
            const toggleClass  = item.is_active == 1 ? 'btn-warning' : 'btn-success';
            const newActive    = item.is_active == 1 ? 0 : 1;

            return `
            <tr>
                <td>${escapeHtml(item.section_label)}</td>
                <td>${escapeHtml(item.section_number)}</td>
                <td style="max-width:280px;">${escapeHtml(item.description)}</td>
                <td><code style="font-size:11px;background:#f4f4f4;padding:2px 5px;border-radius:3px;">${escapeHtml(item.item_key)}</code></td>
                <td>${activeBadge}</td>
                <td>${escapeHtml(item.created_by_name || '—')}</td>
                <td style="white-space:nowrap;">${formatDateShort(item.created_at)}</td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <button class="btn btn-secondary btn-sm ci-edit-btn"
                            data-id="${escapeHtml(item.id)}"
                            data-key="${escapeHtml(item.item_key)}"
                            data-desc="${escapeHtml(item.description)}">✏️ Edit</button>
                        <button class="btn ${toggleClass} btn-sm ci-toggle-btn"
                            data-id="${escapeHtml(item.id)}"
                            data-active="${newActive}">${toggleLabel}</button>
                        <button class="btn btn-danger btn-sm ci-delete-btn"
                            data-id="${escapeHtml(item.id)}"
                            data-key="${escapeHtml(item.item_key)}">🗑 Delete</button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        // Attach event listeners via delegation
        tbody.querySelectorAll('.ci-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                ChecklistItems.openEditModal(
                    btn.dataset.id,
                    btn.dataset.key,
                    btn.dataset.desc
                );
            });
        });
        tbody.querySelectorAll('.ci-toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                ChecklistItems.toggleStatus(btn.dataset.id, btn.dataset.active);
            });
        });
        tbody.querySelectorAll('.ci-delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                ChecklistItems.deleteItem(btn.dataset.id, btn.dataset.key);
            });
        });
    },

    openAddModal() {
        const el = (id) => document.getElementById(id);
        el('ciAddSection').value     = '';
        el('ciAddNumber').value      = '';
        el('ciAddDescription').value = '';
        openModal('addChecklistItemModal');
    },

    suggestNumber() {
        const section = document.getElementById('ciAddSection').value;
        if (!section) return;
        const max = this._sectionCounts[section] || 0;
        document.getElementById('ciAddNumber').value = max + 1;
    },

    async submitAdd() {
        const section     = document.getElementById('ciAddSection').value;
        const sectionNum  = parseInt(document.getElementById('ciAddNumber').value);
        const description = document.getElementById('ciAddDescription').value.trim();

        if (!section)       { showWarning('Please select a section.');       return; }
        if (!sectionNum || sectionNum < 1) { showWarning('Please enter a valid item number.'); return; }
        if (!description)   { showWarning('Please enter a description.');     return; }

        const data = await API.post('/checklist/add_item.php', { section, section_number: sectionNum, description });
        if (data && data.success) {
            showSuccess('Checklist item added successfully!');
            closeModal('addChecklistItemModal');
            this.load();
        } else {
            showError(data?.message || 'Failed to add item');
        }
    },

    openEditModal(id, key, description) {
        document.getElementById('ciEditId').value          = id;
        document.getElementById('ciEditKey').value         = key;
        document.getElementById('ciEditDescription').value = description;
        openModal('editChecklistItemModal');
    },

    async submitEdit() {
        const item_id     = document.getElementById('ciEditId').value;
        const description = document.getElementById('ciEditDescription').value.trim();

        if (!description) { showWarning('Please enter a description.'); return; }

        const data = await API.post('/checklist/update_item.php', { item_id, description });
        if (data && data.success) {
            showSuccess('Item updated successfully!');
            closeModal('editChecklistItemModal');
            this.load();
        } else {
            showError(data?.message || 'Failed to update item');
        }
    },

    async toggleStatus(item_id, is_active) {
        const action = parseInt(is_active) ? 'activate' : 'deactivate';
        const confirmed = await confirmDialog(`Are you sure you want to ${action} this item?\n\nNote: Existing SWO checklists will NOT be affected.`);
        if (!confirmed) return;

        const data = await API.post('/checklist/toggle_item_status.php', { item_id, is_active });
        if (data && data.success) {
            showSuccess(data.message || 'Status updated');
            this.load();
        } else {
            showError(data?.message || 'Failed to update status');
        }
    },

    async deleteItem(item_id, item_key) {
        const confirmed = await confirmDialog(
            `Delete checklist item "${item_key}"?\n\n` +
            `• If used in existing SWOs: will be soft-deleted (hidden from new SWOs but old data preserved)\n` +
            `• If never used: will be permanently deleted`
        );
        if (!confirmed) return;

        const data = await API.post('/checklist/delete_item.php', { item_id });
        if (data && data.success) {
            showSuccess(data.message || 'Item deleted');
            this.load();
        } else {
            showError(data?.message || 'Failed to delete item');
        }
    }
};

// Auto-load when the checklist items tab is clicked; attach filter listeners
document.addEventListener('DOMContentLoaded', function() {
    const tabBtn = document.querySelector('[data-tab="tab-checklist-items"]');
    if (tabBtn) {
        tabBtn.addEventListener('click', function() {
            if (!ChecklistItems._items.length) {
                ChecklistItems.load();
            }
        });
    }

    const sectionFilter = document.getElementById('ciSectionFilter');
    const searchFilter  = document.getElementById('ciSearchFilter');
    if (sectionFilter) sectionFilter.addEventListener('change', () => ChecklistItems.load());
    if (searchFilter)  searchFilter.addEventListener('input',  () => ChecklistItems.load());
});
