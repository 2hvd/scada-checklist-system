/* auto_refresh.js - Lightweight automatic page refresh */
(function () {
    const AUTO_REFRESH_MS = 30000;
    const MIN_IDLE_MS = 8000;
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
