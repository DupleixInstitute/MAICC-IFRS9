import os
D = os.path.dirname(os.path.abspath(__file__))
p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir/Intake.vue'
s = open(p, encoding='utf-8').read()
i = s.index('            <!-- Import result -->')
top = open(os.path.join(D, 'intake_top.vue'), encoding='utf-8').read()
s = top + s[i:]


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new)


rep("""            <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                {{ error }}
            </div>""", """            <div v-if="error" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <svg class="mt-0.5 h-5 w-5 flex-none text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <div>
                    <div class="font-semibold">The file was not imported</div>
                    <p class="mt-1">{{ error }}</p>
                </div>
            </div>""")
rep("""import JetLabel from '@/Jetstream/Label.vue'
""", """import InPageTabs from '@/Components/Data/InPageTabs.vue'
import ImportHistoryTable from '@/Components/Data/ImportHistoryTable.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import FileDrop from '@/Components/Data/FileDrop.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
""")
rep("components: { AppLayout, JetLabel, Link },",
    "components: { AppLayout, Link, InPageTabs, ImportHistoryTable, ImportHowTo, FileDrop, LocalPager },")
rep("""        initialType: { type: String, default: 'contract_master' },
    },""", """        initialType: { type: String, default: 'contract_master' },
        recentImports: { type: Array, default: () => [] },
    },""")
rep("""            importType: this.initialType || 'contract_master',
""", """            importType: this.initialType || 'contract_master',
            tab: 'upload',
            historyPage: 1,
            importTypes: [
                { key: 'contract_master', short: 'contract master', label: 'Contract master (Extract A), facility terms, monthly' },
                { key: 'schedule', short: 'repayment schedules', label: 'Repayment schedule (per contract, once)' },
                { key: 'fees', short: 'fees', label: 'Fees and transaction costs' },
                { key: 'contract_transactions', short: 'contract transactions', label: 'Contract transactions (Extract B), scheduled and actual cash flows' },
                { key: 'gl_interest', short: 'GL interest postings', label: 'GL interest postings (Extract C), what the ledger posted' },
                { key: 'reference_rates', short: 'reference rates', label: 'Reference rates (File C), the prime lending rate by effective date' },
                { key: 'disbursements', short: 'drawdowns', label: 'Drawdowns, one row per tranche paid out' },
            ],
""")
rep("""    computed: {
        /** Named for the type""", """    watch: {
        importType() { this.historyPage = 1 },
    },
    computed: {
        typeLabel() {
            return (this.importTypes.find(t => t.key === this.importType) || {}).label || this.importType
        },
        typeShort() {
            return (this.importTypes.find(t => t.key === this.importType) || {}).short || this.importType
        },
        /** What is particular to the chosen type, from the importer's own rules. */
        typeSteps() {
            return {
                contract_transactions: [
                    'Rows marked Scheduled are kept as the remaining schedule for comparison. They never replace the original schedule the EIR uses.',
                    'Rows marked Actual are kept as transaction evidence and do not appear in Cash Flows. The fee column is optional.',
                ],
                reference_rates: [
                    'Write dates year first (yyyy-mm-dd). A file with any other date shape is refused as a whole, naming the column and the first bad row.',
                    'Dates must rise down the file. Rates are percentages, such as 25.30. A row that repeats the previous rate is loaded but is not a rate change.',
                ],
                disbursements: [
                    'One row per tranche, dates written year first (yyyy-mm-dd). Any other date shape refuses the whole file.',
                    'Every amount must be above zero. Loading the same file twice adds nothing: rows are matched on facility, date, amount, tranche and reference.',
                ],
            }[this.importType] || []
        },
        tabs() {
            return [
                { key: 'upload', label: 'Upload' },
                { key: 'history', label: 'History', count: this.typeHistory.length },
            ]
        },
        /** The imports log rows of the chosen type, named "EIR <type>: <file>". */
        typeHistory() {
            const prefix = 'EIR ' + this.importType + ':'
            return this.recentImports.filter(r => String(r.name || '').startsWith(prefix))
        },
        pagedHistory() {
            return this.typeHistory.slice((this.historyPage - 1) * 15, this.historyPage * 15)
        },
        historyCaption() {
            return this.typeHistory.length.toLocaleString() + ' uploads of ' + this.typeShort + '. The Imports screen logs every kind.'
        },
        /** Named for the type""")
# onFile now receives the File from FileDrop, not the change event.
rep("""        onFile(e) {
            this.file = e.target.files[0]
            this.resetAnalysis()
        },""", """        onFile(file) {
            this.file = file
            this.resetAnalysis()
        },""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
