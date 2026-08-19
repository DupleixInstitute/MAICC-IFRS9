<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">GL Reconciliation</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">EIR to GL Reconciliation</h2>
          <p class="mt-1 text-sm text-gray-600">Why calculated interest income differs from what the ledger posted</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('eir-data.index', { tab: 'gl' })" class="secondary-btn">GL Postings</Link>
          <Link :href="route('eir-calculations.index')" class="primary-btn">EIR Calculations</Link>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-5">
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="apply">
          <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Reporting period</label>
            <select v-model="form.period" class="form-input md:w-48" @change="apply">
              <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Portfolio</label>
            <select v-model="form.portfolio" class="form-input md:w-48" @change="apply">
              <option value="">All portfolios</option>
              <option v-for="p in portfolios" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <button type="submit" class="secondary-btn">Apply</button>
        </form>
      </div>

      <div v-if="!period" class="rounded-lg border border-gray-200 bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
        No GL interest postings have been loaded yet. Use EIR Data Intake to load an interest posting extract.
      </div>

      <template v-else>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-2xl font-bold" :class="card.tone">{{ card.value }}</div>
            <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.label }}</div>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Variance bridge — {{ period }}</h3>
            <p class="mt-1 text-xs text-gray-500">
              Both sides accrue monthly, so the difference resolves into three terms that sum to the variance exactly.
              The <strong>base effect</strong> is the gap between the balance the engine amortises and the balance the
              ledger accrued on &mdash; derived from the posting itself rather than assumed, so a ledger that amortises
              and one that posts flat on original principal both decompose cleanly. The <strong>rate effect</strong> is
              the yield uplift from fees integral to the EIR. The <strong>impairment effect</strong> is Stage&nbsp;3
              accruing on the amortised cost net of the loss allowance, which is a correct measurement difference
              rather than an error.
            </p>
          </div>
          <table class="min-w-full">
            <tbody>
              <tr v-for="line in bridgeLines" :key="line.label" :class="line.emphasis ? 'bg-gray-50 font-semibold' : ''">
                <td class="td" :class="line.indent ? 'pl-8 text-gray-600' : 'text-gray-900'">
                  {{ line.label }}
                  <span v-if="line.note" class="ml-2 text-xs font-normal text-gray-500">{{ line.note }}</span>
                </td>
                <td class="td text-right tabular-nums" :class="line.tone">{{ money(line.value) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-if="Math.abs(bridge.unexplained) < 1" class="border-t border-gray-200 px-4 py-3 text-xs text-emerald-700">
            The three effects account for the variance in full — no unexplained residual.
          </p>
          <p v-else class="border-t border-gray-200 px-4 py-3 text-xs text-amber-700">
            {{ money(bridge.unexplained) }} is not explained by any of the three effects — for at least one facility the
            ledger did not post at the contractual rate at all. Review the rows below.
          </p>
        </div>

        <div v-if="Math.abs(bridge.rate_effect) < 100 && bridge.gl_matched !== 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
          <h4 class="text-sm font-semibold text-amber-900">The rate effect is effectively nil</h4>
          <p class="mt-1 text-xs text-amber-800">
            A solved EIR carrying no integral fees de-compounds to exactly the contractual monthly rate, so the entire
            variance is a balance difference. Until fee lines are classified as integral, this reconciliation measures
            the ledger's accrual basis rather than any EIR yield uplift.
          </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Facilities</h3>
            <p class="text-xs text-gray-500">
              Sorted by absolute variance. Tolerance is ±{{ summary.tolerance_percent }}% of the posted amount.
            </p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr>
                  <th class="th">Contract</th><th class="th">Basis</th>
                  <th class="th text-right">GL posted</th><th class="th text-right">EIR accrued</th>
                  <th class="th text-right">Variance</th><th class="th text-right">GL implied base</th>
                  <th class="th text-right">Base effect</th><th class="th text-right">Rate effect</th>
                  <th class="th text-right">Impairment</th><th class="th">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in sortedRows" :key="r.contract_id">
                  <td class="td">
                    <div class="font-semibold text-gray-900">{{ r.contract_id }}</div>
                    <div class="text-xs text-gray-500">{{ r.portfolio || '—' }}{{ r.gl_account_code ? ' · ' + r.gl_account_code : '' }}</div>
                  </td>
                  <td class="td text-xs text-gray-600">
                    <div v-if="r.opening_gross !== null">Opening {{ money(r.opening_gross) }}</div>
                    <div v-if="r.drawn_amount !== null">Drawn {{ money(r.drawn_amount) }}</div>
                    <div v-if="r.interest_basis">{{ r.interest_basis }}</div>
                  </td>
                  <td class="td text-right tabular-nums">{{ money(r.gl_posted) }}</td>
                  <td class="td text-right tabular-nums">{{ r.eir_accrued === null ? '—' : money(r.eir_accrued) }}</td>
                  <td class="td text-right tabular-nums" :class="toneFor(r.variance)">
                    {{ r.variance === null ? '—' : money(r.variance) }}
                    <div v-if="r.variance_percent !== null" class="text-xs text-gray-500">{{ r.variance_percent }}%</div>
                  </td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.gl_implied_base === null ? '—' : money(r.gl_implied_base) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.base_effect === null ? '—' : money(r.base_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.rate_effect === null ? '—' : money(r.rate_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.impairment_effect === null ? '—' : money(r.impairment_effect) }}</td>
                  <td class="td"><span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span></td>
                </tr>
                <tr v-if="!rows.length">
                  <td colspan="10" class="p-10 text-center text-sm text-gray-500">No GL postings for this period and portfolio.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  period: { type: String, default: null },
  periods: { type: Array, default: () => [] },
  portfolios: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  rows: { type: Array, default: () => [] },
  bridge: { type: Object, required: true },
  summary: { type: Object, required: true },
})

