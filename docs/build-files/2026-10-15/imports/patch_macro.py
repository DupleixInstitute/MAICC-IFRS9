import os
D = os.path.dirname(os.path.abspath(__file__))
p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Macro/Index.vue'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'
s = s.replace('\r\n', '\n')

a = s.index('      <!-- Import / export -->')
b = s.index('    </div>\n  </app-layout>', a)
block = open(os.path.join(D, 'macro_import.vue'), encoding='utf-8').read()
s = s[:a] + block + s[b:]


def rep(old, new):
    global s
    assert s.count(old) == 1, old
    s = s.replace(old, new)


rep("import { Link, router } from '@inertiajs/vue3'\n",
    "import { Link, router } from '@inertiajs/vue3'\nimport FileDrop from '@/Components/Data/FileDrop.vue'\nimport ImportHowTo from '@/Components/Data/ImportHowTo.vue'\nimport LocalPager from '@/Components/Data/LocalPager.vue'\n")
rep("  components: { AppLayout, Link },", "  components: { AppLayout, Link, FileDrop, ImportHowTo, LocalPager },")
rep("m: { period: '', value: '', is_forecast: false, reason: '' }, previewData: null,",
    "m: { period: '', value: '', is_forecast: false, reason: '' }, batchPage: 1, previewData: null,")
rep("  computed: {\n", "  computed: {\n    pagedBatches() { return (this.batches || []).slice((this.batchPage - 1) * 15, this.batchPage * 15) },\n")
rep("  methods: {\n", "  methods: {\n    batchSeries(b) {\n      try { return JSON.parse(b.series || '[]').map(x => x.code).join(', ') } catch (e) { return '' }\n    },\n")
open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))
print('ok', repr(nl))
