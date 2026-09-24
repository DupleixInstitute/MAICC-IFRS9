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
          <a v-if="period" :href="downloadUrl('xlsx')" class="secondary-btn">Download Excel</a>
          <a v-if="period" :href="downloadUrl('pdf')" class="secondary-btn">Download PDF</a>
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

      <!-- The revenue run. Without it there is nothing to reconcile against:
           this page joins GL postings to amortisation rows, so an engine that
           has never run reports every posting as "not calculated". -->
      <div v-if="period" class="rounded-lg border p-4 shadow-sm" :class="chainComplete ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50'">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <h3 class="font-semibold" :class="chainComplete ? 'text-gray-900' : 'text-amber-900'">
              {{ chainComplete ? 'Amortised-cost roll-forward' : 'No calculated interest to compare against' }}
            </h3>
            <p class="mt-1 text-xs" :class="chainComplete ? 'text-gray-500' : 'text-amber-800'">
              <template v-if="chainComplete">
                {{ revenueReadiness.rows_for_period }} amortisation row(s) exist for {{ period }}, from
                {{ revenueReadiness.locked_contracts }} contract(s) with an approved and locked EIR. Re-running leaves
                calculated periods unchanged.
              </template>
              <template v-else>
                {{ revenueReadiness.missing_periods.length }} period(s) up to {{ period }} have no amortisation rows,
                starting at {{ revenueReadiness.missing_periods[0] }}. Each opening balance is the prior period's
                closing, so the catch-up runs every period in order from {{ revenueReadiness.first_period }}.
              </template>
            </p>
            <p v-if="!revenueReadiness.locked_contracts" class="mt-1 text-xs font-medium text-amber-900">
              No contract has a locked EIR yet, so a run would produce nothing. Solve and approve them on EIR
              Calculations first.
            </p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-2">
            <button type="button" class="secondary-btn" :disabled="running || !revenueReadiness.locked_contracts"
                    @click="runRevenue('period')">
              Run {{ period }} only
            </button>
            <button type="button" class="primary-btn" :disabled="running || !revenueReadiness.locked_contracts"
                    @click="runRevenue('catch_up')">
              {{ running ? 'Running…' : `Run every period to ${period}` }}
            </button>
          </div>
        </div>

        <div v-if="revenueRun" class="mt-4 rounded-md border p-3 text-xs"
             :class="revenueRun.status === 'REFUSED' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-gray-200 bg-gray-50 text-gray-700'">
          <div v-if="revenueRun.status === 'REFUSED'">{{ revenueRun.message }}</div>
          <template v-else>
            <div class="font-semibold text-gray-900">
              Ran {{ revenueRun.periods_run.length }} period(s): {{ revenueRun.periods_run.join(', ') }}
            </div>
            <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1">
              <span>Rows created: <strong>{{ revenueRun.totals.created }}</strong></span>
              <span>Already calculated: <strong>{{ revenueRun.totals.unchanged }}</strong></span>
              <span>Blocked: <strong>{{ revenueRun.totals.blocked }}</strong></span>
              <span>Cash taken from the schedule: <strong>{{ revenueRun.totals.cash_derived_from_schedule }}</strong></span>
              <span v-if="revenueRun.totals.unclassified_cash">
                Unclassified cash: <strong>{{ money(revenueRun.totals.unclassified_cash) }}</strong>
              </span>
            </div>
            <div v-if="Object.keys(revenueRun.blocked_contracts || {}).length" class="mt-2">
              <div class="font-semibold text-amber-900">Contracts that produced no row:</div>
              <div v-for="(reason, key) in revenueRun.blocked_contracts" :key="key" class="mt-0.5">
                <span class="font-medium text-gray-900">{{ key }}</span> — {{ reason }}
              </div>
              <div v-if="revenueRun.blocked_truncated" class="mt-1 text-gray-500">
                and {{ revenueRun.blocked_truncated }} more.
              </div>
            </div>
          </template>
        </div>
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
              The difference resolves into four terms that sum to the variance exactly. The <strong>base effect</strong>
              is the ledger against the interest the contract charges: the prior month-end outstanding balance
              times the annual rate times the days in the month, on the governed day count. Every row's share of it
              carries a named cause. The <strong>carrying amount effect</strong> is that same charge on the balance the
              engine amortises instead of the balance the loan book reports. The <strong>rate effect</strong> is
              accruing at the EIR rather than at the contractual rate on the same balance: the uplift from fees
              integral to the EIR, and any difference between the two conventions. The <strong>impairment
              effect</strong> is Stage&nbsp;3 accruing on the amortised cost net of the loss allowance, which is a
              correct measurement difference rather than an error.
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
            The four effects account for the variance in full &mdash; no unexplained residual.
          </p>
          <p v-else class="border-t border-gray-200 px-4 py-3 text-xs text-amber-700">
            {{ money(bridge.unexplained) }} is not explained by any of the four effects. For at least one facility the
            expected interest could not be worked out, so the difference cannot be split. Review the rows below and
            read the cause on each.
          </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <h3 class="font-semibold text-gray-900">Why the ledger and the contract differ</h3>
          <p class="mt-1 text-xs text-gray-500">
            Every row outside the band carries one named cause. {{ summary.expected_agrees }} of {{ summary.rows }}
            row(s) agree inside the band, {{ summary.expected_explained }} are explained by a named cause and
            {{ summary.expected_unexplained }} are still unexplained.
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span v-for="(count, cause) in summary.causes" :key="cause" :class="causeClass(cause)">
              {{ causeLabel(cause) }}: {{ count }}
            </span>
          </div>
        </div>

        <div v-if="Math.abs(bridge.rate_effect) < 100 && bridge.gl_matched !== 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
          <h4 class="text-sm font-semibold text-amber-900">The rate effect is effectively nil</h4>
          <p class="mt-1 text-xs text-amber-800">
            A solved EIR carrying no integral fees accrues at very nearly the contractual rate, so the whole variance
            is a balance or a convention difference. Until fee lines are classified as integral, this reconciliation
            measures the ledger's basis rather than any EIR yield uplift.
          </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Facilities</h3>
            <p class="text-xs text-gray-500">
              Sorted by absolute variance. Expected interest is the prior month-end balance times the annual rate
              times the days charged, on the governed day count ({{ summary.day_count || 'not approved' }}). A row
              agrees when the difference is inside {{ summary.tolerance_percent }} percent of the amount posted,
              with a floor of {{ money(summary.tolerance_floor) }}.
            </p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr>
                  <th class="th">Contract</th><th class="th">Basis</th>
                  <th class="th text-right">Expected interest</th><th class="th text-right">GL posted</th>
                  <th class="th text-right">Expected less posted</th><th class="th">Cause</th>
                  <th class="th text-right">EIR accrued</th><th class="th text-right">Variance</th>
                  <th class="th text-right">Carrying amount</th><th class="th text-right">Rate effect</th>
                  <th class="th text-right">Impairment</th><th class="th">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in sortedRows" :key="r.contract_id">
                  <td class="td">
                    <div class="font-semibold text-gray-900">{{ r.contract_id }}</div>
                    <div class="text-xs text-gray-500">{{ r.customer_name || r.portfolio || '—' }}{{ r.gl_account_code ? ' · ' + r.gl_account_code : '' }}</div>
                  </td>
                  <td class="td text-xs text-gray-600">
                    <div v-if="r.expected_opening_balance !== null">
                      Opening {{ money(r.expected_opening_balance) }}
                      <span v-if="r.expected_rate !== null">at {{ (r.expected_rate * 100).toFixed(2) }}%</span>
                      <span v-if="r.expected_days !== null">for {{ r.expected_days }} day(s)</span>
                    </div>
                    <div v-if="r.opening_gross !== null">Amortised {{ money(r.opening_gross) }}</div>
                    <div v-if="r.gl_implied_base !== null">Ledger implies {{ money(r.gl_implied_base) }}</div>
                    <div v-if="r.first_disbursement_month" class="font-medium text-amber-700">Month of first disbursement</div>
                    <div v-if="r.capitalising_moratorium_month" class="font-medium text-amber-700">Capitalising moratorium</div>
                  </td>
                  <td class="td text-right tabular-nums">{{ r.expected_interest === null ? '—' : money(r.expected_interest) }}</td>
                  <td class="td text-right tabular-nums">{{ r.has_posting ? money(r.gl_posted) : 'nothing posted' }}</td>
                  <td class="td text-right tabular-nums" :class="toneFor(r.expected_difference)">
                    {{ r.expected_difference === null ? '—' : money(r.expected_difference) }}
                  </td>
                  <td class="td">
                    <span :class="causeClass(r.cause)">{{ causeLabel(r.cause) }}</span>
                    <div class="mt-1 max-w-sm text-xs text-gray-500">{{ r.cause_detail }}</div>
                  </td>
                  <td class="td text-right tabular-nums">{{ r.eir_accrued === null ? '—' : money(r.eir_accrued) }}</td>
                  <td class="td text-right tabular-nums" :class="toneFor(r.variance)">
                    {{ r.variance === null ? '—' : money(r.variance) }}
                    <div v-if="r.variance_percent !== null" class="text-xs text-gray-500">{{ r.variance_percent }}%</div>
                  </td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.carrying_amount_effect === null ? '—' : money(r.carrying_amount_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.rate_effect === null ? '—' : money(r.rate_effect) }}</td>
                  <td class="td text-right tabular-nums text-gray-600">{{ r.impairment_effect === null ? '—' : money(r.impairment_effect) }}</td>
                  <td class="td"><span :class="statusClass(r.status)">{{ statusLabel(r.status) }}</span></td>
                </tr>
                <tr v-if="!rows.length">
                  <td colspan="12" class="p-10 text-center text-sm text-gray-500">No GL postings or live loan-book rows for this period and portfolio.</td>
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
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  period: { type: String, default: null },
  periods: { type: Array, default: () => [] },
  portfolios: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  rows: { type: Array, default: () => [] },
  bridge: { type: Object, required: true },
  summary: { type: Object, required: true },
  revenueReadiness: { type: Object, default: () => ({ locked_contracts: 0, calculated_periods: 0, rows_for_period: 0, missing_periods: [], first_period: null }) },
  revenueRun: { type: Object, default: null },
})

