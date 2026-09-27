/**
 * SCMB Block Inspector resizer
 *
 * Injects a draggable left-edge handle into the Gutenberg Block Inspector
 * sidebar (wp-admin outer document, NOT the canvas iframe).
 *
 * Persists the chosen width to localStorage under the key "scmbInspectorWidth".
 * Restores it on page load and applies the "scmb-wide-inspector" class to
 * <body> so the companion CSS can widen the sidebar.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------ */
    var STORAGE_KEY   = 'scmbInspectorWidth';
    var DEFAULT_WIDTH = 460;
    var MIN_WIDTH     = 220;
    var MAX_WIDTH     = 1200;
    var BODY_CLASS    = 'scmb-wide-inspector';
    var DRAG_CLASS    = 'scmb-inspector-resizing';
    var HANDLE_CLASS  = 'scmb-drag-resize';
    var CSS_PROP      = '--scmb-inspector-width';

    /* Sidebar selector — targets the outer skeleton column in the
     * wp-admin document (not the canvas iframe). */
    var SIDEBAR_SEL = '.interface-interface-skeleton__sidebar';

    /* ------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Clamp n between lo and hi.
     *
     * @param {number} n
     * @param {number} lo
     * @param {number} hi
     * @return {number}
     */
    function clamp(n, lo, hi) {
        return Math.min(Math.max(n, lo), hi);
    }

    /**
     * Read the persisted width from localStorage.
     * Falls back to DEFAULT_WIDTH if absent or invalid.
     *
     * @return {number}
     */
    function readStoredWidth() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw !== null) {
                var parsed = parseInt(raw, 10);
                if (!isNaN(parsed)) {
                    return clamp(parsed, MIN_WIDTH, MAX_WIDTH);
                }
            }
        } catch (_) {
            /* localStorage may be unavailable (private-browsing quota) */
        }
        return DEFAULT_WIDTH;
    }

    /**
     * Persist width to localStorage.
     *
     * @param {number} width
     * @return {void}
     */
    function saveWidth(width) {
        try {
            localStorage.setItem(STORAGE_KEY, String(width));
        } catch (_) {
            /* ignore write failures */
        }
    }

    /**
     * Apply width to the document: set the CSS custom property on <body>
     * and ensure the activation class is present.
     *
     * @param {number} width
     * @return {void}
     */
    function applyWidth(width) {
        document.body.style.setProperty(CSS_PROP, width + 'px');
        document.body.classList.add(BODY_CLASS);
    }

    /* ------------------------------------------------------------------
     * Handle injection
     * ------------------------------------------------------------------ */

    /**
     * Create and inject the drag handle into the sidebar element.
     * Idempotent — does nothing if a handle already exists.
     *
     * @param {Element} sidebar
     * @return {Element|null} The handle element, or null if sidebar absent.
     */
    function injectHandle(sidebar) {
        if (!sidebar) {
            return null;
        }

        /* Already injected */
        if (sidebar.querySelector('.' + HANDLE_CLASS)) {
            return sidebar.querySelector('.' + HANDLE_CLASS);
        }

        var handle = document.createElement('div');
        handle.className = HANDLE_CLASS;
        handle.setAttribute('role', 'separator');
        handle.setAttribute('aria-label', 'Resize inspector panel');
        handle.setAttribute('tabindex', '0');
        handle.setAttribute(
            'aria-valuenow',
            String(readStoredWidth())
        );
        handle.setAttribute('aria-valuemin', String(MIN_WIDTH));
        handle.setAttribute('aria-valuemax', String(MAX_WIDTH));

        sidebar.insertBefore(handle, sidebar.firstChild);

        return handle;
    }

    /* ------------------------------------------------------------------
     * Drag logic
     * ------------------------------------------------------------------ */

    /**
     * Attach pointer-drag behaviour to a handle element.
     *
     * Dragging leftward widens the sidebar; dragging rightward narrows it.
     * The sidebar grows from its left edge (the handle's position), so as the
     * user drags the handle left, the right edge of the sidebar stays fixed
     * and the left edge moves — i.e. the sidebar gets wider.
     *
     * @param {Element} handle
     * @return {void}
     */
    function attachDragBehaviour(handle) {
        var dragging    = false;
        var startX      = 0;
        var startWidth  = 0;
        var activePtrId = null;

        /* ---- Pointer start -------------------------------------------- */
        handle.addEventListener('pointerdown', function (e) {
            /* Only respond to the primary button / first touch point. */
            if (e.button !== undefined && e.button !== 0) {
                return;
            }

            e.preventDefault();

            dragging    = true;
            startX      = e.clientX;
            startWidth  = readStoredWidth();
            activePtrId = e.pointerId;

            /*
             * setPointerCapture routes ALL subsequent pointer events for this
             * pointerId to the handle element, even when the cursor leaves the
             * browser window.  This is what prevents the "glue" (stuck-drag)
             * bug that occurred when the user released the mouse outside the
             * viewport while the sidebar was clamped at MAX_WIDTH.
             */
            handle.setPointerCapture(e.pointerId);

            document.body.classList.add(DRAG_CLASS);
        });

        /* ---- Pointer move --------------------------------------------- */
        handle.addEventListener('pointermove', function (e) {
            if (!dragging || e.pointerId !== activePtrId) {
                return;
            }

            var delta    = startX - e.clientX;
            var rawWidth = startWidth + delta;
            var newWidth = clamp(rawWidth, MIN_WIDTH, MAX_WIDTH);

            /*
             * Re-anchor the drag origin whenever the sidebar is clamped at a
             * limit.  Without this, the cursor accumulates invisible "debt"
             * past the boundary so that subsequent movement in the opposite
             * direction produces no visual change until the debt is paid off,
             * creating a rubber-band / stuttering effect.
             */
            if (rawWidth !== newWidth) {
                startX     = e.clientX;
                startWidth = newWidth;
            }

            applyWidth(newWidth);
            handle.setAttribute('aria-valuenow', String(Math.round(newWidth)));
        });

        /* ---- Pointer end / cancel ------------------------------------- */
        function onPointerEnd(e) {
            if (!dragging || e.pointerId !== activePtrId) {
                return;
            }

            dragging    = false;
            activePtrId = null;

            if (handle.hasPointerCapture(e.pointerId)) {
                handle.releasePointerCapture(e.pointerId);
            }

            document.body.classList.remove(DRAG_CLASS);

            /* Persist the width that was actually applied. */
            var finalWidth = parseInt(
                document.body.style.getPropertyValue(CSS_PROP),
                10
            );

            if (!isNaN(finalWidth)) {
                saveWidth(finalWidth);
            }
        }

        handle.addEventListener('pointerup',     onPointerEnd);
        handle.addEventListener('pointercancel', onPointerEnd);

        /* ---- Keyboard (arrow keys) ------------------------------------ */
        handle.addEventListener('keydown', function (e) {
            var step = e.shiftKey ? 20 : 5;
            var current = readStoredWidth();
            var next;

            if (e.key === 'ArrowLeft') {
                /* Left arrow = wider (same direction as mouse drag left) */
                next = clamp(current + step, MIN_WIDTH, MAX_WIDTH);
            } else if (e.key === 'ArrowRight') {
                next = clamp(current - step, MIN_WIDTH, MAX_WIDTH);
            } else {
                return;
            }

            e.preventDefault();
            applyWidth(next);
            saveWidth(next);
            handle.setAttribute('aria-valuenow', String(next));
        });
    }

    /* ------------------------------------------------------------------
     * Initialisation
     * ------------------------------------------------------------------ */

    /**
     * Find the sidebar, apply the stored width, and inject/wire the handle.
     * Called once on DOMContentLoaded and re-tried via MutationObserver when
     * Gutenberg mounts its sidebar asynchronously.
     *
     * @return {void}
     */
    function init() {
        var width   = readStoredWidth();
        var sidebar = document.querySelector(SIDEBAR_SEL);

        if (!sidebar) {
            return;
        }

        applyWidth(width);

        var handle = injectHandle(sidebar);

        if (handle) {
            attachDragBehaviour(handle);
        }
    }

    /* Wait for DOM then watch for late-mounted Gutenberg sidebar */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }

    function bootstrap() {
        init();

        /* Gutenberg mounts the sidebar asynchronously after React hydration.
         * Watch for it so the handle is injected even if init() ran too early. */
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    if (
                        added[j].nodeType === 1 &&
                        (added[j].matches(SIDEBAR_SEL) ||
                            added[j].querySelector(SIDEBAR_SEL))
                    ) {
                        init();
                        return;
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }
})();
