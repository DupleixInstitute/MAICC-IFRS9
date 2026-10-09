<template>
  <app-layout>
    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>EIR &amp; Revenue Recognition</span>
          <span>/</span>
          <span class="font-medium text-maiic-700">Calculations</span>
        </div>
        <h2 class="font-semibold text-xl text-gray-800">EIR Calculations</h2>
        <p class="mt-1 text-sm text-gray-600">Calculate each contract's original effective interest rate, then have it approved and locked by someone else</p>
      </div>
    </template>
    <template #actions>
      <button v-if="selected.length" type="button" class="secondary-btn" @click="calculate">Calculate selected ({{ selected.length }})</button>
      <button
        v-if="Number(approvalSummary.total || 0) > 0"
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
                    <button v-if="!contract.locked_at" type="button" @click="recalculate(contract)" class="maiic-action maiic-action-neutral"
                            :title="contract.calculation_status === 'PENDING' ? 'Calculate' : contract.calculation_status === 'BLOCKED' ? 'Retry calculation' : 'Recalculate'">
                      <font-awesome-icon icon="calculator" />
                    </button>
                    <button v-if="contract.calculation_status === 'CALCULATED'" type="button" @click="approve(contract)" class="maiic-action maiic-action-view" title="Approve and lock">
                      <font-awesome-icon icon="lock" />
                    </button>
                    <button v-if="contract.locked_at && canReopen" type="button" @click="openReopenModal(contract)" class="maiic-action maiic-action-edit" title="Reopen locked EIR">
                      <font-awesome-icon icon="lock-open" />
                    </button>
                    <span v-else-if="contract.locked_at" class="text-xs text-gray-500">Locked, an administrator must reopen it</span>
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
              <h3 id="reopen-eir-title" class="text-lg font-semibold text-gray-900">Reopen locked original EIR</h3>
              <p class="mt-1 text-sm text-gray-500">Contract {{ reopenModal.contract?.contract_id }}</p>
            </div>
            <button class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" :disabled="reopenModal.processing" @click="closeReopenModal">&#10005;</button>
          </div>
        </div>
        <div class="space-y-4 px-6 py-5">
          <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            The locked calculation will be archived. Revenue-recognition rows will be superseded and discounted ECL will be marked stale. The EIR must then be recalculated and independently approved.
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
            {{ reopenModal.processing ? 'Reopening...' : 'Reopen and invalidate results' }}
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

export default {
  components: { AppLayout, Link, KpiRow },
  props: {
    contracts: Object,
    filters: Object,
    summary: Object,
    approvalSummary: { type: Object, default: () => ({}) },
    canReopen: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return {
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
      return this.statuses.map(s => ({ label: this.statusLabel(s), value: Number(this.summary[s]?.contract_count || 0), sub: 'contracts', accent: accents[s] }))
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
    calculate() {
      this.$inertia.post(this.route('eir-calculations.calculate'), { contract_ids: this.selected }, {
        onSuccess: () => { this.selected = [] },
      })
    },
    recalculate(contract) {
      this.$inertia.post(this.route('eir-calculations.calculate'), { contract_ids: [contract.contract_id] }, { preserveScroll: true })
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
        onError: errors => { this.reopenModal.error = errors.reason || 'The locked EIR could not be reopened.' },
        onFinish: () => { this.reopenModal.processing = false },
      })
    },
    async approve(c) {
      if (!(await confirmDialog({
        title: `Approve and lock ${c.contract_id}?`,
        message: 'The original EIR is locked permanently. Only an administrator can reopen it.',
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
