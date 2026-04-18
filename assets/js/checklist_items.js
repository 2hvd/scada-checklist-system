/* checklist_items.js - Admin Checklist Items Management */

const ChecklistItems = {
    _items: [],
    _parentItems: [],
    _sectionCounts: {},
    _suggestReqId: 0,
    _roleOrder: ['user', 'support', 'control'],
    _editingRoleItemId: null,

    async load() {
        const tbody   = document.getElementById('ciTableBody');
        const section = document.getElementById('ciSectionFilter')?.value || '';
        const search  = document.getElementById('ciSearchFilter')?.value  || '';
        const status  = document.getElementById('ciStatusFilter')?.value  || 'all';
        const swo_type_id = document.getElementById('ciSwoTypeFilter')?.value || '';

        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="11" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const params = { section, search, status };
            if (swo_type_id) params.swo_type_id = swo_type_id;

            const data = await API.get('/checklist/get_items_list.php', params);
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger">Failed to load items.</td></tr>';
                return;
            }

            this._items = data.data.items || [];
            this._parentItems = data.data.parent_items || [];
            this._updateStats(data.data);
            this._renderTable(this._items, tbody);
            this.refreshAddRoleParentOptions();
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger">Connection error.</td></tr>';
        }
    },

    _updateStats(data) {
        const bySection = data.by_section || [];
        const el = (id) => document.getElementById(id);
        if (el('ciStatTotal'))        el('ciStatTotal').textContent = data.total_items  ?? '—';
        if (el('ciStatActive'))       el('ciStatActive').textContent = data.active_items ?? '—';

        let configCount = '—';
        let commissionCount = '—';
        this._sectionCounts = {};

        bySection.forEach(s => {
            const sectionItems = this._items.filter(i => i.section === s.section);
            const maxNum = sectionItems.reduce((m, i) => Math.max(m, parseInt(i.section_number) || 0), 0);
            this._sectionCounts[s.section] = maxNum;
            if (s.section === 'during_config') configCount = s.total;
            if (s.section === 'during_commissioning') commissionCount = s.total;
        });

        if (el('ciStatConfig'))        el('ciStatConfig').textContent = configCount;
        if (el('ciStatCommissioning')) el('ciStatCommissioning').textContent = commissionCount;
    },

    _roleConfigFromItem(item) {
        return {
            user: {
                visible: String(item.visible_user ?? '1') === '1',
                parent_item_id: item.user_parent_item_id ? String(item.user_parent_item_id) : ''
            },
            support: {
                visible: String(item.visible_support ?? '1') === '1',
                parent_item_id: item.support_parent_item_id ? String(item.support_parent_item_id) : ''
            },
            control: {
                visible: String(item.visible_control ?? '1') === '1',
                parent_item_id: item.control_parent_item_id ? String(item.control_parent_item_id) : ''
            }
        };
    },

    _roleSummaryHtml(item) {
        const cfg = this._roleConfigFromItem(item);
        const badges = this._roleOrder.map(role => {
            const label = role.charAt(0).toUpperCase() + role.slice(1);
            if (!cfg[role].visible) {
                return `<span class="badge ci-role-tag ci-role-tag-off">${label}: Off</span>`;
            }
            const parent = cfg[role].parent_item_id
                ? this._items.find(it => String(it.id) === String(cfg[role].parent_item_id))
                : null;
            const parentBadgeText = parent ? `#${escapeHtml(String(parent.section_number))}` : '—';
            return `<span class="badge ci-role-tag ci-role-tag-on">${label} ${parentBadgeText}</span>`;
        });
        return badges.join('');
    },

    _renderTable(items, tbody) {
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No checklist items found.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            const activeBadge = item.is_active == 1
                ? '<span class="badge badge-ci-active">Active</span>'
                : '<span class="badge badge-ci-inactive">Inactive</span>';

            const toggleLabel  = item.is_active == 1 ? '⏸ Deactivate' : '▶ Activate';
            const toggleClass  = item.is_active == 1 ? 'btn-warning' : 'btn-success';
            const isChild = !!item.parent_item_id;
            const childPrefix = isChild ? '<span class="ci-desc-prefix">↳</span>' : '';
            const descClass = isChild ? 'ci-desc-cell ci-desc-cell--child' : 'ci-desc-cell';
            const parentBadge = (item.sub_items_count > 0)
                ? '<span class="badge badge-ci-parent">Parent</span>'
                : '';
            const childBadge = isChild
                ? '<span class="badge badge-ci-child">Child</span>'
                : '';
            const usageCount = parseInt(item.usage_count) || 0;
            const usageBadge = usageCount > 0
                ? `<span class="badge badge-ci-usage" title="Used in ${usageCount} SWO(s)">Used: ${usageCount} SWO${usageCount > 1 ? 's' : ''}</span>`
                : '<span class="text-muted">—</span>';
            const swoTypeName = item.swo_type_name && item.swo_type_name !== '—' ? item.swo_type_name : null;
            const swoTypeBadge = swoTypeName
                ? `<span class="badge badge-ci-type">${escapeHtml(swoTypeName)}</span>`
                : '<span class="text-muted">—</span>';

            return `
            <tr>
                <td>${escapeHtml(item.section_label)}</td>
                <td>${escapeHtml(String(item.section_number))}</td>
                <td class="${descClass}">${childPrefix}${escapeHtml(item.description)}</td>
                <td><code class="ci-item-key">${escapeHtml(item.item_key)}</code></td>
                <td>${swoTypeBadge}</td>
                <td>
                    <div class="ci-status-stack">
                        ${activeBadge}
                        ${parentBadge}
                        ${childBadge}
                    </div>
                </td>
                <td>${escapeHtml(item.created_by_name || '—')}</td>
                <td class="ci-created-cell">${formatDateShort(item.created_at)}</td>
                <td>${usageBadge}</td>
                <td class="ci-role-cell">
                    <div class="ci-role-stack">
                        <div class="ci-role-summary">${this._roleSummaryHtml(item)}</div>
                        <button class="btn btn-secondary btn-sm ci-role-map-btn" data-id="${escapeHtml(String(item.id))}">⚙ Role Box</button>
                    </div>
                </td>
                <td class="ci-actions-cell">
                    <div class="ci-actions-row">
                        <button class="btn ${toggleClass} btn-sm ci-toggle-btn" data-id="${escapeHtml(String(item.id))}">${toggleLabel}</button>
                        <button class="btn btn-danger btn-sm ci-delete-btn" data-id="${escapeHtml(String(item.id))}" data-key="${escapeHtml(item.item_key)}">🗑 Delete</button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.ci-toggle-btn').forEach(btn => btn.addEventListener('click', () => this.toggleStatus(btn.dataset.id)));
        tbody.querySelectorAll('.ci-delete-btn').forEach(btn => btn.addEventListener('click', () => this.deleteItem(btn.dataset.id, btn.dataset.key)));
        tbody.querySelectorAll('.ci-role-map-btn').forEach(btn => btn.addEventListener('click', () => this.openRoleMappingModal(btn.dataset.id)));
    },

    _roleFieldIds(mode, role) {
        const cap = role.charAt(0).toUpperCase() + role.slice(1);
        if (mode === 'add') {
            return { visible: `ciAddRole${cap}Visible`, parent: `ciAddRole${cap}Parent` };
        }
        return { visible: `ciRoleMap${cap}Visible`, parent: `ciRoleMap${cap}Parent` };
    },

    _collectRoleConfig(mode) {
        const config = {};
        this._roleOrder.forEach(role => {
            const ids = this._roleFieldIds(mode, role);
            const visibleEl = document.getElementById(ids.visible);
            const parentEl = document.getElementById(ids.parent);
            config[role] = {
                visible: !!visibleEl?.checked,
                parent_item_id: parentEl?.value || ''
            };
        });
        return config;
    },

    _applyRoleConfig(mode, config) {
        this._roleOrder.forEach(role => {
            const ids = this._roleFieldIds(mode, role);
            const visibleEl = document.getElementById(ids.visible);
            const parentEl = document.getElementById(ids.parent);
            if (visibleEl) visibleEl.checked = !!config?.[role]?.visible;
            if (parentEl) parentEl.value = config?.[role]?.parent_item_id || '';
        });
        this._syncRoleFormState(mode);
    },

    _syncRoleFormState(mode) {
        this._roleOrder.forEach(role => {
            const ids = this._roleFieldIds(mode, role);
            const visibleEl = document.getElementById(ids.visible);
            const parentEl = document.getElementById(ids.parent);
            if (!visibleEl || !parentEl) return;
            parentEl.disabled = !visibleEl.checked;
            if (!visibleEl.checked) parentEl.value = '';
        });
    },

    _parentCandidates(section, swoTypeId, excludeItemId = null) {
        return this._parentItems.filter(p => {
            if (excludeItemId && String(p.id) === String(excludeItemId)) return false;
            const sameSection = !section || p.section === section;
            const sameType = !swoTypeId ? true : (!p.swo_type_id || String(p.swo_type_id) === String(swoTypeId));
            return sameSection && sameType;
        });
    },

    _renderRoleParentOptions(mode, section, swoTypeId, currentConfig = null, excludeItemId = null) {
        const options = this._parentCandidates(section, swoTypeId, excludeItemId)
            .map(p => `<option value="${escapeHtml(String(p.id))}">#${escapeHtml(String(p.section_number))} — ${escapeHtml(p.description)}</option>`)
            .join('');
        this._roleOrder.forEach(role => {
            const ids = this._roleFieldIds(mode, role);
            const select = document.getElementById(ids.parent);
            if (!select) return;
            const selected = currentConfig?.[role]?.parent_item_id || '';
            select.innerHTML = '<option value="">-- No Parent (Top-level) --</option>' + options;
            select.value = selected;
        });
        this._syncRoleFormState(mode);
    },

    refreshAddRoleParentOptions() {
        const section = document.getElementById('ciAddSection')?.value || '';
        const swoTypeId = document.getElementById('ciAddSwoType')?.value || '';
        const config = this._collectRoleConfig('add');
        this._renderRoleParentOptions('add', section, swoTypeId, config, null);
    },

    openAddModal() {
        const el = (id) => document.getElementById(id);
        if (el('ciAddSection')) { el('ciAddSection').value = ''; el('ciAddSection').disabled = false; }
        if (el('ciAddNumber')) el('ciAddNumber').value = '';
        if (el('ciAddDescription')) el('ciAddDescription').value = '';
        if (el('ciAddSwoType')) el('ciAddSwoType').value = '';
        this._applyRoleConfig('add', {
            user: { visible: true, parent_item_id: '' },
            support: { visible: true, parent_item_id: '' },
            control: { visible: true, parent_item_id: '' }
        });
        this.refreshAddRoleParentOptions();
        openModal('addChecklistItemModal');
    },

    _firstSelectedParentFromConfig(config) {
        for (const role of this._roleOrder) {
            if (config[role]?.visible && config[role]?.parent_item_id) {
                return config[role].parent_item_id;
            }
        }
        return '';
    },

    async suggestNumber() {
        const sectionEl = document.getElementById('ciAddSection');
        const numberEl = document.getElementById('ciAddNumber');
        const swoTypeEl = document.getElementById('ciAddSwoType');
        if (!sectionEl || !numberEl) return;

        const section = sectionEl.value;
        if (!section) {
            numberEl.value = '';
            return;
        }

        const config = this._collectRoleConfig('add');
        const selectedParentId = this._firstSelectedParentFromConfig(config);
        const params = { section };
        const swo_type_id = parseInt(swoTypeEl?.value || '') || 0;
        const parent_item_id = parseInt(selectedParentId || '') || 0;
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
        this.refreshAddRoleParentOptions();
        this.suggestNumber();
    },

    onParentItemChange() {
        this.suggestNumber();
    },

    async submitAdd() {
        const sectionEl = document.getElementById('ciAddSection');
        const numberEl = document.getElementById('ciAddNumber');
        const descEl = document.getElementById('ciAddDescription');
        const swoTypeEl = document.getElementById('ciAddSwoType');

        const section = (sectionEl?.value || '').trim();
        const sectionNum = parseInt(numberEl?.value || '') || 0;
        const description = (descEl?.value || '').trim();
        const swo_type_id = parseInt(swoTypeEl?.value || '') || 0;
        const roleConfig = this._collectRoleConfig('add');

        if (!section) return showWarning('Please select a section.');
        if (sectionNum < 1 || sectionNum > 99) return showWarning('Item number must be between 1-99.');
        if (!description) return showWarning('Please enter a description.');
        if (!this._roleOrder.some(role => roleConfig[role].visible)) {
            return showWarning('At least one role (User, Support, or Control) must be enabled for this item.');
        }

        try {
            const payload = { section, section_number: sectionNum, description, role_config: roleConfig };
            if (swo_type_id) payload.swo_type_id = swo_type_id;

            const data = await API.post('/checklist/add_item.php', payload);
            if (data && data.success) {
                showSuccess('✅ Item added: ' + description);
                closeModal('addChecklistItemModal');
                setTimeout(() => this.load(), 300);
            } else {
                showError(data?.message || 'Failed to add item');
            }
        } catch (err) {
            showError('Error: ' + err.message);
        }
    },

    openRoleMappingModal(itemId) {
        const item = this._items.find(i => String(i.id) === String(itemId));
        if (!item) return;
        this._editingRoleItemId = String(itemId);
        const section = item.section;
        const typeId = item.swo_type_id || '';
        const config = this._roleConfigFromItem(item);

        const title = document.getElementById('ciRoleMapItemTitle');
        const hiddenId = document.getElementById('ciRoleMapItemId');
        if (title) title.textContent = `${item.section_label || section} #${item.section_number} — ${item.description}`;
        if (hiddenId) hiddenId.value = String(item.id);

        this._renderRoleParentOptions('map', section, typeId, config, item.id);
        this._applyRoleConfig('map', config);
        openModal('checklistItemRoleMapModal');
    },

    async submitRoleMapping() {
        const itemId = document.getElementById('ciRoleMapItemId')?.value || this._editingRoleItemId;
        const roleConfig = this._collectRoleConfig('map');
        if (!itemId) return;
        if (!this._roleOrder.some(role => roleConfig[role].visible)) {
            return showWarning('At least one role (User, Support, or Control) must be enabled for this item.');
        }

        const data = await API.post('/checklist/update_item_role_mapping.php', {
            item_id: parseInt(itemId, 10),
            role_config: roleConfig
        });
        if (data && data.success) {
            showSuccess(data.message || 'Role mapping updated');
            closeModal('checklistItemRoleMapModal');
            this.load();
        } else {
            showError(data?.message || 'Failed to update role mapping');
        }
    },

    async toggleStatus(item_id) {
        const confirmed = await confirmDialog('Are you sure you want to toggle the status of this item?\n\nNote: Existing SWO checklists will NOT be affected.');
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
                msg = `⚠️ This item is used in ${usage.data.count} SWO(s).\n\nThis will be SOFT-DELETED:\n• Existing SWOs can still access their data\n• New SWOs won't see this item\n• The item can be reactivated later\n\nContinue?`;
            } else {
                msg = `Delete checklist item "${item_key}" permanently?\n\nThis item is not used in any SWO,\nso it will be permanently removed.`;
            }
        } catch (e) {
            msg = `Delete checklist item "${item_key}"?\n\n• If used in existing SWOs: will be soft-deleted\n• If never used: will be permanently deleted`;
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

document.addEventListener('DOMContentLoaded', function() {
    const tabBtn = document.querySelector('[data-tab="tab-checklist-items"]');
    if (tabBtn) {
        tabBtn.addEventListener('click', function() {
            if (!ChecklistItems._items.length) ChecklistItems.load();
        });
    }

    const sectionFilter = document.getElementById('ciSectionFilter');
    const statusFilter  = document.getElementById('ciStatusFilter');
    const searchFilter  = document.getElementById('ciSearchFilter');
    const swoTypeFilter = document.getElementById('ciSwoTypeFilter');
    if (sectionFilter) sectionFilter.addEventListener('change', () => ChecklistItems.load());
    if (statusFilter) statusFilter.addEventListener('change', () => ChecklistItems.load());
    if (searchFilter) searchFilter.addEventListener('input', () => ChecklistItems.load());
    if (swoTypeFilter) swoTypeFilter.addEventListener('change', () => ChecklistItems.load());

    const addSection = document.getElementById('ciAddSection');
    const addType = document.getElementById('ciAddSwoType');
    if (addSection) addSection.addEventListener('change', () => {
        ChecklistItems.refreshAddRoleParentOptions();
        ChecklistItems.suggestNumber();
    });
    if (addType) addType.addEventListener('change', () => ChecklistItems.onSwoTypeChange());

    ChecklistItems._roleOrder.map(role => role.charAt(0).toUpperCase() + role.slice(1)).forEach(cap => {
        const addVisible = document.getElementById(`ciAddRole${cap}Visible`);
        const addParent = document.getElementById(`ciAddRole${cap}Parent`);
        const mapVisible = document.getElementById(`ciRoleMap${cap}Visible`);
        const mapParent = document.getElementById(`ciRoleMap${cap}Parent`);

        if (addVisible) addVisible.addEventListener('change', () => {
            ChecklistItems._syncRoleFormState('add');
            ChecklistItems.suggestNumber();
        });
        if (addParent) addParent.addEventListener('change', () => ChecklistItems.suggestNumber());
        if (mapVisible) mapVisible.addEventListener('change', () => ChecklistItems._syncRoleFormState('map'));
        if (mapParent) mapParent.addEventListener('change', () => ChecklistItems._syncRoleFormState('map'));
    });
});
