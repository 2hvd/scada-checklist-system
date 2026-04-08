/* checklist.js - Checklist page logic */

const ChecklistPage = {
    swoId: null,
    readOnly: false,

    async init(swoId, readOnly = false) {
        this.swoId = swoId;
        this.readOnly = readOnly;
        await this.loadChecklist();
        await this.loadComments();
        this.bindCommentForm();
    },

    async loadChecklist() {
        const container = document.getElementById('checklistContainer');
        if (!container) return;
        container.innerHTML = '<div class="loading-overlay"><div class="loading-spinner"></div></div>';

        try {
            const data = await API.get('/checklist/get_checklist.php', {swo_id: this.swoId});
            if (!data || !data.success) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load checklist.</div>';
                return;
            }

            this.renderChecklist(data.data, container);
            this.updateProgress(data.data.progress, data.data.counts);
        } catch (err) {
            container.innerHTML = '<div class="alert alert-danger">Error loading checklist.</div>';
        }
    },

    renderChecklist(data, container) {
        const sections = data.sections || [];
        let html = '';

        sections.forEach(section => {
            html += `
                <div class="checklist-section">
                    <div class="checklist-section-header">${escapeHtml(section.label)}</div>
            `;
            section.items.forEach((item, idx) => {
                const num = idx + 1;
                const disabled = this.readOnly ? 'disabled' : '';
                html += `
                    <div class="checklist-item" data-key="${escapeHtml(item.key)}">
                        <div class="item-number">${num}</div>
                        <div class="item-label">${escapeHtml(item.label)}</div>
                        ${this.readOnly
                            ? `<div>${getChecklistStatusBadge(item.status)}</div>`
                            : `<select class="item-status-select status-${item.status}" 
                                data-key="${escapeHtml(item.key)}" 
                                onchange="ChecklistPage.updateStatus('${escapeHtml(item.key)}', this.value, this)"
                                ${disabled}>
                                    <option value="empty"   ${item.status==='empty'   ?'selected':''}>— Select —</option>
                                    <option value="done"    ${item.status==='done'    ?'selected':''}>Done</option>
                                    <option value="na"      ${item.status==='na'      ?'selected':''}>N/A</option>
                                    <option value="not_yet" ${item.status==='not_yet' ?'selected':''}>Not Yet</option>
                                    <option value="still"   ${item.status==='still'   ?'selected':''}>Still</option>
                               </select>`
                        }
                    </div>
                `;
            });
            html += '</div>';
        });
        container.innerHTML = html;
    },

    async updateStatus(itemKey, newStatus, selectEl) {
        if (this.readOnly) return;
        const oldClass = selectEl.className;
        selectEl.disabled = true;

        try {
            const data = await API.post('/checklist/update_status.php', {
                swo_id: this.swoId,
                item_key: itemKey,
                status: newStatus
            });

            if (data && data.success) {
                selectEl.className = `item-status-select status-${newStatus}`;
                await this.refreshProgress();
            } else {
                showError(data?.message || 'Failed to update status');
                selectEl.className = oldClass;
            }
        } catch (err) {
            showError('Connection error');
            selectEl.className = oldClass;
        } finally {
            selectEl.disabled = false;
        }
    },

    async refreshProgress() {
        const data = await API.get('/checklist/get_checklist.php', {swo_id: this.swoId});
        if (data && data.success) {
            this.updateProgress(data.data.progress, data.data.counts);
        }
    },

    updateProgress(progress, counts) {
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressPct = document.getElementById('progressPct');

        if (progressBar) {
            progressBar.style.width = progress + '%';
            progressBar.style.background = getProgressColor(progress);
        }
        if (progressText) {
            progressText.textContent = `${progress}% (${counts.done} Done, ${counts.na} N/A, ${counts.still} Still, ${counts.not_yet} Not Yet, ${counts.empty} Empty)`;
        }
        if (progressPct) progressPct.textContent = progress + '%';

        // Update submit button state
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = counts.empty > 0;
            submitBtn.title = counts.empty > 0 ? `${counts.empty} items still empty` : 'Submit checklist for review';
        }
    },

    async submitChecklist() {
        const confirmed = await confirmDialog('Submit this checklist for review? You can withdraw it later if needed.');
        if (!confirmed) return;

        const data = await API.post('/checklist/submit_checklist.php', {swo_id: this.swoId});
        if (data && data.success) {
            showSuccess('Checklist submitted for review!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data?.message || 'Failed to submit');
        }
    },

    async withdrawChecklist() {
        const confirmed = await confirmDialog('Withdraw this submission? You can edit and resubmit.');
        if (!confirmed) return;

        const data = await API.post('/checklist/withdraw_checklist.php', {swo_id: this.swoId});
        if (data && data.success) {
            showSuccess('Checklist withdrawn. You can now edit it.');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data?.message || 'Failed to withdraw');
        }
    },

    async loadComments() {
        const container = document.getElementById('commentsContainer');
        if (!container) return;

        try {
            const data = await API.get('/comments/get_comments.php', {swo_id: this.swoId});
            if (!data || !data.success) return;

            const comments = data.data || [];
            if (!comments.length) {
                container.innerHTML = '<div class="text-muted" style="font-size:13px;padding:12px 0;">No comments yet.</div>';
                return;
            }

            container.innerHTML = comments.map(c => `
                <div class="comment-item">
                    <div class="comment-meta">
                        <span class="comment-author">${escapeHtml(c.username)}</span>
                        <span class="comment-time">${formatDate(c.created_at)}</span>
                    </div>
                    ${c.item_key ? `<div class="comment-item-key">Re: ${escapeHtml(c.item_key)}</div>` : ''}
                    <div class="comment-text">${escapeHtml(c.comment_text)}</div>
                </div>
            `).join('');
        } catch (err) {
            console.error('Failed to load comments:', err);
        }
    },

    bindCommentForm() {
        const form = document.getElementById('commentForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const textarea = form.querySelector('textarea');
            const text = textarea.value.trim();
            if (!text) return;

            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                const data = await API.post('/comments/add_comment.php', {
                    swo_id: this.swoId,
                    comment_text: text
                });
                if (data && data.success) {
                    textarea.value = '';
                    await this.loadComments();
                } else {
                    showError(data?.message || 'Failed to add comment');
                }
            } catch {
                showError('Connection error');
            } finally {
                btn.disabled = false;
            }
        });
    },

    exportCSV() {
        window.location.href = `/scada-checklist-system/api/export/csv_export.php?swo_id=${this.swoId}`;
    }
};
