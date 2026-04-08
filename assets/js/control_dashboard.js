/* control_dashboard.js - Control review dashboard logic */

const ControlDashboard = {
    async loadData() {
        try {
            const data = await API.get('/dashboard/control_data.php');
            if (!data || !data.success) return;

            this.renderSummary(data.data.summary || {});
            this.renderPending(data.data.pending_reviews || []);
            this.renderCompleted(data.data.completed_reviews || []);
        } catch (err) {
            console.error('Control dashboard error:', err);
        }
    },

    renderSummary(summary) {
        const pendingEl = document.getElementById('statControlPending');
        const completedEl = document.getElementById('statControlCompleted');
        if (pendingEl) pendingEl.textContent = summary.pending ?? 0;
        if (completedEl) completedEl.textContent = summary.completed ?? 0;

        const badge = document.getElementById('controlPendingBadge');
        if (badge) {
            badge.textContent = summary.pending ?? 0;
            badge.style.display = (summary.pending ?? 0) > 0 ? 'inline-block' : 'none';
        }
    },

    renderPending(swos) {
        const tbody = document.getElementById('controlPendingTableBody');
        if (!tbody) return;
        if (!swos.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No pending submissions.</td></tr>';
            return;
        }
        tbody.innerHTML = swos.map(s => `
            <tr>
                <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                <td>${escapeHtml(s.station_name)}</td>
                <td>${escapeHtml(s.assigned_to_name || '—')}</td>
                <td>${escapeHtml(s.support_reviewer_name || '—')}</td>
                <td>${formatDate(s.support_reviewed_at)}</td>
                <td>
                    <a class="btn btn-primary btn-sm"
                       href="/scada-checklist-system/views/control/review_swo.php?swo_id=${s.id}">
                        Review
                    </a>
                </td>
            </tr>
        `).join('');
    },

    renderCompleted(swos) {
        const tbody = document.getElementById('controlCompletedTableBody');
        if (!tbody) return;
        if (!swos.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No completed reviews yet.</td></tr>';
            return;
        }
        tbody.innerHTML = swos.map(s => `
            <tr>
                <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                <td>${escapeHtml(s.station_name)}</td>
                <td>${escapeHtml(s.assigned_to_name || '—')}</td>
                <td>${formatDate(s.control_reviewed_at)}</td>
            </tr>
        `).join('');
    },

    submitApprove() {
        ControlReview.submitApprove();
    },

    submitReturn() {
        ControlReview.submitReturn();
    },
};