const form = reactive({
  period: props.filters.period || props.period || '',
  portfolio: props.filters.portfolio || '',
})

const apply = () => router.get(route('eir-reconciliation.index'),
  { period: form.period, portfolio: form.portfolio },
  { preserveState: true, preserveScroll: true, replace: true })

const running = ref(false)

/** Every period up to the selected one already holds amortisation rows. */
const chainComplete = computed(() => props.revenueReadiness.missing_periods.length === 0)

/**
 * The run is synchronous: it writes accounting rows the page then reads back,
 * so the reconciliation has to reload from them rather than from what was on
 * screen before.
 */
const runRevenue = (mode) => {
  running.value = true
  router.post(route('eir-reconciliation.run-revenue'),
    { period: props.period, portfolio: form.portfolio, mode },
    { preserveScroll: true, onFinish: () => { running.value = false } })
}

const money = (v) => {
  if (v === null || v === undefined) return '—'
  const n = Number(v)
  return (n < 0 ? '(' : '') + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (n < 0 ? ')' : '')
}

const cards = computed(() => [
  { label: 'Interest posted in the ledger', value: money(props.summary.posted_total), tone: 'text-gray-900' },
  { label: 'Contractual interest expected', value: money(props.summary.expected_total), tone: 'text-gray-900' },
  { label: 'Difference to explain', value: money(props.summary.expected_difference_total),
    tone: toneFor(props.summary.expected_difference_total) },
  { label: 'Rows agreeing with the ledger', value: `${props.summary.expected_agrees} of ${props.summary.rows}`, tone: 'text-gray-900' },
])

