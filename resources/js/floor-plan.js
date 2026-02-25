/**
 * PausePlus – Floor Plan JS
 * Alpine.js utilities for drag-and-drop table positioning.
 *
 * Requires: Alpine.js (already loaded via Livewire 3)
 */

/**
 * Main editor Alpine component
 */
function floorPlanEditor() {
    return {
        init() {
            // Future: add grid snapping, zoom, etc.
        }
    };
}

/**
 * Per-table draggable behaviour.
 * Uses the native HTML5 drag API as a lightweight alternative to SortableJS.
 *
 * @param {number} tableId
 * @param {number} initialX
 * @param {number} initialY
 */
function draggable(tableId, initialX, initialY) {
    return {
        tableId,
        x: initialX,
        y: initialY,
        dragging: false,
        offsetX: 0,
        offsetY: 0,

        init() {
            const el = this.$el;

            el.addEventListener('mousedown', (e) => {
                // Only left-button drag, not double-click (form open)
                if (e.detail > 1) return;
                this.dragging = true;
                this.offsetX = e.clientX - this.x;
                this.offsetY = e.clientY - this.y;
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!this.dragging) return;
                this.x = Math.max(0, e.clientX - this.offsetX);
                this.y = Math.max(0, e.clientY - this.offsetY);
                el.style.left = this.x + 'px';
                el.style.top  = this.y + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (!this.dragging) return;
                this.dragging = false;
                // Persist to server
                this.$wire.updateTablePosition(this.tableId, Math.round(this.x), Math.round(this.y));
            });

            // Touch support
            el.addEventListener('touchstart', (e) => {
                const touch = e.touches[0];
                this.dragging = true;
                this.offsetX  = touch.clientX - this.x;
                this.offsetY  = touch.clientY - this.y;
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!this.dragging) return;
                const touch = e.touches[0];
                this.x = Math.max(0, touch.clientX - this.offsetX);
                this.y = Math.max(0, touch.clientY - this.offsetY);
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
