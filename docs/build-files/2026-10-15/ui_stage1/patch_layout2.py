p='C:/xampp/htdocs/MAICC-IFRS9/resources/js/Layouts/AppLayout.vue'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep('''            <main>
                <div class="pt-6 pb-20">''','''            <main ref="main">
                <div class="pt-6 pb-20">''')
rep('''import { router } from '@inertiajs/vue3'
''','''import { router } from '@inertiajs/vue3'
import { markRaw } from 'vue'
''')
rep('''            sidebarCollapsed: readSidebarCollapsed(),''','''            sidebarCollapsed: readSidebarCollapsed(),
            // the row the page's #actions are teleported into; a plain element
            // that placeActions() moves, never rendered by Vue itself
            actionsRow: markRaw(makeActionsRow()),
            actionsObserver: null,
            actionsFrame: null,''')
rep('''    mounted() {
        this.initializeChannels();
        this.initNavigationFeedback();
    },''','''    mounted() {
        this.initializeChannels();
        this.initNavigationFeedback();
        this.initActionsRow();
    },
    unmounted() {
        if (this.actionsRow) this.actionsRow.remove();
    },''')
rep('''    beforeUnmount() {
        if (this.busyShowAfter) clearTimeout(this.busyShowAfter);''','''    beforeUnmount() {
        if (this.actionsObserver) this.actionsObserver.disconnect();
        if (this.actionsFrame) cancelAnimationFrame(this.actionsFrame);
        if (this.busyShowAfter) clearTimeout(this.busyShowAfter);''')
rep('''        // the desktop sidebar's icon rail, remembered in this browser''','''        // ---- the page's action buttons, in a row above its table ----------
        initActionsRow() {
            if (!this.$slots.actions) return;
            this.placeActions();
            // client-side tabs, filters and v-if panels change which table is
            // first: place the row again after any change under <main>
            if (typeof MutationObserver === 'undefined' || !this.$refs.main) return;
            this.actionsObserver = new MutationObserver((records) => {
                const row = this.actionsRow;
                if (records.every(r => row.contains(r.target))) return;
                if (this.actionsFrame) return;
                this.actionsFrame = requestAnimationFrame(() => {
                    this.actionsFrame = null;
                    this.placeActions();
                });
            });
            this.actionsObserver.observe(this.$refs.main, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        },
        placeActions() {
            const row = this.actionsRow;
            const body = this.$refs.pageBody;
            const home = this.$refs.actionsHome;
            if (!row || !body || !home) return;
            const target = actionsTarget(body, row);
            if (target) {
                if (row.parentNode !== target.parentNode || row.nextSibling !== target) target.parentNode.insertBefore(row, target);
            } else if (row.parentNode !== home) {
                home.appendChild(row);
            }
        },
        // the desktop sidebar's icon rail, remembered in this browser''')
rep('''export default {''','''// The page's action buttons sit in their own row at the far right, directly
// above the page's first visible table (UI request of 13 October 2026).
function makeActionsRow() {
    if (typeof document === 'undefined') return null;
    const el = document.createElement('div');
    el.className = 'mb-3 flex flex-wrap items-center justify-end gap-2';
    el.setAttribute('data-actions-row', '');
    return el;
}
function visible(el) {
    return !!el && el.getClientRects().length > 0;
}
// Where the actions row goes: before the page's own [data-actions-anchor],
// else before the container of the first visible table (its panel, else its
// .maiic-table-wrap, else the table). A container that is a cell of a grid or
// a row of flex items is lifted to the grid or row, so the actions row never
// becomes a cell beside it. Null when the page shows no table.
function actionsTarget(body, row) {
    const usable = el => visible(el) && !row.contains(el) && !el.closest('[role="dialog"], [aria-modal="true"], .fixed');
    const mark = [...body.querySelectorAll('[data-actions-anchor]')].find(usable);
    if (mark) return mark;
    const wrap = [...body.querySelectorAll('.maiic-table-wrap')].find(el => usable(el) && el.querySelector('table'));
    const table = wrap ? wrap.querySelector('table') : [...body.querySelectorAll('table')].find(usable);
    if (!table) return null;
    let node = table.closest('.maiic-panel') || wrap || table.closest('.overflow-x-auto') || table;
    if (!body.contains(node) || node === body) node = table;
    while (node.parentElement && node.parentElement !== body) {
        const cs = getComputedStyle(node.parentElement);
        const side = cs.display.includes('grid') || (cs.display.includes('flex') && !cs.flexDirection.startsWith('column'));
        if (!side) break;
        node = node.parentElement;
    }
    return node;
}

export default {''')
open(p,'w',encoding='utf-8',newline='').write(s)
