p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/InternalGrading/Index.vue'
s = open(p, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b, 1)

rep("""            <button type="button" class="primary-btn" @click="openCreateProfile">New profile</button>""",
    """            <button type="button" class="secondary-btn" @click="openSp"><font-awesome-icon icon="upload" /> Import S&amp;P table</button>
            <button type="button" class="primary-btn" @click="openCreateProfile">New profile</button>""")

rep("""<GradesPdMatrix""", """<!-- Import the S&P grading of an ECL model workbook -->
<div v-if="sp.open" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="sp.open = false">
    <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    <div class="relative z-10 max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-6 shadow-lg dark:bg-slate-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Import the S&amp;P grading of an ECL model</h3>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-maiic-50 p-3 text-sm text-gray-700 dark:bg-slate-900/60 dark:text-slate-300">
                <p class="font-semibold text-maiic-800 dark:text-emerald-300">What to upload</p>
                <p class="mt-1">MAIIC's ECL model workbook, as Finance keeps it (for December 2025, <span class="font-mono text-xs">MAIIC &amp; FInES ECL Model - 14th December 2025_Final.xlsx</span>). Two of its sheets are read and nothing else:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li><strong>ASS1-Internal Grades</strong>: the ten credit grades, each with its description, its S&amp;P rating, its broad grade and its PD range.</li>
                    <li><strong>PDs-S&amp;P</strong>: S&amp;P's average cumulative default rates by rating for horizons 1 to 10 years (the December 2025 model uses Table 25, Emerging Markets 1981-2024), each row tied to a grade.</li>
                </ul>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-slate-900/60 dark:text-slate-300">
                <p class="font-semibold text-gray-800 dark:text-slate-100">What happens</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li>Press <strong>Preview</strong>: the grades and their PDs are shown, with the S&amp;P source, before anything is saved.</li>
                    <li>A grade with no row of PDs on the second sheet is listed and left out, not given a curve the model does not have.</li>
                    <li><strong>Create draft profile</strong> saves it as a draft. It changes no PD until it is activated with the lock on its card, and it can be edited or deleted while it is a draft.</li>
                    <li>Next year, load the new model the same way under a new name.</li>
                </ul>
            </div>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="block"><span class="block text-sm font-medium text-gray-700 dark:text-slate-300">ECL model workbook (.xlsx)</span>
                <input type="file" accept=".xlsx,.xlsm" class="mt-1 block w-full text-sm" @change="spFile($event)" /></label>
            <label class="block"><span class="block text-sm font-medium text-gray-700 dark:text-slate-300">Profile name</span>
                <input v-model="sp.name" type="text" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-slate-600 dark:bg-slate-900" /></label>
        </div>
        <p v-if="sp.error" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ sp.error }}</p>

        <div v-if="sp.preview" class="mt-4">
            <p class="text-sm text-gray-700 dark:text-slate-300"><strong>Source:</strong> {{ sp.preview.source.provider }} {{ sp.preview.source.title }}<span v-if="sp.preview.source.region">, {{ sp.preview.source.region }}</span><span v-if="sp.preview.source.table"> ({{ sp.preview.source.table }})</span>. {{ sp.preview.source.note }}</p>
            <div class="mt-2 overflow-x-auto">
                <table class="maiic-table maiic-table-compact">
                    <thead><tr><th>Grade</th><th>S&amp;P</th><th>Broad grade</th><th class="num">PD range</th><th v-for="y in sp.preview.max_tenor" :key="y" class="num">Year {{ y }}</th></tr></thead>
                    <tbody>
                        <tr v-for="g in sp.preview.grades" :key="g.grade_code">
                            <td class="whitespace-nowrap font-semibold">{{ g.grade_code }} {{ g.description }}</td>
                            <td class="whitespace-nowrap">{{ g.sp_rating }}</td>
                            <td class="whitespace-nowrap">{{ g.broad }}</td>
                            <td class="num whitespace-nowrap">{{ pctTxt(g.lower) }} to {{ pctTxt(g.upper) }}</td>
                            <td v-for="y in sp.preview.max_tenor" :key="y" class="num">{{ g.pds[y] !== undefined ? pctTxt(g.pds[y]) : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul v-if="sp.preview.skipped.length" class="mt-2 list-disc pl-5 text-xs text-amber-700"><li v-for="(m, i) in sp.preview.skipped" :key="i">{{ m }}</li></ul>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="secondary-btn" :disabled="sp.busy" @click="sp.open = false">Cancel</button>
            <button type="button" class="secondary-btn" :disabled="!sp.file || sp.busy" @click="previewSp">{{ sp.busy && !sp.preview ? 'Reading...' : 'Preview' }}</button>
            <button type="button" class="primary-btn" :disabled="!sp.preview || !sp.preview.grades.length || !sp.name || sp.busy" @click="importSp">Create draft profile</button>
        </div>
    </div>
</div>

<GradesPdMatrix""")

rep("""const showModal = ref(false)""", """const showModal = ref(false)
const sp = ref({ open: false, file: null, name: '', preview: null, error: null, busy: false })
const openSp = () => { sp.value = { open: true, file: null, name: 'S&P Emerging Markets (ECL model, December 2025)', preview: null, error: null, busy: false } }
const spFile = (e) => { sp.value.file = e.target.files[0] || null; sp.value.preview = null; sp.value.error = null }
const pctTxt = (v) => (v === null || v === undefined) ? '-' : (Number(v) * 100).toFixed(2) + '%'
const previewSp = async () => {
    sp.value.busy = true; sp.value.error = null
    const fd = new FormData(); fd.append('file', sp.value.file)
    try {
        const { data } = await axios.post(route('internal-grading.import-sp.preview'), fd)
        sp.value.preview = data
    } catch (e) {
        sp.value.error = e.response?.data?.message || 'The workbook could not be read.'
    } finally { sp.value.busy = false }
}
const importSp = () => {
    sp.value.busy = true
    router.post(route('internal-grading.import-sp'), { file: sp.value.file, name: sp.value.name }, {
        forceFormData: true, preserveScroll: true,
        onSuccess: () => { sp.value.open = false },
        onFinish: () => { sp.value.busy = false },
    })
}""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
