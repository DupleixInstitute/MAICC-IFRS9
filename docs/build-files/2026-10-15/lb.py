p='resources/js/Pages/LoanBooks/Index.vue'
s=open(p,encoding='utf-8').read()
a=s.index('<script setup>')
tpl='''<template>
    <AppLayout title="Loan Book" description="Every loan in a month-end loan book, with its stage, exposure, PD, LGD and ECL">
        <template #actions>
            <select v-model="period" @change="fetchData" class="maiic-select w-40" aria-label="Reporting period">
                <option v-for="p in periods" :key="p" :value="p">{{ periodLabel(p) }}</option>
            </select>
            <select v-model="filters.stage" @change="fetchData" class="maiic-select w-32" aria-label="Stage">
                <option value="">All stages</option>
                <option value="1">Stage 1</option>
                <option value="2">Stage 2</option>
                <option value="3">Stage 3</option>
            </select>
            <input type="text" v-model="filters.search" @input="fetchDataDebounced" placeholder="Search contract or customer" class="maiic-input w-56" aria-label="Search"/>
            <button @click="openExportModal" class="secondary-btn" title="Balances by stage for a range of periods">Export summary</button>
            <button @click="openDisbursementModal" class="secondary-btn" title="Loans disbursed within a range of periods">Disbursements</button>
            <Link :href="route('loan_applications.loan-book.import.create')" class="primary-btn">Import loan book</Link>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" v-if="summary">
                <div class="maiic-kpi" style="--accent: #15803d"><div class="maiic-kpi-label">Loans</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.total_loans || 0).toLocaleString() }}</div></div>
                <div class="maiic-kpi" style="--accent: #15803d"><div class="maiic-kpi-label">Total EAD (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.total_balance) }}</div></div>
                <div class="maiic-kpi" style="--accent: #dc2626" :title="Number(summary.stage_3_count || 0).toLocaleString() + ' loans in stage 3'"><div class="maiic-kpi-label">Stage 3 exposure (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.stage_3_exposure) }}</div></div>
                <div class="maiic-kpi" style="--accent: #d97706"><div class="maiic-kpi-label">ECL coverage</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.ecl_coverage || 0).toFixed(2) }}%</div></div>
                <div class="maiic-kpi" style="--accent: #dc2626"><div class="maiic-kpi-label">Total ECL (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.total_provision) }}</div></div>
            </div>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Contract ID</th>
                                <th>Customer</th>
                                <th>Stage</th>
                                <th class="num">EAD (MWK)</th>
                                <th class="num">PD</th>
                                <th class="num">LGD</th>
                                <th class="num">ECL (MWK)</th>
                                <th class="num">Coverage</th>
                                <th class="num">Days overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="loan in loanBooks.data" :key="loan.id">
                                <td class="whitespace-nowrap font-mono text-xs">{{ loan.contract_id }}</td>
                                <td class="font-semibold text-gray-900">{{ loan.client?.name || loan.customer_name || loan.external_identity_id }}</td>
                                <td class="whitespace-nowrap">
                                    <span :class="['maiic-badge', stageOf(loan) === 3 ? 'maiic-badge-red' : stageOf(loan) === 2 ? 'maiic-badge-gold' : 'maiic-badge-green']">Stage {{ stageOf(loan) }}</span>
                                </td>
                                <td class="num">{{ formatMoney(loan.ead ?? loan.carrying_amount) }}</td>
                                <td class="num">{{ formatRate(loan.pd_post_fli ?? loan.pd_value) }}</td>
                                <td class="num">{{ formatRate(loan.lgd_value) }}</td>
                                <td class="num">{{ formatMoney(loan.ecl_value) }}</td>
                                <td class="num">{{ coverageOf(loan) }}</td>
                                <td class="num"><span :class="getOverdueClass(loan.overdue_days)">{{ loan.overdue_days }}</span></td>
                            </tr>
                            <tr v-if="!loanBooks.data.length">
                                <td colspan="9" class="maiic-empty">{{ periods.length ? 'No loan matches these filters.' : 'No loan book has been imported yet. Use Import loan book (top right).' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination v-if="loanBooks.links" :links="loanBooks.links"/>
        </div>
        <HelpManual/>
    </AppLayout>

    <!-- Export summary -->
    <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="showExportModal = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-900">Export the loan book summary</h3>
                <p class="text-sm text-gray-500">Balances by IFRS 9 stage for each period in the range.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label for="exportPortfolio" class="maiic-flabel">Portfolio</label>
                    <select v-model="exportForm.portfolio_id" id="exportPortfolio" class="maiic-select">
                        <option value="">All portfolios</option>
                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="startPeriod" class="maiic-flabel">From</label><input type="month" v-model="exportForm.start_period" id="startPeriod" class="maiic-input" required></div>
                    <div><label for="endPeriod" class="maiic-flabel">To</label><input type="month" v-model="exportForm.end_period" id="endPeriod" class="maiic-input" required></div>
                </div>
                <p v-if="exportError" class="text-xs text-red-600">{{ exportError }}</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button @click="showExportModal = false" class="secondary-btn">Cancel</button>
                <button @click="submitExport" class="primary-btn" :disabled="exporting">{{ exporting ? 'Exporting...' : 'Export' }}</button>
            </div>
        </div>
    </div>

    <!-- Disbursement report -->
    <div v-if="showDisbursementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="showDisbursementModal = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-900">Export the disbursement report</h3>
                <p class="text-sm text-gray-500">The amount disbursed and the number of loans originated in the range.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label for="disbursementPortfolio" class="maiic-flabel">Portfolio</label>
                    <select v-model="disbursementForm.portfolio_id" id="disbursementPortfolio" class="maiic-select">
                        <option value="">All portfolios</option>
                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="dStart" class="maiic-flabel">From</label><input type="month" v-model="disbursementForm.start_period" id="dStart" class="maiic-input" required></div>
                    <div><label for="dEnd" class="maiic-flabel">To</label><input type="month" v-model="disbursementForm.end_period" id="dEnd" class="maiic-input" required></div>
                </div>
                <div>
                    <span class="maiic-flabel">Content</span>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" v-model="disbursementForm.mode" value="summary" class="text-maiic-600"> Summary only</label>
                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-700"><input type="radio" v-model="disbursementForm.mode" value="detailed" class="text-maiic-600"> Summary and each contract</label>
                </div>
                <p v-if="disbursementError" class="text-xs text-red-600">{{ disbursementError }}</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button @click="showDisbursementModal = false" class="secondary-btn">Cancel</button>
                <button @click="submitDisbursementExport" class="primary-btn" :disabled="disbursementExporting">{{ disbursementExporting ? 'Exporting...' : 'Export' }}</button>
            </div>
        </div>
    </div>
</template>

'''
s=tpl+s[a:]

