import re
base = r'c:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages'
def patch(path, pairs):
    p = base + path
    s = open(p, encoding='utf-8').read()
    for old, new in pairs:
        assert s.count(old) == 1, (path, old[:60])
        s = s.replace(old, new)
    open(p, 'w', encoding='utf-8', newline='').write(s)

BTN = 'class="rounded-lg border px-3 py-1.5 text-sm font-semibold" :class="n === CUR ? \'border-maiic-600 bg-maiic-600 text-white\' : \'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:bg-maiic-50\'"'

# Audit Trail
patch(r'\AuditTrail\Index.vue', [
("""                <button :disabled="pagination.current_page <= 1" @click="go(pagination.current_page - 1)"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 font-medium disabled:opacity-40 hover:bg-gray-50">Previous</button>
""", """                <button :disabled="pagination.current_page <= 1" @click="go(pagination.current_page - 1)"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 font-medium disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <template v-for="n in pageWindow" :key="n">
                    <span v-if="n === '...'" class="px-1 py-1.5 text-gray-400">...</span>
                    <button v-else @click="go(n)" """ + BTN.replace('CUR', 'pagination.current_page') + """>{{ n }}</button>
                </template>
"""),
("""    methods: {
        go(page) {""", """    computed: {
        // Page numbers around the current page, with the first and last.
        pageWindow() {
            const cur = this.pagination.current_page, last = this.pagination.last_page
            const pages = []
            for (let n = 1; n <= last; n++) {
                if (n === 1 || n === last || Math.abs(n - cur) <= 2) pages.push(n)
                else if (pages[pages.length - 1] !== '...') pages.push('...')
            }
            return pages
        },
    },
    methods: {
        go(page) {"""),
])

# Discounted payments
patch(r'\DiscountedPayments\Index.vue', [
("""                                    <div class="flex items-center space-x-1">
                                        <span class="px-3 py-1 text-sm bg-maiic-600 text-white rounded-md">
                                            {{ discountedPayments.current_page }}
                                        </span>
                                        <span class="text-sm text-gray-500">of</span>
                                        <span class="text-sm text-gray-700">{{ discountedPayments.last_page }}</span>
                                    </div>""", """                                    <div class="flex items-center gap-1">
                                        <template v-for="n in pageWindow" :key="n">
                                            <span v-if="n === '...'" class="px-1 text-sm text-gray-400">...</span>
                                            <button v-else @click="goToPage(n)" """ + BTN.replace('CUR', 'discountedPayments.current_page') + """>{{ n }}</button>
                                        </template>
                                    </div>"""),
("""        // Pagination methods
        const prevPage = () => {""", """        // Pagination methods
        const pageWindow = computed(() => {
            const cur = props.discountedPayments.current_page, last = props.discountedPayments.last_page
            const pages = []
            for (let n = 1; n <= last; n++) {
                if (n === 1 || n === last || Math.abs(n - cur) <= 2) pages.push(n)
                else if (pages[pages.length - 1] !== '...') pages.push('...')
            }
            return pages
        });

        const goToPage = (page) => {
            const params = { page };
            Object.keys(search.value).forEach(key => {
                if (search.value[key]) {
                    params[key] = search.value[key];
                }
            });
            router.get(`/loss-given-default/${props.lgd.id}/discounted-payments`, params, { preserveScroll: true });
        };

        const prevPage = () => {"""),
])
print('ok')
