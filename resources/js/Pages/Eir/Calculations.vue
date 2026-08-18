<template>
  <app-layout>
    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <Link :href="route('eir-data.index')" class="hover:text-maiic-700">EIR Data</Link>
          <span>/</span>
          <span class="font-medium text-maiic-700">Calculations</span>
        </div>
        <h2 class="font-semibold text-xl text-gray-800">EIR Calculations</h2>
        <p class="mt-1 text-sm text-gray-600">Calculate, independently approve, and lock each contract's original effective interest rate</p>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="s in statuses" :key="s" class="bg-white border border-gray-200 shadow-sm rounded-lg p-4">
          <div class="text-2xl font-bold text-gray-800">{{ summary[s]?.contract_count || 0 }}</div>
          <div class="text-xs text-gray-500">{{ s }} contracts</div>
        </div>
      </div>

      <div
        v-if="Number(approvalSummary.total || 0) > 0"
        class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
      >
        <div>
          <h3 class="font-semibold text-gray-800">Bulk approval</h3>
          <p class="mt-1 text-sm text-gray-600">
            {{ approvalSummary.eligible || 0 }} calculated contract(s) are eligible across all pages.
            <span v-if="approvalSummary.admin_override" class="font-medium text-maiic-700">
              Administrator override is active.
            </span>
            <span v-else-if="approvalSummary.own || approvalSummary.missing_maker">
              {{ approvalSummary.own || 0 }} calculated by you and {{ approvalSummary.missing_maker || 0 }} without maker evidence will be skipped.
            </span>
          </p>
        </div>
        <button
          type="button"
          class="primary-btn justify-center disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="bulkApproving || Number(approvalSummary.eligible || 0) === 0"
          @click="approveAll"
        >
          {{ bulkApproving ? 'Approving...' : `Approve & lock all eligible (${approvalSummary.eligible || 0})` }}
        </button>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3" @submit.prevent="applyFilters">
          <input v-model="filter.contract_id" class="form-input" placeholder="Contract, account or reference">
          <select v-model="filter.status" class="form-input">
            <option value="">All statuses</option>
            <option v-for="s in statuses" :key="s">{{ s }}</option>
          </select>
          <button type="submit" class="primary-btn justify-center" :disabled="filtering">
            {{ filtering ? 'Applying...' : 'Apply filters' }}
          </button>
          <button type="button" class="secondary-btn justify-center" :disabled="filtering || !hasActiveFilters" @click="clearFilters">Clear filters</button>
        </form>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600">
          <span>Showing {{ contracts.from || 0 }}–{{ contracts.to || 0 }} of {{ contracts.total || 0 }} matching records.</span>
          <span v-if="hasActiveFilters" class="badge-blue">Filters active</span>
        </div>
      </div>

      <div v-if="selected.length" class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 flex items-center justify-between">
        <span class="font-medium text-maiic-900">{{ selected.length }} contract(s) selected</span>
        <button @click="calculate" class="primary-btn">Calculate selected</button>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="th"><input type="checkbox" :checked="allSelected" @change="toggleAll"></th>
                <th class="th">Contract</th>
                <th class="th">Status</th>
                <th class="th">Periodic EIR</th>
                <th class="th">Effective annual EIR</th>
                <th class="th">Evidence</th>
                <th class="th">Action</th>
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
                  <span :class="statusClass(contract.calculation_status)">{{ contract.calculation_status }}</span>
                  <div v-if="contract.calculation_error" class="mt-1 text-xs text-red-700 max-w-sm">{{ contract.calculation_error }}</div>
                </td>
                <td class="td">{{ percent(contract.eir_period) }}</td>
                <td class="td font-medium">{{ percent(contract.eir_effective_annual) }}</td>
                <td class="td text-xs text-gray-600">
                  <div v-if="contract.calculated_at">Calculated {{ date(contract.calculated_at) }}</div>
                  <div v-if="contract.solver_method">{{ contract.solver_method }} · {{ contract.solver_iterations }} iterations</div>
                  <div v-if="contract.locked_at">Locked {{ date(contract.locked_at) }}</div>
                </td>
                <td class="td">
                  <button v-if="contract.calculation_status === 'CALCULATED'" @click="approve(contract)" class="primary-btn">Approve & lock</button>
                  <span v-else-if="contract.locked_at" class="text-xs text-gray-500">Final</span>
                </td>
              </tr>
              <tr v-if="!contracts.data.length">
                <td colspan="7" class="p-8 text-center text-gray-500">No contracts match these filters.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="p-4 flex flex-wrap gap-2 border-t">
          <button
            v-for="link in contracts.links"
            :key="link.label"
            v-html="link.label"
            :disabled="!link.url"
            @click="go(link.url)"
            class="px-3 py-1 border rounded text-sm disabled:opacity-40"
            :class="link.active ? 'bg-maiic-600 text-white' : 'bg-white text-gray-700'"
          ></button>
        </div>
      </div>

      <p v-if="Object.keys(errors).length" class="text-sm text-red-700">{{ Object.values(errors)[0] }}</p>
    </div>

    <teleport to="head"><title>EIR Calculations</title></teleport>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: {
    contracts: Object,
    filters: Object,
    summary: Object,
    approvalSummary: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      statuses: ['PENDING', 'BLOCKED', 'CALCULATED', 'LOCKED'],
      selected: [],
      bulkApproving: false,
      filtering: false,
      filter: {
        status: this.filters.status || '',
        contract_id: this.filters.contract_id || '',
      },
    }
  },
  computed: {
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
    percent(v) {
      return v === null || v === undefined ? '—' : (Number(v) * 100).toFixed(4) + '%'
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
    approve(c) {
      if (confirm(`Approve and permanently lock the original EIR for ${c.contract_id}?`)) {
        this.$inertia.post(this.route('eir-calculations.approve', c.id))
      }
    },
    approveAll() {
      const eligible = Number(this.approvalSummary.eligible || 0)
      if (!eligible) return

      const confirmed = confirm(
        `Approve and permanently lock all ${eligible} eligible calculated EIRs across every page? This cannot be undone.`
      )
      if (!confirmed) return

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
.form-input{@apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-maiic-500}.primary-btn{@apply inline-flex px-4 py-2 rounded-md text-sm font-medium text-white bg-maiic-600 hover:bg-maiic-700 disabled:cursor-not-allowed disabled:opacity-50}.secondary-btn{@apply inline-flex px-4 py-2 rounded-md border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50}.th{@apply px-4 py-3 bg-maiic-700 text-left text-[11px] font-bold text-white uppercase tracking-wider whitespace-nowrap}.td{@apply px-4 py-2.5 align-top text-sm border-t border-gray-100}.badge-green{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-maiic-100 text-maiic-800}.badge-blue{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-800}.badge-yellow{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800}.badge-red{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800}
</style>
