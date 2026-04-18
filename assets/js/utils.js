/* utils.js - Utility functions */

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getStatusBadge(status) {
    const cls = getStatusBadgeClass(status);
    return `<span class="badge ${cls}">${escapeHtml(status)}</span>`;
}

function getStatusBadgeClass(status) {
    const statusMap = {
        'Rejected':                 'badge-rejected',
        'Pending':                  'badge-pending',
        'Registered':               'badge-registered',
        'In Progress':              'badge-inprogress',
        'Submitted':                'badge-submitted',
        'Pending Support Review':   'badge-submitted',
        'Pending Control Review':   'badge-pending',
        'Returned from Control':    'badge-warning',
        'Completed':                'badge-completed',
        'Closed':                   'badge-closed',
    };
    return statusMap[status] || 'badge-pending';
}

function getChecklistStatusBadge(status) {
    const labels = {
        'done':    { label: 'Done',     cls: 'status-done' },
        'na':      { label: 'N/A',      cls: 'status-na' },
        'not_yet': { label: 'Not Yet',  cls: 'status-not_yet' },
        'still':   { label: 'Still',    cls: 'status-still' },
        'empty':   { label: '—',        cls: 'status-empty' },
    };
    const s = labels[status] || labels['empty'];
    return `<span class="badge ${s.cls}">${s.label}</span>`;
}

function getProgressColor(percent) {
    if (percent >= 80) return '#27ae60';
    if (percent >= 50) return '#f39c12';
    return '#e74c3c';
}

function formatProgress(done, na, total) {
    const completed = done + na;
    const pct = total > 0 ? Math.round(completed / total * 100) : 0;
    return { completed, total, pct };
}

function confirmDialog(message) {
    return new Promise(resolve => {
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal" style="max-width:400px">
                <div class="modal-header">
                    <span class="modal-title">Confirm Action</span>
                </div>
                <p style="margin:0 0 20px;font-size:15px;">${escapeHtml(message)}</p>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                    <button class="btn btn-danger" id="confirmOk">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        // Trigger transition on next frame so CSS opacity transition fires
        requestAnimationFrame(() => requestAnimationFrame(() => overlay.classList.add('active')));

        const close = (result) => {
            overlay.classList.remove('active');
            overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
            resolve(result);
        };
        overlay.querySelector('#confirmOk').onclick = () => close(true);
        overlay.querySelector('#confirmCancel').onclick = () => close(false);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
    });
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;');
}

function renderProgressBar(pct, showText = true) {
    const color = getProgressColor(pct);
    return `
        <div class="progress-bar-wrapper">
            <div class="progress-bar" style="width:${pct}%;background:${color}"></div>
        </div>
        ${showText ? `<div class="progress-text">${pct}% complete</div>` : ''}
    `;
}

function getDashboardRefreshMs(defaultMs = 10000) {
    const raw = document.body?.dataset?.dashboardRefreshMs;
    if (raw == null || raw === '') return defaultMs;
    const parsed = Number(raw);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : defaultMs;
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// Tab switching
function initTabs(tabsId) {
    const container = document.getElementById(tabsId) || document;
    container.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const content = document.getElementById(targetTab);
            if (content) content.classList.add('active');
        });
    });

    // Auto-select tab from URL ?tab= parameter (deferred so all listeners are ready)
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        setTimeout(() => {
            const targetBtn = container.querySelector(`.tab-btn[data-tab="${tabParam}"]`);
            if (targetBtn) targetBtn.click();
        }, 0);
    }
}
