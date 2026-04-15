/* checklist_items.js - Admin Checklist Items Management */

const ChecklistItems = {
    _items: [],
    _sectionCounts: {},   // {section: maxNumber} cached per load

    async load() {
        const tbody   = document.getElementById('ciTableBody');
        const section = document.getElementById('ciSectionFilter')?.value || '';
        const search  = document.getElementById('ciSearchFilter')?.value  || '';
        const status  = document.getElementById('ciStatusFilter')?.value  || 'all';

        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const data = await API.get('/checklist/get_items_list.php', { section, search, status });
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Failed to load items.</td></tr>';
                return;
            }

            this._items = data.data.items || [];
            this._updateStats(data.data);
            this._renderTable(this._items, tbody);
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Connection error.</td></tr>';
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
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No checklist items found.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const activeBadge = item.is_active == 1
                ? '<span class="badge" style="background:#27ae60;color:#fff">Active</span>'
                : '<span class="badge" style="background:#e74c3c;color:#fff">Inactive</span>';

            const toggleLabel  = item.is_active == 1 ? '⏸ Deactivate' : '▶ Activate';
            const toggleClass  = item.is_active == 1 ? 'btn-warning' : 'btn-success';

            const usageCount = parseInt(item.usage_count) || 0;
            const usageBadge = usageCount > 0
                ? `<span class="badge" style="background:#f39c12;color:#fff" title="Used in ${usageCount} SWO(s)">⚠️ ${usageCount} SWO${usageCount > 1 ? 's' : ''}</span>`
                : '<span class="text-muted">—</span>';

            const editDisabled = usageCount > 0
                ? `disabled title="Cannot edit — used in ${usageCount} SWO(s)"`
                : '';

            return `
            <tr>
                <td>${escapeHtml(item.section_label)}</td>
                <td>${escapeHtml(item.section_number)}</td>
                <td style="max-width:280px;">${escapeHtml(item.description)}</td>
                <td><code style="font-size:11px;background:#f4f4f4;padding:2px 5px;border-radius:3px;">${escapeHtml(item.item_key)}</code></td>
                <td>${activeBadge}</td>
                <td>${escapeHtml(item.created_by_name || '—')}</td>
                <td style="white-space:nowrap;">${formatDateShort(item.created_at)}</td>
                <td>${usageBadge}</td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <button class="btn btn-secondary btn-sm ci-edit-btn"
                            data-id="${escapeHtml(item.id)}"
                            data-key="${escapeHtml(item.item_key)}"
                            data-desc="${escapeHtml(item.description)}"
                            ${editDisabled}>✏️ Edit</button>
                        <button class="btn ${toggleClass} btn-sm ci-toggle-btn"
                            data-id="${escapeHtml(item.id)}">${toggleLabel}</button>
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
                ChecklistItems.toggleStatus(btn.dataset.id);
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

    // في checklist_items.js - تحديث submitAdd function:

async submitAdd() {
    const sectionEl   = document.getElementById('ciAddSection');
    const numberEl    = document.getElementById('ciAddNumber');
    const descEl      = document.getElementById('ciAddDescription');
    
    const section     = (sectionEl.value || '').trim();
    const sectionNum  = parseInt(numberEl.value) || 0;
    const description = (descEl.value || '').trim();

    console.log('BEFORE SUBMIT:', { 
        section, 
        sectionNum, 
        description,
        inputValue: numberEl.value,
        inputType: typeof numberEl.value
    });

    if (!section) { 
        showWarning('Please select a section.'); 
        return; 
    }
    if (sectionNum < 1 || sectionNum > 99) { 
        showWarning('Item number must be between 1-99.'); 
        return; 
    }
    if (!description) { 
        showWarning('Please enter a description.'); 
        return; 
    }

    try {
        const payload = { 
            section: section, 
            section_number: sectionNum, 
            description: description
        };
        
        console.log('SENDING TO API:', payload);
        
        const data = await API.post('/checklist/add_item.php', payload);
        
        if (data && data.success) {
            showSuccess('✅ Item added: ' + description);
            sectionEl.value = '';
            numberEl.value = '';
            descEl.value = '';
            closeModal('addChecklistItemModal');
            setTimeout(() => this.load(), 500);
        } else {
            showError(data?.message || 'Failed to add item');
        }
    } catch (err) {
        showError('Error: ' + err.message);
        console.error('API Error:', err);
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

    async toggleStatus(item_id) {
        const confirmed = await confirmDialog(`Are you sure you want to toggle the status of this item?\n\nNote: Existing SWO checklists will NOT be affected.`);
        if (!confirmed) return;

        const data = await API.post('/checklist/toggle_item_status.php', { item_id });
        if (data && data.success) {
            showSuccess(data.message || 'Status updated');
            this.load();
        } else {
            showError(data?.message || 'Failed to update status');
        }
    },

    async deleteItem(item_id, item_key) {
        let msg;
        try {
            const usage = await API.get('/checklist/check_item_usage.php', { item_id });
            if (usage && usage.success && usage.data && usage.data.count > 0) {
                msg = `⚠️ This item is used in ${usage.data.count} SWO(s).\n\n` +
                      `This will be SOFT-DELETED:\n` +
                      `• Existing SWOs can still access their data\n` +
                      `• New SWOs won't see this item\n` +
                      `• The item can be reactivated later\n\n` +
                      `Continue?`;
            } else {
                msg = `Delete checklist item "${item_key}" permanently?\n\n` +
                      `This item is not used in any SWO,\n` +
                      `so it will be permanently removed.`;
            }
        } catch (e) {
            msg = `Delete checklist item "${item_key}"?\n\n` +
                  `• If used in existing SWOs: will be soft-deleted\n` +
                  `• If never used: will be permanently deleted`;
        }

        const confirmed = await confirmDialog(msg);
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
    const statusFilter  = document.getElementById('ciStatusFilter');
    const searchFilter  = document.getElementById('ciSearchFilter');
    if (sectionFilter) sectionFilter.addEventListener('change', () => ChecklistItems.load());
    if (statusFilter)  statusFilter.addEventListener('change',  () => ChecklistItems.load());
    if (searchFilter)  searchFilter.addEventListener('input',  () => ChecklistItems.load());
});
