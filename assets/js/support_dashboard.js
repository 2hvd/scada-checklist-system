/* support_dashboard.js - Support review dashboard logic */

const SupportReview = {
    currentSwoId: null,
    currentSwoStatus: null,
    saveTimers: {},

    async openModal(swoId, swoNumber, stationName, status) {
        this.currentSwoId = swoId;
        this.currentSwoStatus = status;

        document.getElementById('supportModalTitle').textContent =
            `Review: ${swoNumber}`;
        document.getElementById('supportModalSwoInfo').textContent =
            `Station: ${stationName} | Status: ${status}`;
        document.getElementById('supportOverallComments').value = '';
        document.getElementById('supportReviewTableBody').innerHTML =
            '<tr><td colspan="5" class="text-center">Loading...</td></tr>';

        openModal('supportReviewModal');
        await this.loadReviewData(swoId);
    },

    async loadReviewData(swoId) {
        try {
            // Load checklist items (user statuses)
            const checklistData = await API.get('/checklist/get_checklist.php', {swo_id: swoId});
            // Load existing support item reviews
            const reviewData = await API.get('/swo/get_support_item_reviews.php', {swo_id: swoId});

            if (!checklistData || !checklistData.success) {
                document.getElementById('supportReviewTableBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger">Failed to load checklist.</td></tr>';
                return;
            }

            const existingReviews = {};
            if (reviewData && reviewData.success) {
                (reviewData.data || []).forEach(r => {
                    existingReviews[r.item_key] = r;
                });
            }

            this.renderReviewTable(checklistData.data.sections || [], existingReviews);
        } catch (err) {
            console.error('Error loading review data:', err);
            document.getElementById('supportReviewTableBody').innerHTML =
                '<tr><td colspan="5" class="text-center text-danger">Error loading data.</td></tr>';
        }
    },

    renderReviewTable(sections, existingReviews) {
        const tbody = document.getElementById('supportReviewTableBody');
        let html = '';
        let rowNum = 0;

        sections.forEach(section => {
            html += `<tr><td colspan="5" style="background:#f5f6fa;font-weight:700;padding:8px 12px;">
                ${escapeHtml(section.label)}</td></tr>`;
            section.items.forEach(item => {
                rowNum++;
                const existing = existingReviews[item.key] || {};
                const decision = existing.support_decision || '';
                const comment  = existing.support_comment  || '';

                html += `
                <tr data-item-key="${escapeHtml(item.key)}">
                    <td style="text-align:center">${rowNum}</td>
                    <td>${escapeHtml(item.label)}</td>
                    <td>${getChecklistStatusBadge(item.status)}</td>
                    <td>
                        <select class="form-control form-control-sm support-decision-select"
                                data-key="${escapeHtml(item.key)}"
                                onchange="SupportReview.scheduleItemSave('${escapeHtml(item.key)}')">
                            <option value="">—</option>
                            <option value="done"    ${decision==='done'    ?'selected':''}>Done</option>
                            <option value="na"      ${decision==='na'      ?'selected':''}>N/A</option>
                            <option value="still"   ${decision==='still'   ?'selected':''}>Still</option>
                            <option value="not_yet" ${decision==='not_yet' ?'selected':''}>Not Yet</option>
                        </select>
                    </td>
                    <td>
                        <textarea class="form-control form-control-sm support-comment-input"
                                  data-key="${escapeHtml(item.key)}"
                                  rows="2"
                                  placeholder="Comment..."
                                  onblur="SupportReview.scheduleItemSave('${escapeHtml(item.key)}')"
                        >${escapeHtml(comment)}</textarea>
                    </td>
                </tr>`;
            });
        });

        tbody.innerHTML = html || '<tr><td colspan="5" class="text-center text-muted">No items found.</td></tr>';
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

        const decision = row.querySelector('.support-decision-select')?.value ?? '';
        const comment  = row.querySelector('.support-comment-input')?.value  ?? '';

        try {
            await API.post('/swo/support_item_review.php', {
                swo_id:           this.currentSwoId,
                item_key:         itemKey,
                support_decision: decision,
                support_comment:  comment,
            });
        } catch (err) {
            console.error('Failed to save item review:', err);
        }
    },

    async submitAccept() {
        const comments = document.getElementById('supportOverallComments').value.trim();
        const confirmed = await confirmDialog('Accept this submission and send to Control for final approval?');
        if (!confirmed) return;

        const btn = document.getElementById('supportAcceptBtn');
        btn.disabled = true;

        try {
            const data = await API.post('/swo/support_accept.php', {
                swo_id:   this.currentSwoId,
                comments: comments,
            });
            if (data && data.success) {
                showSuccess('Submission accepted and forwarded to Control.');
                closeModal('supportReviewModal');
                SupportDashboard.loadData();
            } else {
                showError(data?.message || 'Failed to accept submission');
            }
        } finally {
            btn.disabled = false;
        }
    },

    async submitReject() {
        const comments = document.getElementById('supportOverallComments').value.trim();
        if (!comments) {
            showWarning('Please provide a rejection reason before rejecting.');
            return;
        }
        const confirmed = await confirmDialog('Reject this submission and return it to the user?');
        if (!confirmed) return;

        const btn = document.getElementById('supportRejectBtn');
        btn.disabled = true;

        try {
            const data = await API.post('/swo/support_reject.php', {
                swo_id:   this.currentSwoId,
                comments: comments,
            });
            if (data && data.success) {
                showSuccess('Submission rejected and returned to user.');
                closeModal('supportReviewModal');
                SupportDashboard.loadData();
            } else {
                showError(data?.message || 'Failed to reject submission');
            }
        } finally {
            btn.disabled = false;
        }
    },
};