# script: imports and props
old="""import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '../../Components/HelpManual.vue';

const props = defineProps({
    loanBooks: Object,
    filters: Object,
    portfolios: Array
});

const summary = ref(null);
const filters = ref({
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    stage: '',
    search: '',
    ...props.filters
});

const years = [2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030];
const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];
"""
assert old in s
s=s.replace(old,"""import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '@/Components/HelpManual.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    loanBooks: Object,
    filters: Object,
    portfolios: Array,
    summary: Object,
    periods: { type: Array, default: () => [] },
});

// The tiles come with the page (the server's summary for the same filters).
const summary = ref(props.summary || null);
const filters = ref({
    stage: '',
    search: '',
    ...props.filters
});
// The month shown: the one asked for, else the latest the server opened on.
const pad = (m) => String(m).padStart(2, '0');
const period = ref(props.filters?.year && props.filters?.month
    ? props.filters.year + '-' + pad(props.filters.month)
    : (props.periods[0] || ''));
const periodLabel = (p) => {
    const [y, m] = String(p).split('-');
    return new Date(Number(y), Number(m) - 1, 1).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
};
""")
# remove import modal state & submitImport
a=s.index("const showImportModal = ref(false);")
s=s.replace("const showImportModal = ref(false);\n","")
a=s.index("const importing = ref(false);")
b=s.index("const exportForm = ref({")
s=s[:a]+"const exporting = ref(false);\nconst disbursementExporting = ref(false);\nconst exportError = ref('');\nconst disbursementError = ref('');\n\n"+s[b:]
a=s.index("const handleFileUpload")
b=s.index("const openExportModal")
s=s[:a]+s[b:]
# alerts -> inline errors
s=s.replace("""    if (!exportForm.value.start_period || !exportForm.value.end_period) {
        alert('Please select both start and end periods');
        return;
    }

    if (exportForm.value.start_period > exportForm.value.end_period) {
        alert('Start period cannot be later than end period');
        return;
    }
""","""    exportError.value = '';
    if (!exportForm.value.start_period || !exportForm.value.end_period) {
        exportError.value = 'Choose both the first and the last period.';
        return;
    }
    if (exportForm.value.start_period > exportForm.value.end_period) {
        exportError.value = 'The first period cannot be after the last.';
        return;
    }
""")
s=s.replace("""        alert('Export failed. Please try again.');""","""        exportError.value = 'The export could not start. Please try again.';""")
s=s.replace("""    if (!disbursementForm.value.start_period || !disbursementForm.value.end_period) {
        alert('Please select both start and end periods');
        return;
    }

    if (disbursementForm.value.start_period > disbursementForm.value.end_period) {
        alert('Start period cannot be later than end period');
        return;
    }
""","""    disbursementError.value = '';
    if (!disbursementForm.value.start_period || !disbursementForm.value.end_period) {
        disbursementError.value = 'Choose both the first and the last period.';
        return;
    }
    if (disbursementForm.value.start_period > disbursementForm.value.end_period) {
        disbursementError.value = 'The first period cannot be after the last.';
        return;
    }
""")
s=s.replace("""        alert('Disbursement export failed. Please try again.');""","""        disbursementError.value = 'The export could not start. Please try again.';""")
s=s.replace("""    showExportModal.value = true;""","""    exportError.value = '';
    showExportModal.value = true;""")
