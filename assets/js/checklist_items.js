/* checklist_items.js - Admin Checklist Items Management */

const ChecklistItems = {
    _items: [],
    _parentItems: [],
    _sectionCounts: {},   // {section: maxNumber} cached per load
    _suggestReqId: 0,

    async load() {
        const tbody   = document.getElementById('ciTableBody');
        const section = document.getElementById('ciSectionFilter')?.value || '';
        const search  = document.getElementById('ciSearchFilter')?.value  || '';
        const status  = document.getElementById('ciStatusFilter')?.value  || 'all';
        const swo_type_id = document.getElementById('ciSwoTypeFilter')?.value || '';

        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const params = { section, search, status };
            if (swo_type_id) params.swo_type_id = swo_type_id;

            const data = await API.get('/checklist/get_items_list.php', params);
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Failed to load items.</td></tr>';
                return;
            }

            this._items = data.data.items || [];
            this._parentItems = data.data.parent_items || [];
            this._updateStats(data.data);
            this._renderTable(this._items, tbody);
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Connection error.</td></tr>';
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
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No checklist items found.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const activeBadge = item.is_active == 1
                ? '<span class="badge" style="background:#27ae60;color:#fff">Active</span>'
                : '<span class="badge" style="background:#e74c3c;color:#fff">Inactive</span>';

            const toggleLabel  = item.is_active == 1 ? '⏸ Deactivate' : '▶ Activate';
            const toggleClass  = item.is_active == 1 ? 'btn-warning' : 'btn-success';

            // Hierarchy: indent description and add ↳ prefix for child items
            const isChild = !!item.parent_item_id;
            const childPrefix = isChild ? '<span style="color:#aaa;margin-right:4px;">↳</span>' : '';
            const descStyle = isChild
                ? 'max-width:280px;padding-left:20px;'
                : 'max-width:280px;';

            const parentBadge = (item.sub_items_count > 0)
                ? ' <span class="badge" style="background:#3498db;color:#fff;font-size:10px;">Parent</span>'
                : '';

            const usageCount = parseInt(item.usage_count) || 0;
            const usageBadge = usageCount > 0
                ? `<span class="badge" style="background:#f39c12;color:#fff" title="Used in ${usageCount} SWO(s)">⚠️ ${usageCount} SWO${usageCount > 1 ? 's' : ''}</span>`
                : '<span class="text-muted">—</span>';

            const swoTypeName = item.swo_type_name && item.swo_type_name !== '—' ? item.swo_type_name : null;
            const swoTypeBadge = swoTypeName
                ? `<span class="badge" style="background:#9b59b6;color:#fff;">${escapeHtml(swoTypeName)}</span>`
                : '<span class="text-muted">—</span>';

            return `
            <tr>
                <td>${escapeHtml(item.section_label)}</td>
                <td>${escapeHtml(String(item.section_number))}</td>
                <td style="${descStyle}">${childPrefix}${escapeHtml(item.description)}</td>
                <td><code style="font-size:11px;background:#f4f4f4;padding:2px 5px;border-radius:3px;">${escapeHtml(item.item_key)}</code></td>
                <td>${swoTypeBadge}</td>
                <td>${activeBadge}${parentBadge}</td>
                <td>${escapeHtml(item.created_by_name || '—')}</td>
                <td style="white-space:nowrap;">${formatDateShort(item.created_at)}</td>
                <td>${usageBadge}</td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
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
        el('ciAddSection').disabled  = false;
        el('ciAddNumber').value      = '';
        el('ciAddDescription').value = '';
        if (el('ciAddSwoType'))    el('ciAddSwoType').value    = '';
        if (el('ciAddParentItem')) el('ciAddParentItem').innerHTML = '<option value="">-- No Parent (Top-level Item) --</option>';
        openModal('addChecklistItemModal');
    },

    async suggestNumber() {
        const sectionEl = document.getElementById('ciAddSection');
        const numberEl = document.getElementById('ciAddNumber');
        const swoTypeEl = document.getElementById('ciAddSwoType');
        const parentEl = document.getElementById('ciAddParentItem');
        if (!sectionEl || !numberEl) return;

        const section = sectionEl.value;
        if (!section) {
            numberEl.value = '';
            return;
        }

        const params = { section };
        const swo_type_id = parseInt(swoTypeEl?.value || '') || 0;
        const parent_item_id = parseInt(parentEl?.value || '') || 0;
        if (swo_type_id) params.swo_type_id = swo_type_id;
        if (parent_item_id) params.parent_item_id = parent_item_id;

        const reqId = ++this._suggestReqId;
        try {
            const data = await API.get('/checklist/suggest_number.php', params);
            if (reqId !== this._suggestReqId) return;
            if (data && data.success && data.data) {
                numberEl.value = data.data.suggested_number || '';
            } else {
                const max = this._sectionCounts[section] || 0;
                numberEl.value = max + 1;
            }
        } catch (err) {
            const max = this._sectionCounts[section] || 0;
            numberEl.value = max + 1;
        }
    },

    onSwoTypeChange() {
        this.refreshParentListAndNumber();
    },

    refreshParentListAndNumber() {
        const swo_type_id = document.getElementById('ciAddSwoType')?.value;
        const section = document.getElementById('ciAddSection')?.value;
        const parentSelect = document.getElementById('ciAddParentItem');
        if (!parentSelect) return;

        if (!swo_type_id) {
            parentSelect.innerHTML = '<option value="">-- No Parent (Top-level Item) --</option>';
            this.suggestNumber();
            return;
        }

        // Use _parentItems (from API) if available, otherwise fall back to filtering _items
        const source = this._parentItems.length
            ? this._parentItems
            : this._items.filter(i => !i.parent_item_id);

        const matching = source.filter(i => {
            const sameType = String(i.swo_type_id) === String(swo_type_id);
            const sameSection = !section || i.section === section;
            return sameType && sameSection;
        });
        parentSelect.innerHTML = '<option value="">-- No Parent (Top-level Item) --</option>' +
            matching.map(i => `<option value="${i.id}">${escapeHtml(i.section_label || i.section)} #${escapeHtml(String(i.section_number))} — ${escapeHtml(i.description)}</option>`).join('');
        this.suggestNumber();
    },

    onParentItemChange() {
        const parentEl  = document.getElementById('ciAddParentItem');
        const sectionEl = document.getElementById('ciAddSection');
        const typeEl = document.getElementById('ciAddSwoType');
        if (!parentEl || !sectionEl) return;

        const parent_id = parseInt(parentEl.value) || 0;
        if (!parent_id) {
            // No parent selected — re-enable manual section selection
            sectionEl.disabled = false;
            this.suggestNumber();
            return;
        }

        // Find the parent item to auto-set the section
        const source = this._parentItems.length
            ? this._parentItems
            : this._items.filter(i => !i.parent_item_id);
        const parent = source.find(i => String(i.id) === String(parent_id));
        if (parent && parent.section) {
            sectionEl.value    = parent.section;
            sectionEl.disabled = true; // Lock to match parent section
            if (typeEl && parent.swo_type_id) {
                typeEl.value = String(parent.swo_type_id);
            }
        }
        this.suggestNumber();
    },

    // في checklist_items.js - تحديث submitAdd function:

