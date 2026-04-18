﻿<div class="card">
    <div class="card-header">
        <h3 class="card-title">Submitted SWOs</h3>
    </div>
    <div class="table-wrapper" style="overflow-x:auto;">
        <table class="swo-submitted-table">
            <thead>
                <tr>
                    <th>SWO #</th>
                    <th>Station</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Your Review</th>
                    <th>Control Review</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="submittedSwoTableBody">
                <tr><td colspan="8" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadSubmittedSWOs() {
    const tbody = document.getElementById('submittedSwoTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="loading-overlay"><div class="loading-spinner"></div></div></td></tr>';

    try {
        const data = await API.get('/dashboard/support_data.php');
        if (!data || !data.success) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Failed to load SWOs.</td></tr>';
            return;
        }

        const submitted = (data.data.my_swos || []).filter(s =>
            ['Pending Support Review', 'Pending Control Review', 'Returned from Control', 'Completed'].includes(s.status)
        );

        if (!submitted.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No submitted SWOs yet.</td></tr>';
            return;
        }

        tbody.innerHTML = submitted.map(s => {
            const editBtn = s.status === 'Returned from Control'
                ? `<a class="btn btn-warning btn-sm" href="/scada-checklist-system/views/support/review_swo.php?swo_id=${s.id}">Edit</a>`
                : '';
            return `
                <tr>
                    <td><strong>${escapeHtml(s.swo_number)}</strong></td>
                    <td>${escapeHtml(s.station_name)}</td>
                    <td>${escapeHtml(s.assigned_to_name || '"”')}</td>
                    <td>${getStatusBadge(s.status)}</td>
                    <td>${s.submitted_at ? formatDateShort(s.submitted_at) : '"”'}</td>
                    <td>${s.support_reviewed_at ? formatDateShort(s.support_reviewed_at) : '"”'}</td>
                    <td>${s.control_reviewed_at ? formatDateShort(s.control_reviewed_at) : '"”'}</td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a class="btn btn-info btn-sm" href="/scada-checklist-system/views/support/view_submitted_swo.php?swo_id=${s.id}">View</a>
                        ${editBtn}
                    </td>
                </tr>
            `;
        }).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading SWOs.</td></tr>';
    }
}
</script>