s=s.replace("""    showDisbursementModal.value = true;""","""    disbursementError.value = '';
    showDisbursementModal.value = true;""")
old="""        router.get(route('loan_applications.loan-book'), {
            search: filters.value.search,
            year: filters.value.year,
            month: filters.value.month,
            stage: filters.value.stage
        }, {"""
assert old in s
s=s.replace(old,"""        const [year, month] = String(period.value || '').split('-');
        router.get(route('loan_applications.loan-book'), {
            search: filters.value.search || undefined,
            year: year || undefined,
            month: month ? Number(month) : undefined,
            stage: filters.value.stage || undefined
        }, {""")
old="""        const response = await fetch(route('loan_applications.loan-book.summary', {
            search: filters.value.search,
            year: filters.value.year,
            month: filters.value.month,
            stage: filters.value.stage
        }));"""
assert old in s
s=s.replace(old,"""        const [year, month] = String(period.value || '').split('-');
        const response = await fetch(route('loan_applications.loan-book.summary', {
            search: filters.value.search || undefined,
            year: year || undefined,
            month: month ? Number(month) : undefined,
            stage: filters.value.stage || undefined
        }));""")
s=s.replace("""const fetchSummary = async""","""const fetchDataDebounced = debounce(() => fetchData(), 400);

const fetchSummary = async""")
# remove downloadTemplate + onMounted
a=s.index("const downloadTemplate")
b=s.index("</script>")
s=s[:a]+s[b:]
open(p,'w',encoding='utf-8').write(s)
