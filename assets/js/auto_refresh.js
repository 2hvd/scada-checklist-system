/* auto_refresh.js - Lightweight automatic page refresh */
(function () {
    const body = document.body || {};
    const AUTO_REFRESH_MS = Number(body.dataset.autoRefreshMs) || 60000;
    // Keep a short idle guard so we refresh only after the user stops interacting.
    const MIN_IDLE_MS = Number(body.dataset.autoRefreshIdleMs) || 10000;
    let lastInteractionAt = Date.now();

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

    setInterval(() => {
        if (!canRefreshNow()) return;
        window.location.reload();
    }, AUTO_REFRESH_MS);
})();
