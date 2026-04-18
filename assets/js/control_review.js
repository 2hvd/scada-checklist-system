/* control_review.js - Full-page control review with auto-save */

const ControlReviewPage = {
    swoId: null,
    saveTimers: {},

    async init(swoId) {
        this.swoId = swoId;
        await this.load();
        this.attachEventListeners();
    },

    async load() {
        try {
            const data = await API.get('/swo/get_control_review.php', { swo_id: this.swoId });
            if (!data || !data.success) {
                document.getElementById('reviewContent').innerHTML =
                    '<div class="alert alert-danger">Failed to load review data.</div>';
                return;
            }
            this.renderPage(data.data);
        } catch (err) {
            console.error('ControlReviewPage.load error:', err);
            document.getElementById('reviewContent').innerHTML =
                '<div class="alert alert-danger">Error loading review data.</div>';
        }
    },

    renderPage(data) {
        const swo      = data.swo;
        const sections = data.sections || [];
        const counts   = data.counts   || {};
        const progress = data.progress || 0;

        // Header
        document.getElementById('reviewTitle').textContent = 'Control Review: ' + swo.swo_number;
        document.getElementById('reviewStation').textContent = swo.station_name;

        // Progress
        const progressColor = progress >= 80 ? '#27ae60' : (progress >= 50 ? '#f39c12' : '#e74c3c');
        document.getElementById('reviewProgressBar').style.width      = progress + '%';
        document.getElementById('reviewProgressBar').style.background = progressColor;
        document.getElementById('reviewProgressPct').textContent      = progress + '%';
        document.getElementById('reviewProgressText').textContent     =
            `${counts.done || 0} Done, ${counts.na || 0} N/A, ${counts.still || 0} Still, ` +
            `${counts.not_yet || 0} Not Yet, ${counts.empty || 0} Empty`;

        // Support overall comments panel (read-only)
        const supportOverallEl = document.getElementById('supportOverallComments');
        if (supportOverallEl) {
            supportOverallEl.textContent = data.support_overall_comments || '—';
        }

        // Sections
        const container = document.getElementById('reviewSections');
        container.innerHTML = sections.map(section => `
            <div class="review-section">
                <div class="review-section-header">${escapeHtml(section.label)}</div>
                <table class="review-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="col-status">User Status</th>
                            <th class="user-col-readonly">User Comment</th>
                            <th class="col-status">Support Decision</th>
                            <th class="col-comment support-col-readonly">Support Comment</th>
                            <th class="col-decision">Control Decision</th>
                            <th class="col-comment">Control Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${section.items.map((item, idx) => `
                        <tr data-item-key="${escapeHtml(item.key)}">
                            <td>${idx + 1}</td>
                            <td>${escapeHtml(item.label)}</td>
                            <td class="col-status">${getChecklistStatusBadge(item.status)}</td>
                            <td class="user-col-readonly">
                                <span class="user-comment-readonly">${escapeHtml(item.user_comment || '—')}</span>
                            </td>
                            <td class="col-status">${item.support_decision ? getChecklistStatusBadge(item.support_decision) : '<span class="text-muted">—</span>'}</td>
                            <td class="support-col-readonly">
                                <span class="support-comment-readonly">${escapeHtml(item.support_comment || '—')}</span>
                            </td>
                            <td class="col-decision">
                                <select class="review-decision-select control-decision-select"
                                        data-item-key="${escapeHtml(item.key)}">
                                    <option value="">—</option>
                                    <option value="done"    ${item.decision === 'done'    ? 'selected' : ''}>Done</option>
                                    <option value="na"      ${item.decision === 'na'      ? 'selected' : ''}>N/A</option>
                                    <option value="still"   ${item.decision === 'still'   ? 'selected' : ''}>Still</option>
                                    <option value="not_yet" ${item.decision === 'not_yet' ? 'selected' : ''}>Not Yet</option>
                                </select>
                            </td>
                            <td class="col-comment">
                                <textarea class="review-comment-textarea control-comment-textarea"
                                          data-item-key="${escapeHtml(item.key)}"
                                          rows="2"
                                          placeholder="Add comment...">${escapeHtml(item.comment)}</textarea>
                            </td>
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `).join('');

        // Overall comments
        const overallTextarea = document.getElementById('overallCommentsTextarea');
        if (overallTextarea) {
            overallTextarea.value = data.overall_comments || '';
        }
    },

    attachEventListeners() {
        const content = document.getElementById('reviewContent');
        if (!content) return;

        // Decision dropdowns – auto-save on change
        content.addEventListener('change', (e) => {
            if (e.target.classList.contains('control-decision-select')) {
                const itemKey = e.target.dataset.itemKey;
                this.scheduleItemSave(itemKey);
            }
        });

        // Comment textareas – auto-save on blur
        content.addEventListener('blur', (e) => {
            if (e.target.classList.contains('control-comment-textarea')) {
                const itemKey = e.target.dataset.itemKey;
                this.scheduleItemSave(itemKey);
            }
        }, true);

        // Overall comments – auto-save on blur
        const overallTextarea = document.getElementById('overallCommentsTextarea');
        if (overallTextarea) {
            overallTextarea.addEventListener('blur', () => this.saveOverallComments());
        }
    },

    scheduleItemSave(itemKey) {
        if (this.saveTimers[itemKey]) {
            clearTimeout(this.saveTimers[itemKey]);
        }
        this.showSaving();
        this.saveTimers[itemKey] = setTimeout(() => this.saveItemReview(itemKey), 150);
    },

    async saveItemReview(itemKey) {
        const row = document.querySelector(`tr[data-item-key="${CSS.escape(itemKey)}"]`);
        if (!row) return;

        const decision = row.querySelector('.control-decision-select')?.value ?? '';
        const comment  = row.querySelector('.control-comment-textarea')?.value ?? '';

        try {
            const data = await API.post('/swo/control_item_review.php', {
                swo_id:           this.swoId,
                item_key:         itemKey,
                control_decision: decision,
                control_comment:  comment,
            });
            if (data && data.success) {
                this.showSaved();
            } else {
                this.showError('Save failed');
            }
        } catch (err) {
            console.error('Failed to save item review:', err);
            this.showError('Save failed');
        }
    },

    async saveOverallComments() {
        const comments = document.getElementById('overallCommentsTextarea')?.value ?? '';
        this.showSaving();
        try {
            const data = await API.post('/swo/control_overall_review.php', {
                swo_id:           this.swoId,
                overall_comments: comments,
            });
            if (data && data.success) {
                this.showSaved();
            } else {
                this.showError('Save failed');
            }
        } catch (err) {
            console.error('Failed to save overall comments:', err);
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

    async approveAndComplete() {
        const comments  = document.getElementById('overallCommentsTextarea')?.value ?? '';
        const confirmed = await confirmDialog('Approve this submission? It will be marked as Completed.');
        if (!confirmed) return;

        const btn = document.getElementById('approveBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/control_approve.php', {
                swo_id:   this.swoId,
                comments: comments,
            });
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
        const comments = document.getElementById('overallCommentsTextarea')?.value?.trim() ?? '';
        if (!comments) {
            showWarning('Please add overall comments with the return reason before returning to Support.');
            document.getElementById('overallCommentsTextarea')?.focus();
            return;
        }
        const confirmed = await confirmDialog('Return this submission to Support for re-review?');
        if (!confirmed) return;

        const btn = document.getElementById('returnBtn');
        if (btn) btn.disabled = true;

        try {
            const data = await API.post('/swo/control_return.php', {
                swo_id:   this.swoId,
                comments: comments,
            });
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
