/**
 * PausePlus – Floor Plan JS
 * Alpine.js utilities for drag-and-drop table positioning.
 *
 * Requires: Alpine.js (already loaded via Livewire 3)
 */

/**
 * Main editor Alpine component (canvas-level)
 */
function floorPlanEditor() {
    return {
        init() {
            // Future: grid snapping, zoom, undo/redo
        }
    };
}

/**
 * Per-table draggable behaviour (canvas-relative coordinates).
 *
 * @param {number} tableId
 * @param {number} initialX  CSS left position within canvas
 * @param {number} initialY  CSS top position within canvas
 */
function draggable(tableId, initialX, initialY) {
    return {
        tableId,
        x: initialX,
        y: initialY,
        dragging: false,
        startMouseX: 0,
        startMouseY: 0,
        startX: 0,
        startY: 0,

        init() {
            const el     = this.$el;
            const canvas = () => document.getElementById('floor-plan-canvas');

            const getCanvasPos = () => {
                const c = canvas();
                return c ? c.getBoundingClientRect() : { left: 0, top: 0, width: 800, height: 600 };
            };

            // ── Mouse ─────────────────────────────────────────────
            el.addEventListener('mousedown', (e) => {
                if (e.detail > 1) return; // ignore double-click (opens form)
                if (e.button !== 0) return;
                this.dragging    = true;
                this.startMouseX = e.clientX;
                this.startMouseY = e.clientY;
                this.startX      = this.x;
                this.startY      = this.y;
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!this.dragging) return;
                const rect = getCanvasPos();
                const maxX = rect.width  - el.offsetWidth;
                const maxY = rect.height - el.offsetHeight;
                this.x = Math.max(0, Math.min(maxX, this.startX + (e.clientX - this.startMouseX)));
                this.y = Math.max(0, Math.min(maxY, this.startY + (e.clientY - this.startMouseY)));
                el.style.left = this.x + 'px';
                el.style.top  = this.y + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!this.dragging) return;
                this.dragging = false;
                this.$wire.updateTablePosition(this.tableId, Math.round(this.x), Math.round(this.y));
            });

            // ── Touch ─────────────────────────────────────────────
            el.addEventListener('touchstart', (e) => {
                if (e.touches.length !== 1) return;
                const t = e.touches[0];
                this.dragging    = true;
                this.startMouseX = t.clientX;
                this.startMouseY = t.clientY;
                this.startX      = this.x;
                this.startY      = this.y;
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!this.dragging) return;
                const t    = e.touches[0];
                const rect = getCanvasPos();
                const maxX = rect.width  - el.offsetWidth;
                const maxY = rect.height - el.offsetHeight;
                this.x = Math.max(0, Math.min(maxX, this.startX + (t.clientX - this.startMouseX)));
                this.y = Math.max(0, Math.min(maxY, this.startY + (t.clientY - this.startMouseY)));
                el.style.left = this.x + 'px';
                el.style.top  = this.y + 'px';
            }, { passive: true });

            document.addEventListener('touchend', () => {
                if (!this.dragging) return;
                this.dragging = false;
                this.$wire.updateTablePosition(this.tableId, Math.round(this.x), Math.round(this.y));
            });
        }
    };
}
