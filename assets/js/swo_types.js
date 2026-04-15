/* swo_types.js - Admin SWO Types Management */

const SWOTypes = {
    _types: [],

    async load() {
        const tbody = document.getElementById('swoTypesTableBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

        try {
            const data = await API.get('/swo/get_swo_types.php', { active_only: '0' });
            if (!data || !data.success) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load types.</td></tr>';
                return;
            }

            this._types = data.data || [];
            this.renderTable(this._types);
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading types.</td></tr>';
        }
    },

    renderTable(types) {
        const tbody = document.getElementById('swoTypesTableBody');
        if (!types.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No SWO types found.</td></tr>';
            return;
        }

        tbody.innerHTML = types.map(type => `
            <tr>
                <td><strong>${escapeHtml(type.name)}</strong></td>
                <td>${escapeHtml(type.description || '—')}</td>
                <td>
                    <span class="badge" style="background:${type.is_active == 1 ? '#27ae60' : '#e74c3c'};color:#fff">
                        ${type.is_active == 1 ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${type.created_at ? formatDateShort(type.created_at) : '—'}</td>
                <td>
                    <div style="display:flex;gap:5px;">
                        <button class="btn btn-secondary btn-sm swo-type-edit-btn"
                            data-id="${type.id}"
                            data-name="${escapeHtml(type.name)}"
                            data-desc="${escapeHtml(type.description || '')}">✏️ Edit</button>
                        <button class="btn btn-danger btn-sm swo-type-delete-btn"
                            data-id="${type.id}">🗑 Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');

        // Attach event listeners via delegation
        tbody.querySelectorAll('.swo-type-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                SWOTypes.openEditModal(btn.dataset.id, btn.dataset.name, btn.dataset.desc);
            });
        });
        tbody.querySelectorAll('.swo-type-delete-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                SWOTypes.delete(btn.dataset.id);
            });
        });
    },

    openAddModal() {
        document.getElementById('swoTypeAddName').value = '';
        document.getElementById('swoTypeAddDescription').value = '';
        openModal('addSwoTypeModal');
    },

    openEditModal(id, name, description) {
        document.getElementById('swoTypeEditId').value = id;
        document.getElementById('swoTypeEditName').value = name;
        document.getElementById('swoTypeEditDescription').value = description;
        openModal('editSwoTypeModal');
    },

    async submitAdd() {
        const name = document.getElementById('swoTypeAddName').value.trim();
        const description = document.getElementById('swoTypeAddDescription').value.trim();

        if (!name) {
            showWarning('Please enter a name');
            return;
        }

        try {
            const data = await API.post('/swo/add_swo_type.php', { name, description });
            if (data && data.success) {
                showSuccess(data.message || 'SWO Type added successfully');
                closeModal('addSwoTypeModal');
                this.load();
                SWOTypes.refreshTypeDropdowns();
            } else {
                showError(data?.message || 'Failed to add type');
            }
        } catch (err) {
            showError('Error: ' + err.message);
        }
    },

    async submitEdit() {
        const swo_type_id = document.getElementById('swoTypeEditId').value;
        const name = document.getElementById('swoTypeEditName').value.trim();
        const description = document.getElementById('swoTypeEditDescription').value.trim();

        if (!name) {
            showWarning('Please enter a name');
            return;
        }

        try {
            const data = await API.post('/swo/update_swo_type.php', { swo_type_id, name, description });
            if (data && data.success) {
                showSuccess(data.message || 'SWO Type updated successfully');
                closeModal('editSwoTypeModal');
                this.load();
                SWOTypes.refreshTypeDropdowns();
            } else {
                showError(data?.message || 'Failed to update type');
            }
        } catch (err) {
            showError('Error: ' + err.message);
        }
    },

    async delete(swo_type_id) {
        const confirmed = await confirmDialog('Delete this SWO Type?\n\nIf it is in use, it will be deactivated instead.');
        if (!confirmed) return;

        try {
            const data = await API.post('/swo/delete_swo_type.php', { swo_type_id });
            if (data && data.success) {
                showSuccess(data.message || 'SWO Type deleted');
                this.load();
                SWOTypes.refreshTypeDropdowns();
            } else {
                showError(data?.message || 'Failed to delete type');
            }
        } catch (err) {
            showError('Error: ' + err.message);
        }
    },

    async refreshTypeDropdowns() {
        try {
            const data = await API.get('/swo/get_swo_types.php', { active_only: '1' });
            if (data && data.success) {
                const types = data.data || [];
                const options = '<option value="">All Types</option>' +
                    types.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');

                const ciFilter = document.getElementById('ciSwoTypeFilter');
                if (ciFilter) ciFilter.innerHTML = options;

                const addSelect = document.getElementById('ciAddSwoType');
                if (addSelect) {
                    addSelect.innerHTML = '<option value="">-- Select SWO Type --</option>' +
                        types.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
                }
            }
        } catch (err) {
            // Silently fail - dropdowns will keep current state
        }
    }
};

// Load when tab is clicked
document.addEventListener('DOMContentLoaded', function() {
    const tabBtn = document.querySelector('[data-tab="tab-swo-types"]');
    if (tabBtn) {
        tabBtn.addEventListener('click', function() {
            SWOTypes.load();
        });
    }

    // Load SWO Types into filter dropdowns on page load
    SWOTypes.refreshTypeDropdowns();
});
