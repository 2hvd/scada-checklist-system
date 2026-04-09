/* review_manager.js - Unified review handler for User, Support, and Control roles */

const ReviewManager = {
    swoId: null,
    role: null,      // 'user', 'support', 'control'
    saveTimers: {},

    async init(swoId, role) {
        this.swoId = swoId;
        this.role  = role;
        await this.load();
        this.attachEventListeners();
    },

    async load() {
        const endpointMap = {
            user:    '/swo/get_user_review.php',
            support: '/swo/get_support_review.php',
            control: '/swo/get_control_review.php',
        };
        const endpoint = endpointMap[this.role];
        if (!endpoint) return;

        try {
            const data = await API.get(endpoint, { swo_id: this.swoId });
            if (!data || !data.success) {
                document.getElementById('reviewContent').innerHTML =
                    '<div class="alert alert-danger">Failed to load review data.</div>';
                return;
            }
            this.renderPage(data.data);
        } catch (err) {
            console.error('ReviewManager.load error:', err);
            document.getElementById('reviewContent').innerHTML =
                '<div class="alert alert-danger">Error loading review data.</div>';
        }
    },

    renderPage(data) {
        const swo      = data.swo;
        const sections = data.sections || [];
        const counts   = data.counts   || {};
        const progress = data.progress || 0;

        // Header title
        const titleEl = document.getElementById('reviewTitle');
        if (titleEl) titleEl.textContent = 'Review: ' + swo.swo_number;

        const stationEl = document.getElementById('reviewStation');
        if (stationEl) stationEl.textContent = swo.station_name;

        const statusWrap = document.getElementById('reviewStatusWrap');
        if (statusWrap) statusWrap.innerHTML = getStatusBadge(swo.status);

        // Progress
        const progressColor = progress >= 80 ? '#27ae60' : (progress >= 50 ? '#f39c12' : '#e74c3c');
        const barEl = document.getElementById('reviewProgressBar');
        if (barEl) { barEl.style.width = progress + '%'; barEl.style.background = progressColor; }
        const pctEl = document.getElementById('reviewProgressPct');
        if (pctEl) pctEl.textContent = progress + '%';
        const textEl = document.getElementById('reviewProgressText');
        if (textEl) textEl.textContent =
            `${counts.done || 0} Done, ${counts.na || 0} N/A, ${counts.still || 0} Still, ` +
            `${counts.not_yet || 0} Not Yet, ${counts.empty || 0} Empty`;

        // Support overall comments panel (control only)
        const supportOverallEl = document.getElementById('supportOverallComments');
        if (supportOverallEl) {
            supportOverallEl.textContent = data.support_overall_comments || '—';
        }

        // Sections
        const container = document.getElementById('reviewSections');
        if (!container) return;

        container.innerHTML = sections.map(section => `
            <div class="review-section">
                <div class="review-section-header">${escapeHtml(section.label)}</div>
                <table class="review-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="col-status">User Status</th>
                            ${this.role === 'control' ? '<th class="col-status">Support Decision</th><th class="support-col-readonly">Support Comment</th>' : ''}
                            ${this.role !== 'user' ? '<th class="col-decision">Decision</th>' : ''}
                            <th class="col-comment">Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${section.items.map((item, idx) => this.renderRow(item, idx + 1)).join('')}
                    </tbody>
                </table>
            </div>
        `).join('');
    },

    renderRow(item, num) {
        const decisionHtml = this.role !== 'user' ? `
            <td class="col-decision">
                <select class="review-decision-select"
                        data-item-key="${escapeHtml(item.key)}">
                    <option value="">—</option>
                    <option value="done"    ${item.decision === 'done'    ? 'selected' : ''}>Done</option>
                    <option value="na"      ${item.decision === 'na'      ? 'selected' : ''}>N/A</option>
                    <option value="still"   ${item.decision === 'still'   ? 'selected' : ''}>Still</option>
                    <option value="not_yet" ${item.decision === 'not_yet' ? 'selected' : ''}>Not Yet</option>
                </select>
            </td>` : '';

        const supportColsHtml = this.role === 'control' ? `
            <td class="col-status">${item.support_decision ? getChecklistStatusBadge(item.support_decision) : '<span class="text-muted">—</span>'}</td>
            <td class="support-col-readonly">
                <span class="support-comment-readonly">${escapeHtml(item.support_comment || '—')}</span>
            </td>` : '';

        return `
            <tr data-item-key="${escapeHtml(item.key)}">
                <td>${num}</td>
                <td>${escapeHtml(item.label)}</td>
                <td class="col-status">${getChecklistStatusBadge(item.status)}</td>
                ${supportColsHtml}
                ${decisionHtml}
                <td class="col-comment">
                    <textarea class="review-comment-textarea"
                              data-item-key="${escapeHtml(item.key)}"
                              rows="2"
                              placeholder="Add comment...">${escapeHtml(item.comment || '')}</textarea>
                </td>
            </tr>`;
    },

    attachEventListeners() {
        const content = document.getElementById('reviewContent');
        if (!content) return;

        // Decision dropdowns – auto-save on change (support / control only)
        if (this.role !== 'user') {
            content.addEventListener('change', (e) => {
                if (e.target.classList.contains('review-decision-select')) {
                    const itemKey = e.target.dataset.itemKey;
                    this.scheduleItemSave(itemKey);
                }
            });
        }

        // Comment textareas – auto-save on blur (all roles)
        content.addEventListener('blur', (e) => {
            if (e.target.classList.contains('review-comment-textarea')) {
                const itemKey = e.target.dataset.itemKey;
                this.scheduleItemSave(itemKey);
            }
        }, true);
    },

    scheduleItemSave(itemKey) {
        if (this.saveTimers[itemKey]) {
            clearTimeout(this.saveTimers[itemKey]);
        }
        this.showSaving();
        this.saveTimers[itemKey] = setTimeout(() => this.saveItemReview(itemKey), 400);
    },

    async saveItemReview(itemKey) {
        const row = document.querySelector(`tr[data-item-key="${CSS.escape(itemKey)}"]`);
        if (!row) return;

        const comment  = row.querySelector('.review-comment-textarea')?.value ?? '';
        const decision = row.querySelector('.review-decision-select')?.value  ?? '';

        const endpointMap = {
            user:    '/swo/user_item_comment.php',
            support: '/swo/support_item_review.php',
            control: '/swo/control_item_review.php',
        };
        const endpoint = endpointMap[this.role];

        const payload = { swo_id: this.swoId, item_key: itemKey, comment };
        if (this.role === 'support') {
            payload.support_decision = decision;
            payload.support_comment  = comment;
            delete payload.comment;
        } else if (this.role === 'control') {
            payload.control_decision = decision;
            payload.control_comment  = comment;
            delete payload.comment;
        }

        try {
            const data = await API.post(endpoint, payload);
            if (data && data.success) {
                this.showSaved();
            } else {
                this.showError('Save failed');
            }
        } catch (err) {
            console.error('ReviewManager.saveItemReview error:', err);
            this.showError('Save failed');
        }
    },

    showSaving() {
        const el = document.getElementById('saveIndicator');
        if (!el) return;
        el.className = 'save-indicator saving';
        el.textContent = '⏳ Saving…';
    },

    showSaved() {
        const el = document.getElementById('saveIndicator');
        if (!el) return;
        el.className = 'save-indicator saved';
        el.textContent = '✓ Saved';
        clearTimeout(this._savedTimer);
        this._savedTimer = setTimeout(() => {
            el.className = 'save-indicator hidden';
        }, 2500);
    },

    showError(msg) {
        const el = document.getElementById('saveIndicator');
        if (!el) return;
        el.className = 'save-indicator error';
        el.textContent = '⚠ ' + msg;
    },

    // ── Role-specific actions ────────────────────────────────────────────────

    async accept() {
        if (this.role !== 'support') return;
        const confirmed = await confirmDialog('Accept this submission and send to Control for final approval?');
        if (!confirmed) return;

        const btn = document.getElementById('acceptBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/support_accept.php', { swo_id: this.swoId, comments: '' });
            if (data && data.success) {
                showSuccess('Submission accepted and forwarded to Control.');
                setTimeout(() => window.location.href = '/scada-checklist-system/views/support/index.php', 1200);
            } else {
                showError(data?.message || 'Failed to accept submission');
                if (btn) btn.disabled = false;
            }
        } catch (err) {
            showError('Connection error');
            if (btn) btn.disabled = false;
        }
    },

    async reject() {
        if (this.role !== 'support') return;
        const confirmed = await confirmDialog('Reject this submission and return it to the user for editing?');
        if (!confirmed) return;

        const btn = document.getElementById('rejectBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/support_reject.php', { swo_id: this.swoId, comments: '' });
            if (data && data.success) {
                showSuccess('Submission rejected and returned to user.');
                setTimeout(() => window.location.href = '/scada-checklist-system/views/support/index.php', 1200);
            } else {
                showError(data?.message || 'Failed to reject submission');
                if (btn) btn.disabled = false;
            }
        } catch (err) {
            showError('Connection error');
            if (btn) btn.disabled = false;
        }
    },

    async approve() {
        if (this.role !== 'control') return;
        const confirmed = await confirmDialog('Approve this submission? It will be marked as Completed.');
        if (!confirmed) return;

        const btn = document.getElementById('approveBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/control_approve.php', { swo_id: this.swoId, comments: '' });
            if (data && data.success) {
                showSuccess('Submission approved and marked as Completed!');
                setTimeout(() => window.location.href = '/scada-checklist-system/views/control/index.php', 1200);
            } else {
                showError(data?.message || 'Failed to approve submission');
                if (btn) btn.disabled = false;
            }
        } catch (err) {
            showError('Connection error');
            if (btn) btn.disabled = false;
        }
    },

    async returnToSupport() {
        if (this.role !== 'control') return;
        const confirmed = await confirmDialog('Return this submission to Support for re-review?');
        if (!confirmed) return;

        const btn = document.getElementById('returnBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/control_return.php', { swo_id: this.swoId, comments: '' });
            if (data && data.success) {
                showSuccess('Submission returned to Support for re-review.');
                setTimeout(() => window.location.href = '/scada-checklist-system/views/control/index.php', 1200);
            } else {
                showError(data?.message || 'Failed to return submission');
                if (btn) btn.disabled = false;
            }
        } catch (err) {
            showError('Connection error');
            if (btn) btn.disabled = false;
        }
    },
};