const form = reactive({
  period: props.filters.period || props.period || '',
  portfolio: props.filters.portfolio || '',
})

const apply = () => router.get(route('eir-reconciliation.index'),
  { period: form.period, portfolio: form.portfolio },
  { preserveState: true, preserveScroll: true, replace: true })

const money = (v) => {
  if (v === null || v === undefined) return '—'
  const n = Number(v)
  return (n < 0 ? '(' : '') + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (n < 0 ? ')' : '')
}

const cards = computed(() => [
  { label: 'GL interest posted', value: money(props.bridge.gl_total), tone: 'text-gray-900' },
  { label: 'EIR interest calculated', value: money(props.bridge.eir_total), tone: 'text-gray-900' },
  { label: 'Net variance', value: money(props.bridge.net_variance), tone: toneFor(props.bridge.net_variance) },
  { label: 'Rows within tolerance', value: `${props.summary.within_tolerance} of ${props.summary.posting_rows - props.summary.not_calculated}`, tone: 'text-gray-900' },
])

const bridgeLines = computed(() => [
  { label: 'GL interest posted, all rows', value: props.bridge.gl_total, emphasis: true },
  { label: 'Postings with no calculated counterpart', value: -props.bridge.gl_without_counterpart, indent: true,
    note: `${props.summary.not_calculated} row(s)`, tone: 'text-gray-600' },
  { label: 'GL interest on matched facilities', value: props.bridge.gl_matched, emphasis: true },
  { label: 'Base effect — amortised balance vs the balance GL accrued on', value: props.bridge.base_effect, indent: true, tone: toneFor(props.bridge.base_effect) },
  { label: 'Rate effect — integral fee yield uplift', value: props.bridge.rate_effect, indent: true, tone: toneFor(props.bridge.rate_effect) },
  { label: 'Impairment effect — Stage 3 accrued on net', value: props.bridge.impairment_effect, indent: true, tone: toneFor(props.bridge.impairment_effect) },
  { label: 'Unexplained', value: props.bridge.unexplained, indent: true, tone: toneFor(props.bridge.unexplained) },
  { label: 'EIR interest calculated', value: props.bridge.eir_total, emphasis: true },
])

const sortedRows = computed(() => [...props.rows].sort((a, b) => {
  if (a.variance === null) return 1
  if (b.variance === null) return -1
  return Math.abs(b.variance) - Math.abs(a.variance)
}))

function toneFor (v) {
  if (v === null || v === undefined || Math.abs(Number(v)) < 1) return 'text-gray-900'
  return Number(v) < 0 ? 'text-rose-700' : 'text-emerald-700'
}

const statusLabel = (s) => ({
  WITHIN_TOLERANCE: 'Within tolerance', VARIANCE: 'Variance',
  NOT_CALCULATED: 'Not calculated', NO_CONTRACT: 'No EIR contract',
}[s] || s)

const statusClass = (s) => {
  const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '
  if (s === 'WITHIN_TOLERANCE') return base + 'bg-emerald-100 text-emerald-800'
  if (s === 'VARIANCE') return base + 'bg-rose-100 text-rose-800'
  return base + 'bg-amber-100 text-amber-800'
}
</script>