const bridgeLines = computed(() => [
  { label: 'GL interest posted, all rows', value: props.bridge.gl_total, emphasis: true },
  { label: 'Postings with no calculated counterpart', value: -props.bridge.gl_without_counterpart, indent: true,
    note: `${props.summary.not_calculated} row(s)`, tone: 'text-gray-600' },
  { label: 'GL interest on matched facilities', value: props.bridge.gl_matched, emphasis: true },
  { label: 'Base effect: the ledger against the interest the contract charges', value: props.bridge.base_effect, indent: true,
    note: 'a named cause per row', tone: toneFor(props.bridge.base_effect) },
  { label: 'Contractual interest on the loan-book balance', value: props.bridge.expected_total, emphasis: true },
  { label: 'Carrying amount effect: the amortised cost instead of the loan-book balance', value: props.bridge.carrying_amount_effect, indent: true, tone: toneFor(props.bridge.carrying_amount_effect) },
  { label: 'Rate effect: the EIR instead of the contractual rate on the same balance', value: props.bridge.rate_effect, indent: true, tone: toneFor(props.bridge.rate_effect) },
  { label: 'Impairment effect: Stage 3 accrued on net', value: props.bridge.impairment_effect, indent: true, tone: toneFor(props.bridge.impairment_effect) },
  { label: 'Unexplained', value: props.bridge.unexplained, indent: true, tone: toneFor(props.bridge.unexplained) },
  { label: 'EIR interest calculated', value: props.bridge.eir_total, emphasis: true },
])

/** The workbook and the PDF of this period, on the EIR export permission. */
const downloadUrl = (format) => route('eir-reconciliation.export', {
  period: props.period, portfolio: form.portfolio || null, format,
})

const causeLabel = (cause) => ({
  WITHIN_TOLERANCE: 'Agrees',
  LATE_DISBURSEMENT: 'Late disbursement',
  CATCH_UP_POSTING: 'Catch-up posting',
  MID_MONTH_TRANCHE: 'Mid-month tranche',
  RATE_MISMATCH: 'Rate mismatch',
  NO_POSTING: 'Nothing posted',
  DATA_GAP: 'Data gap',
  UNEXPLAINED: 'Unexplained',
}[cause] || cause)

const causeClass = (cause) => {
  const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '
  if (cause === 'WITHIN_TOLERANCE') return base + 'bg-emerald-100 text-emerald-800'
  if (cause === 'UNEXPLAINED' || cause === 'NO_POSTING') return base + 'bg-rose-100 text-rose-800'
  if (cause === 'DATA_GAP') return base + 'bg-gray-200 text-gray-800'
  return base + 'bg-amber-100 text-amber-800'
}

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

<style scoped>
.form-input{@apply block rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-maiic-500 focus:outline-none focus:ring-2 focus:ring-maiic-500}
.primary-btn{@apply inline-flex items-center justify-center rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2}
.secondary-btn{@apply inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2}
.th{@apply whitespace-nowrap bg-maiic-700 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white}
.td{@apply border-t border-gray-100 px-4 py-3 align-top text-sm text-gray-700}
tbody tr:nth-child(even){@apply bg-gray-50}
</style>
