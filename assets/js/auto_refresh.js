/* auto_refresh.js - Near real-time refresh watcher */
(function () {
    const body = document.body;
    if (!body) return;

    function readPositiveNumber(value, fallback, key) {
        if (value == null || value === '') return fallback;
        const n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            console.warn(`Invalid ${key} value "${value}", using ${fallback}.`);
            return fallback;
        }
        return n;
    }

    const POLL_INTERVAL_MS = readPositiveNumber(body.dataset.autoRefreshMs, 5000, 'data-auto-refresh-ms');
    // Short guard to avoid refreshing exactly while a click/typing action is being processed.
    const MIN_IDLE_MS = readPositiveNumber(body.dataset.autoRefreshIdleMs, 1200, 'data-auto-refresh-idle-ms');
    let lastInteractionAt = Date.now();
    let lastVersion = null;
    let refreshPending = false;
    let inFlight = false;

    const markInteraction = () => { lastInteractionAt = Date.now(); };

    ['mousedown', 'keydown', 'touchstart', 'input', 'change'].forEach(evt => {
        document.addEventListener(evt, markInteraction, { passive: true });
    });

    function hasFocusedEditableElement() {
        const active = document.activeElement;
        if (!active) return false;
        if (active.isContentEditable) return true;
        const tag = active.tagName;
        if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return false;
        return !active.disabled && !active.readOnly;
    }

    function canRefreshNow() {
        if (document.hidden) return false;
        if (Date.now() - lastInteractionAt < MIN_IDLE_MS) return false;
        if (hasFocusedEditableElement()) return false;
        if (document.querySelector('.modal-overlay.active')) return false;
        if (document.querySelector('.save-indicator.saving')) return false;
        return true;
    }

    async function checkForChanges() {
        if (inFlight) return;
        inFlight = true;
        try {
            const response = await fetch('/scada-checklist-system/api/auth/check_session.php', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                window.location.href = '/scada-checklist-system/index.php';
                return;
            }

            const data = await response.json();
            if (!data?.success) {
                window.location.href = '/scada-checklist-system/index.php';
                return;
            }

            const version = data?.data?.realtime_version ?? null;
            if (version == null) return;
            if (lastVersion === null) {
                lastVersion = version;
                return;
            }

            if (version !== lastVersion) {
                lastVersion = version;
                refreshPending = true;
            }

            if (refreshPending && canRefreshNow()) {
                refreshPending = false;
                window.location.reload();
            }
        } catch (err) {
            console.warn('Realtime refresh poll failed; retrying on next interval.', err);
        } finally {
            inFlight = false;
        }
    }

    checkForChanges();
    setInterval(checkForChanges, POLL_INTERVAL_MS);
})();
