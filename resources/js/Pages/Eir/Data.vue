<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">EIR Data</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">EIR Data</h2>
          <p class="mt-1 text-sm text-gray-600">Review source records, calculated interest, and the GL reconciliation</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('eir-intake.index')" class="secondary-btn">EIR Data Intake</Link>
          <Link :href="route('eir-calculations.index')" class="primary-btn">Calculate EIR</Link>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-5">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-2xl font-bold text-gray-900">{{ number(card.value) }}</div>
          <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.label }}</div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 px-5 pt-4">
          <nav class="flex gap-6 overflow-x-auto">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
              :class="activeTab === tab.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
              @click="openTab(tab.key)"
            >
              {{ tab.label }}
              <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ number(tab.count) }}</span>
            </button>
          </nav>
        </div>

        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">{{ currentTab.label }}</h3>
            <p class="text-xs text-gray-500">{{ currentTab.description }}</p>
          </div>
          <form class="flex w-full flex-wrap gap-2 md:w-auto" @submit.prevent="applySearch">
            <select v-if="activeTab === 'schedules'" v-model="comparisonStatus" class="form-input md:w-56" @change="applySearch">
              <option value="">All comparison results</option>
              <option value="WITHIN_TOLERANCE">Within tolerance ({{ comparisonCounts.within_tolerance || 0 }})</option>
              <option value="PRINCIPAL_VARIANCE">Principal variance ({{ comparisonCounts.principal_variance || 0 }})</option>
              <option value="INTEREST_VARIANCE">Interest variance ({{ comparisonCounts.interest_variance || 0 }})</option>
              <option value="NO_REMAINING_DATA">No Extract B evidence ({{ comparisonCounts.no_remaining_data || 0 }})</option>
              <option value="NOT_COMPARED">Not compared / blocked ({{ comparisonCounts.not_compared || 0 }})</option>
            </select>
            <input v-model="search" class="form-input md:w-72" placeholder="Contract, account or reference">
            <button type="submit" class="secondary-btn">Search</button>
            <button v-if="activeTab === 'schedules' && (search || comparisonStatus)" type="button" class="secondary-btn" @click="clearFilters">Clear</button>
          </form>
        </div>

        <div v-if="activeTab === 'schedules'" class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-amber-50 p-4">
          <button class="secondary-btn" @click="post('eir-schedules.dry-run')">Dry-run readiness</button>
          <button class="primary-btn" @click="post('eir-schedules.generate-all')">Generate eligible drafts</button>
          <p class="text-xs text-amber-800">Extract B remaining rows are comparison evidence. Only an approved version-1 schedule is used by EIR.</p>
        </div>

        <div v-if="activeTab === 'gl'" class="border-b border-gray-200 bg-gray-50 p-4">
          <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div v-for="item in reconciliationCards" :key="item.label" class="rounded-md border border-gray-200 bg-white p-3">
              <div class="text-lg font-bold text-gray-900">{{ item.money ? money(item.value) : number(item.value) }}</div>
              <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ item.label }}</div>
            </div>
          </div>
          <p class="mt-3 text-xs text-gray-600">
            Variance is calculated EIR interest minus GL interest. Positive means additional income may be required; negative means the GL exceeds the EIR calculation. Tolerance is ±{{ reconciliation.tolerance_percent }}% of the GL amount.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table v-if="activeTab === 'contracts'" class="min-w-full">
            <thead><tr><th class="th">Contract</th><th class="th">Portfolio / product</th><th class="th">Terms</th><th class="th">Amounts</th><th class="th">Source coverage</th><th class="th">EIR status</th></tr></thead>
            <tbody>
              <tr v-for="r in data.data" :key="r.id">
                <td class="td"><div class="font-semibold text-gray-900">{{ r.contract_id }}</div><div class="text-xs text-gray-500">{{ r.sub_account_no || r.gl_account_code || 'No sub-account' }}</div></td>
                <td class="td"><div>{{ r.portfolio || '—' }}</div><div class="text-xs text-gray-500">{{ r.product_type || r.instrument_type }}</div></td>
                <td class="td"><div>{{ date(r.origination_date) }} → {{ date(r.maturity_date) }}</div><div class="text-xs text-gray-500">{{ frequency(r.payments_per_year) }} · {{ percent(r.contractual_rate) }}</div></td>
                <td class="td"><div>{{ money(r.drawn_amount) }} {{ r.currency || '' }}</div><div class="text-xs text-gray-500">Approved {{ money(r.approved_amount) }}</div></td>
                <td class="td"><div>{{ r.schedules_count }} cash flows · {{ r.fees_count }} fees</div><div class="text-xs text-gray-500">{{ r.terms_source_system || 'Source not recorded' }}</div></td>
                <td class="td"><span :class="statusClass(r.calculation_status)">{{ r.calculation_status || 'PENDING' }}</span><div v-if="r.eir_effective_annual !== null" class="mt-1 text-xs font-medium">{{ percent(r.eir_effective_annual) }} effective annual</div></td>
              </tr>
              <tr v-if="!data.data.length"><td colspan="6" class="p-10 text-center text-sm text-gray-500">No records found. Use EIR Data Intake to load data.</td></tr>
            </tbody>
          </table>

          <table v-else-if="activeTab === 'cashflows'" class="min-w-full">
            <thead><tr><th class="th">Contract</th><th class="th">Version</th><th class="th">Due date</th><th class="th">Principal</th><th class="th">Interest</th><th class="th">Fees</th><th class="th">Total due</th><th class="th">Source</th></tr></thead>
            <tbody>
              <tr v-for="r in data.data" :key="r.id">
                <td class="td font-semibold">{{ r.contract_id }}</td><td class="td">v{{ r.schedule_version }}</td><td class="td">{{ date(r.due_date) }}</td><td class="td">{{ money(r.principal_due) }}</td><td class="td">{{ money(r.interest_due) }}</td><td class="td">{{ money(r.fee_due) }}</td><td class="td font-semibold">{{ money(r.total_due) }}</td><td class="td"><div>{{ r.schedule_source || r.source_system || '—' }}</div><div class="text-xs text-gray-500">{{ r.source_reference }}</div></td>
              </tr>
              <tr v-if="!data.data.length"><td colspan="8" class="p-10 text-center text-sm text-gray-500">No records found. Use EIR Data Intake to load data.</td></tr>
            </tbody>
          </table>

          <table v-else-if="activeTab === 'schedules'" class="min-w-full">
            <thead><tr><th class="th">Contract</th><th class="th">Extract A terms</th><th class="th">Generated</th><th class="th">Extract B remaining</th><th class="th">Comparison</th><th class="th">Approval</th><th class="th">Actions</th></tr></thead>
            <tbody>
              <tr v-for="r in data.data" :key="r.id">
                <td class="td font-semibold">{{ r.contract_id }}</td>
                <td class="td"><div>{{ frequency(r.payments_per_year) }} · {{ percent(r.contractual_rate) }}</div><div class="text-xs text-gray-500">{{ date(r.origination_date) }} → {{ date(r.maturity_date) }}</div><div v-if="!r.generation_ready" class="mt-1 text-xs text-red-700">{{ (r.generation_issues || []).join('; ') }}</div></td>
                <td class="td">{{ r.schedules_count }} rows</td>
                <td class="td">{{ r.remaining_rows }} rows<div class="text-xs text-gray-500">from {{ date(r.comparison?.cutoff_date) }}</div></td>
                <td class="td"><span :class="comparisonClass(r.comparison?.status)">{{ label(r.comparison?.status) }}</span><div v-if="r.comparison?.principal_variance !== null" class="mt-1 text-xs">Principal Δ {{ signedMoney(r.comparison.principal_variance) }}<br>Interest Δ {{ signedMoney(r.comparison.interest_variance) }}</div></td>
                <td class="td"><span :class="approvalClass(r.schedule_approval_status)">{{ label(r.schedule_approval_status) }}</span></td>
                <td class="td"><div class="flex flex-col gap-2"><Link :href="route('eir-schedules.show', { contractEir: r.id })" class="secondary-btn">View schedule</Link><button v-if="r.schedule_approval_status !== 'APPROVED'" class="secondary-btn" :disabled="!r.generation_ready" @click="post('eir-schedules.generate', r.id)">Generate draft</button><button v-if="r.schedule_approval_status === 'DRAFT'" class="primary-btn" @click="approve(r)">Approve v1</button></div></td>
              </tr>
              <tr v-if="!data.data.length"><td colspan="7" class="p-10 text-center text-sm text-gray-500">No contracts found.</td></tr>
            </tbody>
          </table>

          <table v-else class="min-w-full">
            <thead>
              <tr><th class="th">Contract</th><th class="th">Period</th><th class="th">GL account</th><th class="th">GL interest</th><th class="th">EIR interest</th><th class="th">EIR − GL</th><th class="th">Variance %</th><th class="th">Status</th><th class="th">Source</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in data.data" :key="r.id">
                <td class="td font-semibold">{{ r.contract_id }}</td>
                <td class="td">{{ date(r.reporting_period) }}</td>
                <td class="td">{{ r.gl_account_code || '—' }}</td>
                <td class="td font-semibold">{{ money(r.interest_income_posted) }}</td>
                <td class="td font-semibold">{{ nullableMoney(r.eir_interest) }}</td>
                <td class="td font-semibold" :class="varianceTextClass(r.variance)">{{ signedMoney(r.variance) }}</td>
                <td class="td">{{ variancePercent(r) }}</td>
                <td class="td"><span :class="reconciliationClass(r.reconciliation_status)">{{ reconciliationLabel(r.reconciliation_status) }}</span></td>
                <td class="td"><div>{{ r.source_system }}</div><div class="text-xs text-gray-500">{{ r.source_reference }}</div></td>
              </tr>
              <tr v-if="!data.data.length"><td colspan="9" class="p-10 text-center text-sm text-gray-500">No GL postings match this search.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-wrap gap-2 border-t p-4">
          <button v-for="link in data.links" :key="link.label" v-html="link.label" :disabled="!link.url" @click="go(link.url)" class="rounded border px-3 py-1 text-sm disabled:opacity-40" :class="link.active ? 'bg-maiic-600 text-white' : 'bg-white text-gray-700'"></button>
        </div>
      </div>
    </div>

    <div v-if="approvalModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4" @click.self="closeApprovalModal">
      <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="schedule-review-title">
        <div class="border-b border-gray-200 px-6 py-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 id="schedule-review-title" class="text-lg font-semibold text-gray-900">Approve contractual schedule</h3>
              <p class="mt-1 text-sm text-gray-500">Review the comparison evidence before locking version 1.</p>
            </div>
            <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closeApprovalModal">&#10005;</button>
          </div>
        </div>

        <div class="space-y-5 px-6 py-5">
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Contract</div>
                <div class="mt-1 font-semibold text-gray-900">{{ approvalModal.row?.contract_id }}</div>
              </div>
              <span :class="comparisonClass(approvalModal.row?.comparison?.status)">{{ label(approvalModal.row?.comparison?.status) }}</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div><div class="text-xs text-gray-500">Principal variance</div><div class="mt-1 font-semibold text-gray-900">{{ signedMoney(approvalModal.row?.comparison?.principal_variance) }}</div></div>
              <div><div class="text-xs text-gray-500">Interest variance</div><div class="mt-1 font-semibold text-gray-900">{{ signedMoney(approvalModal.row?.comparison?.interest_variance) }}</div></div>
            </div>
          </div>

          <div>
            <label for="schedule-review-notes" class="text-sm font-semibold text-gray-800">
              Review note <span v-if="reviewNoteRequired" class="text-red-600">*</span>
            </label>
            <textarea id="schedule-review-notes" v-model="approvalModal.notes" rows="4" maxlength="2000" class="form-input mt-2 w-full" :placeholder="reviewNoteRequired ? 'Explain the variance and why this schedule is appropriate.' : 'Optional review evidence or conclusion.'"></textarea>
            <div class="mt-1 flex justify-between text-xs">
              <span :class="reviewNoteRequired ? 'text-amber-700' : 'text-gray-500'">{{ reviewNoteRequired ? 'Required because the comparison is outside tolerance.' : 'Optional when the schedule is within tolerance.' }}</span>
              <span class="text-gray-400">{{ approvalModal.notes.length }}/2000</span>
            </div>
            <p v-if="approvalModal.error" class="mt-2 text-sm text-red-700">{{ approvalModal.error }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
          <button type="button" class="secondary-btn" :disabled="approvalModal.processing" @click="closeApprovalModal">Cancel</button>
          <button type="button" class="primary-btn" :disabled="approvalModal.processing || (reviewNoteRequired && !approvalModal.notes.trim())" @click="confirmApproval">
            {{ approvalModal.processing ? 'Approving…' : 'Approve version 1' }}
          </button>
        </div>
      </div>
    </div>

    <teleport to="head"><title>EIR Data</title></teleport>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: { activeTab: String, data: Object, filters: Object, summary: Object },
  data() {
    return {
      search: this.filters.search || '',
      comparisonStatus: this.filters.comparison_status || '',
      approvalModal: { open: false, row: null, notes: '', processing: false, error: null },
    }
  },
  computed: {
    reconciliation() {
      return this.summary.reconciliation || {}
    },
    comparisonCounts() { return this.summary.schedule_comparisons || {} },
    tabs() {
      return [
        { key: 'contracts', label: 'Contract Master', count: this.summary.contracts, description: 'Facility terms, source conventions, fee and schedule coverage.' },
        { key: 'cashflows', label: 'Cash Flows', count: this.summary.cashflows, description: 'Contractual principal, interest and fee cash flows by due date.' },
        { key: 'schedules', label: 'Schedule Review', count: this.summary.contracts, description: 'Generate original schedules from Extract A, compare to Extract B remaining rows, then approve.' },
        { key: 'gl', label: 'GL Reconciliation', count: this.summary.gl_postings, description: 'Compare calculated effective-interest revenue with interest posted to the general ledger.' },
      ]
    },
    currentTab() {
      return this.tabs.find(tab => tab.key === this.activeTab)
    },
    reviewNoteRequired() {
      return this.approvalModal.row?.comparison?.status !== 'WITHIN_TOLERANCE'
    },
    cards() {
      return [
        { label: 'Contracts', value: this.summary.contracts },
        { label: 'Cash-flow rows', value: this.summary.cashflows },
        { label: 'GL postings', value: this.summary.gl_postings },
        { label: 'Locked EIRs', value: this.summary.locked_eirs },
      ]
    },
    reconciliationCards() {
      return [
        { label: 'Calculated rows', value: this.reconciliation.calculated_rows },
        { label: 'Within tolerance', value: this.reconciliation.within_tolerance },
        { label: 'Exceptions', value: this.reconciliation.missing_rows },
        { label: 'Matched GL interest', value: this.reconciliation.matched_gl_total, money: true },
        { label: 'Calculated EIR interest', value: this.reconciliation.eir_total, money: true },
        { label: 'Net EIR − GL', value: this.reconciliation.net_variance, money: true },
      ]
    },
  },
  methods: {
    number(value) {
      return Number(value || 0).toLocaleString()
    },
    money(value) {
      return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    nullableMoney(value) {
      return value === null || value === undefined ? '—' : this.money(value)
    },
    signedMoney(value) {
      if (value === null || value === undefined) return '—'
      const number = Number(value)
      return `${number > 0 ? '+' : ''}${this.money(number)}`
    },
    percent(value) {
      return value === null || value === undefined ? '—' : `${(Number(value) * 100).toFixed(3)}%`
    },
    variancePercent(row) {
      if (row.variance === null || row.variance === undefined || !Number(row.interest_income_posted)) return '—'
      return `${(Number(row.variance) / Math.abs(Number(row.interest_income_posted)) * 100).toFixed(2)}%`
    },
    date(value) {
      return value ? String(value).slice(0, 10) : '—'
    },
    frequency(value) {
      return ({ 1: 'Annual', 2: 'Semi-annual', 4: 'Quarterly', 6: 'Bi-monthly', 12: 'Monthly' })[value] || 'Frequency pending'
    },
    statusClass(status) {
      return status === 'LOCKED' ? 'badge-green' : status === 'CALCULATED' ? 'badge-blue' : status === 'BLOCKED' ? 'badge-red' : 'badge-yellow'
    },
    reconciliationClass(status) {
      return status === 'WITHIN_TOLERANCE' ? 'badge-green' : status === 'VARIANCE' ? 'badge-red' : 'badge-yellow'
    },
    comparisonClass(status) { return status === 'WITHIN_TOLERANCE' ? 'badge-green' : status === 'NO_REMAINING_DATA' ? 'badge-yellow' : 'badge-red' },
    approvalClass(status) { return status === 'APPROVED' ? 'badge-green' : status === 'DRAFT' ? 'badge-blue' : 'badge-yellow' },
    label(value) { return String(value || 'NOT GENERATED').replaceAll('_', ' ') },
    post(name, id = null) { router.post(this.route(name, id ? { contractEir: id } : {}), {}, { preserveScroll: true }) },
    approve(row) {
      this.approvalModal = { open: true, row, notes: '', processing: false, error: null }
    },
    closeApprovalModal() {
      if (this.approvalModal.processing) return
      this.approvalModal = { open: false, row: null, notes: '', processing: false, error: null }
    },
    confirmApproval() {
      if (!this.approvalModal.row || (this.reviewNoteRequired && !this.approvalModal.notes.trim())) return
      this.approvalModal.processing = true
      this.approvalModal.error = null
      router.post(this.route('eir-schedules.approve', { contractEir: this.approvalModal.row.id }), {
        notes: this.approvalModal.notes.trim() || null,
      }, {
        preserveScroll: true,
        onSuccess: () => this.closeApprovalModalAfterRequest(),
        onError: errors => {
          this.approvalModal.processing = false
          this.approvalModal.error = errors.notes || 'The schedule could not be approved. Review the comparison and try again.'
        },
        onFinish: () => { this.approvalModal.processing = false },
      })
    },
    closeApprovalModalAfterRequest() {
      this.approvalModal = { open: false, row: null, notes: '', processing: false, error: null }
    },
    reconciliationLabel(status) {
      return status === 'WITHIN_TOLERANCE' ? 'Within tolerance' : status === 'VARIANCE' ? 'Variance' : 'Not calculated'
    },
    varianceTextClass(value) {
      if (value === null || value === undefined || Number(value) === 0) return 'text-gray-700'
      return Number(value) > 0 ? 'text-amber-700' : 'text-red-700'
    },
    openTab(tab) {
      router.get(this.route('eir-data.index'), { tab, search: this.search, comparison_status: tab === 'schedules' ? this.comparisonStatus : '' }, { preserveState: false, preserveScroll: true })
    },
    applySearch() {
      router.get(this.route('eir-data.index'), { tab: this.activeTab, search: this.search.trim(), comparison_status: this.activeTab === 'schedules' ? this.comparisonStatus : '' }, { preserveState: false, preserveScroll: true, replace: true })
    },
    clearFilters() { this.search = ''; this.comparisonStatus = ''; this.applySearch() },
    go(url) {
      if (url) router.get(url, {}, { preserveState: false, preserveScroll: true })
    },
  },
}
</script>

<style scoped>
.form-input{@apply block rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-maiic-500 focus:outline-none focus:ring-2 focus:ring-maiic-500}.primary-btn{@apply inline-flex items-center rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700}.secondary-btn{@apply inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50}.th{@apply whitespace-nowrap bg-maiic-700 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white}.td{@apply border-t border-gray-100 px-4 py-3 align-top text-sm text-gray-700}tbody tr:nth-child(even){@apply bg-gray-50}.badge-green{@apply inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800}.badge-blue{@apply inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800}.badge-yellow{@apply inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800}.badge-red{@apply inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800}
</style>
