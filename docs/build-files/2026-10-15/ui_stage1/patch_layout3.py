p='C:/xampp/htdocs/MAICC-IFRS9/resources/js/Layouts/AppLayout.vue'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep("""        initActionsRow() {
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
""", """        initActionsRow() {
            this.placeActions();
            // client-side tabs, filters and v-if panels change which table is
            // first: place the row again after any change under <main>
            if (typeof MutationObserver === 'undefined' || !this.$refs.main) return;
            this.actionsObserver = new MutationObserver((records) => {
                const row = this.actionsRow;
                if (records.every(r => row.contains(r.target))) return;
                this.schedulePlaceActions();
            });
            this.actionsObserver.observe(this.$refs.main, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        },
        schedulePlaceActions() {
            if (this.actionsFrame || typeof requestAnimationFrame === 'undefined') return;
            this.actionsFrame = requestAnimationFrame(() => {
                this.actionsFrame = null;
                this.placeActions();
            });
        },
        placeActions() {
            const row = this.actionsRow;
            const body = this.$refs.pageBody;
            const home = this.$refs.actionsHome;
            if (!row || !body || !home) return;
            // a page whose #actions render nothing (or not yet) leaves no gap
            row.hidden = row.children.length === 0;
""")
rep("""    unmounted() {
        if (this.actionsRow) this.actionsRow.remove();
    },""", """    // a page's #actions can appear or change after the first paint
    updated() {
        this.schedulePlaceActions();
    },
    unmounted() {
        if (this.actionsRow) this.actionsRow.remove();
    },""")
open(p,'w',encoding='utf-8',newline='').write(s)
print('ok')