const ControlReview = {
    currentSwoId: null,
    saveTimers: {},

    async openModal(swoId, swoNumber, stationName) {
        this.currentSwoId = swoId;

        document.getElementById('controlModalTitle').textContent =
            `Control Review: ${swoNumber}`;
        document.getElementById('controlModalSwoInfo').textContent =
            `Station: ${stationName} | Status: Pending Control Review`;
        document.getElementById('controlOverallComments').value = '';
        document.getElementById('controlReviewTableBody').innerHTML =
            '<tr><td colspan="7" class="text-center">Loading...</td></tr>';

        openModal('controlReviewModal');
        await this.loadReviewData(swoId);
    },

    async loadReviewData(swoId) {
        try {
            const [checklistData, supportReviews, controlReviews] = await Promise.all([
                API.get('/checklist/get_checklist.php', {swo_id: swoId}),
                API.get('/swo/get_support_item_reviews.php', {swo_id: swoId}),
                API.get('/swo/get_control_item_reviews.php', {swo_id: swoId}),
            ]);

            if (!checklistData || !checklistData.success) {
                document.getElementById('controlReviewTableBody').innerHTML =
                    '<tr><td colspan="7" class="text-center text-danger">Failed to load checklist.</td></tr>';
                return;
            }

            const existingSupportReviews = {};
            if (supportReviews && supportReviews.success) {
                (supportReviews.data || []).forEach(r => {
                    existingSupportReviews[r.item_key] = r;
                });
            }

            const existingControlReviews = {};
            if (controlReviews && controlReviews.success) {
                (controlReviews.data || []).forEach(r => {
                    existingControlReviews[r.item_key] = r;
                });
            }

            this.renderReviewTable(
                checklistData.data.sections || [],
                existingSupportReviews,
                existingControlReviews
            );
        } catch (err) {
            console.error('Error loading control review data:', err);
            document.getElementById('controlReviewTableBody').innerHTML =
                '<tr><td colspan="7" class="text-center text-danger">Error loading data.</td></tr>';
        }
    },

    renderReviewTable(sections, supportReviews, controlReviews) {
        const tbody = document.getElementById('controlReviewTableBody');
        let html = '';
        let rowNum = 0;

        sections.forEach(section => {
            html += `<tr><td colspan="7" style="background:#f5f6fa;font-weight:700;padding:8px 12px;">
                ${escapeHtml(section.label)}</td></tr>`;
            section.items.forEach(item => {
                rowNum++;
                const sr = supportReviews[item.key] || {};
                const cr = controlReviews[item.key]  || {};
                const controlDecision = cr.control_decision || '';
                const controlComment  = cr.control_comment  || '';

                html += `
                <tr data-item-key="${escapeHtml(item.key)}">
                    <td style="text-align:center">${rowNum}</td>
                    <td>${escapeHtml(item.label)}</td>
                    <td>${getChecklistStatusBadge(item.status)}</td>
                    <td>${sr.support_decision ? getChecklistStatusBadge(sr.support_decision) : '<span class="text-muted">—</span>'}</td>
                    <td style="font-size:12px;max-width:150px">${escapeHtml(sr.support_comment || '—')}</td>
                    <td>
                        <select class="form-control form-control-sm control-decision-select"
                                data-key="${escapeHtml(item.key)}"
                                onchange="ControlReview.scheduleItemSave('${escapeHtml(item.key)}')">
                            <option value="">—</option>
                            <option value="done"    ${controlDecision==='done'    ?'selected':''}>Done</option>
                            <option value="na"      ${controlDecision==='na'      ?'selected':''}>N/A</option>
                            <option value="still"   ${controlDecision==='still'   ?'selected':''}>Still</option>
                            <option value="not_yet" ${controlDecision==='not_yet' ?'selected':''}>Not Yet</option>
                        </select>
                    </td>
                    <td>
                        <textarea class="form-control form-control-sm control-comment-input"
                                  data-key="${escapeHtml(item.key)}"
                                  rows="2"
                                  placeholder="Comment..."
                                  onblur="ControlReview.scheduleItemSave('${escapeHtml(item.key)}')"
                        >${escapeHtml(controlComment)}</textarea>
                    </td>
                </tr>`;
            });
        });

        tbody.innerHTML = html || '<tr><td colspan="7" class="text-center text-muted">No items found.</td></tr>';
    },

    scheduleItemSave(itemKey) {
        if (this.saveTimers[itemKey]) {
            clearTimeout(this.saveTimers[itemKey]);
        }
        this.saveTimers[itemKey] = setTimeout(() => this.saveItemReview(itemKey), 600);
    },

    async saveItemReview(itemKey) {
        const row = document.querySelector(`tr[data-item-key="${itemKey.replace(/"/g, '\\"')}"]`);
        if (!row) return;

        const decision = row.querySelector('.control-decision-select')?.value ?? '';
        const comment  = row.querySelector('.control-comment-input')?.value  ?? '';

        try {
            await API.post('/swo/control_item_review.php', {
                swo_id:           this.currentSwoId,
                item_key:         itemKey,
                control_decision: decision,
                control_comment:  comment,
            });
        } catch (err) {
            console.error('Failed to save item review:', err);
        }
    },

    async submitApprove() {
        const comments = document.getElementById('controlOverallComments').value.trim();
        const confirmed = await confirmDialog('Approve this submission? It will be marked as Completed.');
        if (!confirmed) return;

        const btn = document.getElementById('controlApproveBtn');
        btn.disabled = true;

        try {
            const data = await API.post('/swo/control_approve.php', {
                swo_id:   this.currentSwoId,
                comments: comments,
            });
            if (data && data.success) {
                showSuccess('Submission approved and marked as Completed!');
                closeModal('controlReviewModal');
                ControlDashboard.loadData();
            } else {
                showError(data?.message || 'Failed to approve submission');
            }
        } finally {
            btn.disabled = false;
        }
    },

    async submitReturn() {
        const comments = document.getElementById('controlOverallComments').value.trim();
        if (!comments) {
            showWarning('Please provide a reason before returning to Support.');
            return;
        }
        const confirmed = await confirmDialog('Return this submission to Support for re-review?');
        if (!confirmed) return;

        const btn = document.getElementById('controlReturnBtn');
        btn.disabled = true;

        try {
            const data = await API.post('/swo/control_return.php', {
                swo_id:   this.currentSwoId,
                comments: comments,
            });
            if (data && data.success) {
                showSuccess('Submission returned to Support for re-review.');
                closeModal('controlReviewModal');
                ControlDashboard.loadData();
            } else {
                showError(data?.message || 'Failed to return submission');
            }
        } finally {
            btn.disabled = false;
        }
    },
};
