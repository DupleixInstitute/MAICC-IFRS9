<template>
  <app-layout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800">EIR Calculations</h2>
        <p class="mt-1 text-sm text-gray-600">Calculate each contract's original effective interest rate, then have it approved and locked by someone else</p>
      </div>
    </template>
    <template #actions>
      <button v-if="selected.length && canCalculate" type="button" class="secondary-btn" @click="calculate">Calculate selected ({{ selected.length }})</button>
      <button v-if="readyIds.length && canCalculate && !selected.length" type="button" class="secondary-btn" :disabled="calculatingReady"
              title="Every contract whose readiness reports no blocker and that is not yet calculated" @click="calculateReady">{{ calculatingReady ? 'Calculating...' : `Calculate all ready (${readyIds.length})` }}</button>
      <button
        v-if="Number(approvalSummary.total || 0) > 0 && canCalculate"
        type="button"
        class="primary-btn"
        :disabled="bulkApproving || Number(approvalSummary.eligible || 0) === 0"
        :title="bulkTitle"
        @click="approveAll"
      >
        {{ bulkApproving ? 'Approving...' : `Approve and lock all eligible (${approvalSummary.eligible || 0})` }}
      </button>
    </template>

    <div class="w-full space-y-6">
      <KpiRow :cards="cards" />

      <!-- reopenings requested by one person, awaiting a second (maker-checker) -->
      <div v-if="reopenRequests.length" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <div class="font-semibold text-amber-800">Reopenings awaiting approval ({{ reopenRequests.length }})</div>
        <p class="mt-0.5 text-xs text-gray-600">A locked EIR is reopened only when a person with EIR governance other than the one who asked approves it.</p>
        <ul class="mt-3 divide-y divide-amber-200 dark:divide-amber-700">
          <li v-for="r in reopenRequests" :key="r.id" class="flex flex-wrap items-start justify-between gap-3 py-2 text-sm">
            <div class="min-w-0">
              <div class="font-medium text-gray-900">{{ r.contract_id }}</div>
              <div class="text-gray-700">{{ r.reason }}</div>
              <div class="text-xs text-gray-500">Requested by {{ r.requested_by_name || '-' }}, {{ date(r.requested_at) }}</div>
            </div>
            <div v-if="canReopen" class="flex items-center gap-1.5">
              <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve and reopen" @click="approveReopen(r)"><font-awesome-icon icon="check" /></button>
              <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeReopen(r, 'reject')"><font-awesome-icon icon="times" /></button>
              <button v-else type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeReopen(r, 'withdraw')"><font-awesome-icon icon="ban" /></button>
            </div>
          </li>
        </ul>
      </div>

      <!-- locked EIRs with fee evidence that was not in their inputs: flagged, never reopened by the system -->
      <div v-if="lockReviews.length" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/40">
        <div class="font-semibold text-red-800 dark:text-red-200">Locked EIRs to review ({{ lockReviews.length }})</div>
        <p class="mt-0.5 text-xs text-gray-600 dark:text-slate-400">Fee evidence for these loans was not in the inputs their EIR was locked on. Nothing has been changed: request a reopening to solve the EIR again with its fees, or have a second person clear the flag with the reason.</p>
        <ul class="mt-3 divide-y divide-red-200 dark:divide-red-800">
          <li v-for="r in lockReviews" :key="r.id" class="flex flex-wrap items-start justify-between gap-3 py-2 text-sm">
            <div class="min-w-0">
              <div class="font-medium text-gray-900 dark:text-slate-100">{{ r.contract_id }} {{ r.customer_name }} <span class="text-xs font-normal text-gray-500">locked at {{ pctRate(r.eir_effective_annual) }}{{ r.locked_label ? ' (' + r.locked_label + ')' : '' }}</span></div>
              <div class="text-gray-700 dark:text-slate-300">{{ r.reason }}</div>
              <div class="text-xs text-gray-500">{{ r.source === 'AUTOMATIC' ? 'Found by the system' : 'Raised by a person' }}, {{ date(r.raised_at) }}</div>
            </div>
            <div v-if="canReopen" class="flex items-center gap-1.5">
              <button v-if="r.contract_eir_id" type="button" class="maiic-action maiic-action-edit" title="Request a reopening of this EIR" @click="openReopenModal({ id: r.contract_eir_id, contract_id: r.contract_id })"><font-awesome-icon icon="sync-alt" /></button>
              <button v-if="r.raised_by !== me" type="button" class="maiic-action maiic-action-view" title="Clear the flag (a second person, with the reason)" @click="clearReview(r)"><font-awesome-icon icon="check" /></button>
            </div>
          </li>
        </ul>
      </div>

      <div class="maiic-filterbar">
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" @submit.prevent="applyFilters">
          <label class="block"><span class="maiic-flabel">Contract</span><input v-model="filter.contract_id" class="form-input" placeholder="Contract, account or reference"></label>
          <label class="block"><span class="maiic-flabel">Status</span><select v-model="filter.status" class="form-input">
            <option value="">All statuses</option>
            <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
          </select></label>
          <button type="submit" class="primary-btn justify-center" :disabled="filtering">
            {{ filtering ? 'Applying...' : 'Apply filters' }}
          </button>
          <button type="button" class="secondary-btn justify-center" :disabled="filtering || !hasActiveFilters" @click="clearFilters">Clear filters</button>
        </form>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600">
          <span v-if="contracts.total">Showing {{ contracts.from }} to {{ contracts.to }} of {{ Number(contracts.total).toLocaleString() }} matching contracts.</span>
          <span v-else>No contracts match.</span>
          <span v-if="hasActiveFilters" class="badge-blue">Filters active</span>
          <span v-if="Number(approvalSummary.total || 0) > 0" class="text-xs text-gray-500">{{ bulkTitle }}</span>
        </div>
      </div>


      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="th"><input type="checkbox" :checked="allSelected" @change="toggleAll"></th>
                <th class="th">Contract</th>
                <th class="th">Status</th>
                <th class="th text-right">Periodic EIR</th>
                <th class="th text-right">Effective annual EIR</th>
                <th class="th">Evidence</th>
                <th class="th">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="contract in contracts.data" :key="contract.id">
                <td class="td"><input v-if="!contract.locked_at" type="checkbox" :value="contract.contract_id" v-model="selected"></td>
                <td class="td font-medium">
                  {{ contract.contract_id }}
                  <div class="text-xs text-gray-500">{{ contract.currency || '' }}</div>
                </td>
                <td class="td">
                  <span :class="statusClass(contract.calculation_status)">{{ statusLabel(contract.calculation_status) }}</span>
                  <div v-if="contract.calculation_error" class="mt-1 text-xs text-red-700 max-w-sm">{{ contract.calculation_error }}</div>
                </td>
                <td class="td text-right tabular-nums">{{ percent(contract.eir_period) }}</td>
                <td class="td text-right tabular-nums font-medium">{{ percent(contract.eir_effective_annual) }}</td>
                <td class="td text-xs text-gray-600">
                  <div v-if="contract.calculated_at">Calculated {{ date(contract.calculated_at) }}</div>
                  <div v-if="contract.solver_method">{{ contract.solver_method }} · {{ contract.solver_iterations }} iterations</div>
                  <div v-if="contract.locked_at">Locked {{ date(contract.locked_at) }}</div>
                </td>
                <td class="td">
                  <div class="flex items-center gap-1.5">
                    <button v-if="!contract.locked_at && canCalculate" type="button" @click="recalculate(contract)" class="maiic-action maiic-action-neutral"
                            :title="contract.calculation_status === 'PENDING' ? 'Calculate' : contract.calculation_status === 'BLOCKED' ? 'Retry calculation' : 'Recalculate'">
                      <font-awesome-icon icon="calculator" />
                    </button>
                    <button v-if="contract.calculation_status === 'CALCULATED' && canCalculate" type="button" @click="approve(contract)" class="maiic-action maiic-action-view" title="Approve and lock">
                      <font-awesome-icon icon="lock" />
                    </button>
                    <span v-if="contract.locked_at && reopenRequested(contract)" class="text-xs text-amber-700">Reopening requested, awaiting approval</span>
                    <button v-else-if="contract.locked_at && canReopen" type="button" @click="openReopenModal(contract)" class="maiic-action maiic-action-edit" title="Request reopening of the locked EIR">
                      <font-awesome-icon icon="lock-open" />
                    </button>
                    <span v-else-if="contract.locked_at" class="text-xs text-gray-500">Locked; a person with EIR governance can request its reopening</span>
                  </div>
                </td>
              </tr>
              <tr v-if="!contracts.data.length">
                <td colspan="7" class="p-10 text-center text-sm text-gray-500">
                  <template v-if="hasActiveFilters">No contracts match these filters. Clear the filters to see every contract.</template>
                  <template v-else>No contracts are loaded yet. Load the contract master through EIR Data Intake first.</template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="contracts.links && contracts.links.length > 3" class="px-4 pb-4 border-t">
          <Pagination :links="contracts.links" />
        </div>
      </div>

      <div v-if="Object.keys(errors).length" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <font-awesome-icon icon="exclamation-circle" class="mt-0.5" />
        <div><div class="font-semibold">The action could not be completed</div><div>{{ Object.values(errors)[0] }}</div></div>
      </div>
    </div>

    <div v-if="reopenModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 px-4" @click.self="closeReopenModal">
      <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="reopen-eir-title">
        <div class="border-b border-gray-200 px-6 py-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 id="reopen-eir-title" class="text-lg font-semibold text-gray-900">Request reopening of a locked original EIR</h3>
              <p class="mt-1 text-sm text-gray-500">Contract {{ reopenModal.contract?.contract_id }}</p>
            </div>
            <button class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" :disabled="reopenModal.processing" @click="closeReopenModal">&#10005;</button>
          </div>
        </div>
        <div class="space-y-4 px-6 py-5">
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            A different person with EIR governance approves the request. On approval the locked calculation is archived, the revenue-recognition rows are superseded and the discounted ECL is marked stale; a month the EIR reached that is locked refuses it. The EIR must then be recalculated and independently approved.
          </div>
          <div>
            <label for="reopen-reason" class="text-sm font-semibold text-gray-800">Reason for reopening <span class="text-red-600">*</span></label>
            <textarea id="reopen-reason" v-model="reopenModal.reason" rows="4" maxlength="500" class="form-input mt-2" placeholder="Describe the source-data error or controlled correction."></textarea>
            <div class="mt-1 flex justify-between text-xs"><span class="text-gray-500">At least 10 characters; recorded in audit history.</span><span class="text-gray-400">{{ reopenModal.reason.length }}/500</span></div>
            <p v-if="reopenModal.error" class="mt-2 text-sm text-red-700">{{ reopenModal.error }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4">
          <button class="secondary-btn" :disabled="reopenModal.processing" @click="closeReopenModal">Cancel</button>
          <button class="primary-btn bg-amber-700 hover:bg-amber-800" :disabled="reopenModal.processing || reopenModal.reason.trim().length < 10" @click="confirmReopen">
            {{ reopenModal.processing ? 'Requesting...' : 'Request reopening' }}
          </button>
        </div>
      </div>
    </div>

    <teleport to="head"><title>EIR Calculations</title></teleport>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

export default {
  components: { AppLayout, Link, KpiRow },
  props: {
    contracts: Object,
    filters: Object,
    summary: Object,
    approvalSummary: { type: Object, default: () => ({}) },
    canReopen: { type: Boolean, default: false },
    canCalculate: { type: Boolean, default: false },
    readyIds: { type: Array, default: () => [] },
    reopenRequests: { type: Array, default: () => [] },
    lockReviews: { type: Array, default: () => [] },
    me: { type: Number, default: null },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return { calculatingReady: false,
      statuses: ['PENDING', 'REOPENED', 'BLOCKED', 'CALCULATED', 'LOCKED'],
      selected: [],
      bulkApproving: false,
      filtering: false,
      reopenModal: { open: false, contract: null, reason: '', processing: false, error: null },
      filter: {
        status: this.filters.status || '',
        contract_id: this.filters.contract_id || '',
      },
    }
  },
  computed: {
    cards() {
      const accents = { PENDING: '#d97706', REOPENED: '#c69b2c', BLOCKED: '#dc2626', CALCULATED: '#0f766e', LOCKED: '#15803d' }
      // the workflow state of each contract; the Blockers tab says why a pending one cannot be calculated yet
      const subs = { PENDING: 'not calculated yet (see Blockers)', REOPENED: 'reopened for recalculation', BLOCKED: 'stopped by a calculation run', CALCULATED: 'awaiting approval', LOCKED: 'approved and locked' }
      return this.statuses.map(s => ({ label: this.statusLabel(s), value: Number(this.summary[s]?.contract_count || 0), sub: subs[s] || 'contracts', accent: accents[s] }))
    },
    bulkTitle() {
      const a = this.approvalSummary
      let t = `${a.eligible || 0} calculated contract(s) can be approved across all pages.`
      if (a.admin_override) t += ' Administrator override is active.'
      else if (a.own || a.missing_maker) t += ` ${a.own || 0} calculated by you and ${a.missing_maker || 0} without maker evidence will be skipped.`
      return t
    },
    selectable() {
      return this.contracts.data.filter(c => !c.locked_at)
    },
    allSelected() {
      return this.selectable.length > 0 && this.selectable.every(c => this.selected.includes(c.contract_id))
    },
    hasActiveFilters() {
      return Boolean(this.filter.status || this.filter.contract_id.trim())
    },
  },
  methods: {
    statusLabel(s) {
      return { PENDING: 'Pending', REOPENED: 'Reopened', BLOCKED: 'Blocked', CALCULATED: 'Calculated', LOCKED: 'Locked' }[s] || s
    },
    percent(v) {
      return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(4) + '%'
    },
    date(v) {
      return new Date(v).toLocaleString()
    },
    statusClass(s) {
      return s === 'LOCKED' ? 'badge-green' : s === 'CALCULATED' ? 'badge-blue' : s === 'BLOCKED' ? 'badge-red' : 'badge-yellow'
    },
    toggleAll(e) {
      this.selected = e.target.checked ? this.selectable.map(c => c.contract_id) : []
    },
    applyFilters() {
      this.filtering = true
      router.get(this.route('eir-calculations.index'), {
        status: this.filter.status,
        contract_id: this.filter.contract_id.trim(),
      }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        onFinish: () => { this.filtering = false },
      })
    },
    clearFilters() {
      this.filter = { status: '', contract_id: '' }
      this.applyFilters()
    },
    go(url) {
      if (url) router.get(url, {}, { preserveState: false, preserveScroll: true })
    },
    calculateReady() {
      this.calculatingReady = true
      this.$inertia.post(this.route('eir-calculations.calculate'), { contract_ids: this.readyIds }, { preserveScroll: true, onFinish: () => { this.calculatingReady = false } })
    },
    calculate() {
      this.$inertia.post(this.route('eir-calculations.calculate'), { contract_ids: this.selected }, {
        onSuccess: () => { this.selected = [] },
      })
    },
    recalculate(contract) {
      this.$inertia.post(this.route('eir-calculations.calculate'), { contract_ids: [contract.contract_id] }, { preserveScroll: true })
    },
    reopenRequested(contract) {
      return this.reopenRequests.some(r => r.contract_id === contract.contract_id)
    },
    async approveReopen(r) {
      if (!(await confirmDialog({
        title: `Reopen the locked EIR of ${r.contract_id}?`,
        message: `Requested by ${r.requested_by_name || 'another user'}: ${r.reason}. The locked calculation is archived, the revenue rows are superseded and the discounted ECL is marked stale.`,
        confirmLabel: 'Approve and reopen',
        tone: 'danger',
      }))) return
      this.$inertia.post(this.route('eir-calculations.reopen.approve', r.id), {}, { preserveScroll: true })
    },
    async clearReview(r) {
      const reason = await promptReason({ title: `Clear the review flag on ${r.contract_id}`, message: 'Say why the locked EIR stands: for example the fee was considered, or the posting is not the fee of this loan. It is kept in the audit trail.', confirmLabel: 'Clear the flag' })
      if (!reason) return
      this.$inertia.post(this.route('eir-calculations.lock-review.clear', r.id), { reason }, { preserveScroll: true })
    },
    pctRate(v) {
      return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%'
    },
    async closeReopen(r, action) {
      const words = { reject: 'Reject', withdraw: 'Withdraw' }
      const reason = await promptReason({ title: `${words[action]} the reopening of ${r.contract_id}`, message: 'Give the reason; it is kept with the request and in the audit trail.', confirmLabel: words[action] })
      if (!reason) return
      this.$inertia.post(this.route('eir-calculations.reopen.close', r.id), { action, reason }, { preserveScroll: true })
    },
    openReopenModal(contract) {
      this.reopenModal = { open: true, contract, reason: '', processing: false, error: null }
    },
    closeReopenModal() {
      if (this.reopenModal.processing) return
      this.reopenModal = { open: false, contract: null, reason: '', processing: false, error: null }
    },
    confirmReopen() {
      if (!this.reopenModal.contract || this.reopenModal.reason.trim().length < 10) return
      this.reopenModal.processing = true
      this.reopenModal.error = null
      this.$inertia.post(this.route('eir-calculations.reopen', this.reopenModal.contract.id), {
        reason: this.reopenModal.reason.trim(),
      }, {
        preserveScroll: true,
        onSuccess: () => { this.reopenModal = { open: false, contract: null, reason: '', processing: false, error: null } },
        onError: errors => { this.reopenModal.error = errors.reason || 'The reopening could not be requested.' },
        onFinish: () => { this.reopenModal.processing = false },
      })
    },
    async approve(c) {
      if (!(await confirmDialog({
        title: `Approve and lock ${c.contract_id}?`,
        message: 'The original EIR is locked. It can be reopened only when one person with EIR governance requests it with a reason and a different person approves it.',
        confirmLabel: 'Approve and lock',
      }))) return
      this.$inertia.post(this.route('eir-calculations.approve', c.id))
    },
    async approveAll() {
      const eligible = Number(this.approvalSummary.eligible || 0)
      if (!eligible) return

      if (!(await confirmDialog({
        title: `Approve and lock ${eligible} EIRs?`,
        message: `All ${eligible} eligible calculated EIRs across every page are locked permanently. This cannot be undone.`,
        confirmLabel: 'Approve and lock all',
        tone: 'danger',
      }))) return

      this.bulkApproving = true
      this.$inertia.post(this.route('eir-calculations.approve-all'), {}, {
        preserveScroll: true,
        onFinish: () => { this.bulkApproving = false },
      })
    },
  },
}
</script>

<style scoped>
.form-input{@apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-maiic-500}
</style>