async submitAdd() {
    const sectionEl   = document.getElementById('ciAddSection');
    const numberEl    = document.getElementById('ciAddNumber');
    const descEl      = document.getElementById('ciAddDescription');
    const swoTypeEl   = document.getElementById('ciAddSwoType');
    const parentEl    = document.getElementById('ciAddParentItem');
    
    const section     = (sectionEl.value || '').trim();
    const sectionNum  = parseInt(numberEl.value) || 0;
    const description = (descEl.value || '').trim();
    const swo_type_id = swoTypeEl ? (parseInt(swoTypeEl.value) || 0) : 0;
    const parent_item_id = parentEl ? (parseInt(parentEl.value) || 0) : 0;

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
        if (swo_type_id) payload.swo_type_id = swo_type_id;
        if (parent_item_id) payload.parent_item_id = parent_item_id;
        
        const data = await API.post('/checklist/add_item.php', payload);
        
        if (data && data.success) {
            showSuccess('✅ Item added: ' + description);
            sectionEl.value = '';
            numberEl.value = '';
            descEl.value = '';
            if (swoTypeEl)  swoTypeEl.value = '';
            if (parentEl)   parentEl.value = '';
            closeModal('addChecklistItemModal');
            setTimeout(() => this.load(), 500);
        } else {
            showError(data?.message || 'Failed to add item');
        }
    } catch (err) {
        showError('Error: ' + err.message);
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
    const swoTypeFilter = document.getElementById('ciSwoTypeFilter');
    if (sectionFilter) sectionFilter.addEventListener('change', () => ChecklistItems.load());
    if (statusFilter)  statusFilter.addEventListener('change',  () => ChecklistItems.load());
    if (searchFilter)  searchFilter.addEventListener('input',  () => ChecklistItems.load());
    if (swoTypeFilter) swoTypeFilter.addEventListener('change', () => ChecklistItems.load());

    const addSection = document.getElementById('ciAddSection');
    if (addSection) addSection.addEventListener('change', () => ChecklistItems.refreshParentListAndNumber());
});
