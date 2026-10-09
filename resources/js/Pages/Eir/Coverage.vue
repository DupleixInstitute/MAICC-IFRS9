<template>
  <app-layout>
    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>EIR &amp; Revenue Recognition</span><span>/</span><span>EIR Calculations</span><span>/</span><span class="font-medium text-maiic-700">{{ view.label }}</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">{{ view.label }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ view.description }}</p>
      </div>
    </template>
    <template #actions>
      <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
        <label class="sr-only" for="cov-period">Reporting period</label>
        <select id="cov-period" v-model="form.period" class="maiic-select !w-36" title="Reporting period" @change="apply">
          <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
        </select>
        <label class="sr-only" for="cov-portfolio">Portfolio</label>
        <select id="cov-portfolio" v-model="form.portfolio" class="maiic-select !w-44" title="Portfolio" @change="apply">
          <option value="">All portfolios</option>
          <option v-for="p in portfolios" :key="p" :value="p">{{ p }}</option>
        </select>
      </form>
    </template>

    <div class="w-full space-y-4">
      <div>
        <KpiRow :cards="figures" />
        <p v-if="exposureLead > 1.5" class="mt-2 text-xs text-gray-500">
          Covered facilities are larger than average: exposure coverage ({{ summary.exposure_coverage_percent }}%) runs {{ exposureLead.toFixed(1) }} times ahead of contract coverage ({{ summary.coverage_percent }}%).
        </p>
      </div>

      <div class="maiic-panel">
        <!-- Blockers -->
        <template v-if="tab === 'blockers'">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Blockers, ranked by exposure</h3>
            <p class="mt-1 text-xs text-gray-500">Ranked by carrying amount, not count. <strong>Sole blocker</strong> counts the contracts this alone holds back.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr>
                  <th class="th">Blocker</th><th class="th text-right">Contracts</th>
                  <th class="th text-right">Exposure</th><th class="th text-right">% of book</th>
                  <th class="th text-right">Sole blocker</th><th class="th text-right">Facilities</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="i in issues" :key="i.code" :class="filters.issue === i.code ? '!bg-maiic-50' : ''">
                  <td class="td">
                    <div class="font-semibold text-gray-900">{{ i.label }}</div>
                    <div class="font-mono text-xs text-gray-500">{{ i.code }}</div>
                  </td>
                  <td class="td text-right tabular-nums">{{ number(i.contracts) }}</td>
                  <td class="td text-right tabular-nums">{{ money(i.exposure) }}</td>
                  <td class="td text-right tabular-nums font-semibold">{{ i.exposure_percent }}%</td>
                  <td class="td text-right tabular-nums">{{ number(i.sole_blocker) }}</td>
                  <td class="td text-right">
                    <button type="button" class="maiic-action maiic-action-view" :title="filters.issue === i.code ? 'Clear this blocker filter' : 'Show the facilities held back by this blocker'" @click="filterIssue(i.code)">
                      <font-awesome-icon :icon="filters.issue === i.code ? 'times-circle' : 'eye'" />
                    </button>
                  </td>
                </tr>
                <tr v-if="!issues.length">
                  <td colspan="6" class="p-10 text-center text-sm text-emerald-700">No blockers. Every in-scope contract can be solved.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- By portfolio -->
        <template v-else-if="tab === 'portfolios'">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Coverage by portfolio</h3>
            <p class="mt-1 text-xs text-gray-500">How many contracts in each portfolio carry a locked EIR.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead><tr><th class="th">Portfolio</th><th class="th text-right">Contracts</th><th class="th text-right">Covered</th><th class="th text-right">Coverage</th><th class="th text-right">Exposure</th></tr></thead>
              <tbody>
                <tr v-for="p in portfolioBreakdown" :key="p.portfolio">
                  <td class="td font-semibold text-gray-900">{{ p.portfolio }}</td>
                  <td class="td text-right tabular-nums">{{ number(p.contracts) }}</td>
                  <td class="td text-right tabular-nums">{{ number(p.covered) }}</td>
                  <td class="td text-right tabular-nums" :class="toneFor(p.coverage_percent)">{{ p.coverage_percent }}%</td>
                  <td class="td text-right tabular-nums">{{ money(p.exposure) }}</td>
                </tr>
                <tr v-if="!portfolioBreakdown.length"><td colspan="5" class="p-10 text-center text-sm text-gray-500">No in-scope contracts for this period.</td></tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Largest affected facilities -->
        <template v-else>
          <div class="flex flex-col gap-2 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
            <div>
              <h3 class="font-semibold text-gray-900">
                Largest affected facilities<span v-if="filters.issue" class="text-gray-500">, blocker {{ filters.issue }}</span>
              </h3>
              <p class="text-xs text-gray-500">
                The {{ number(contracts.length) }} largest by exposure of {{ number(contractsTotal) }}. Use the eye on the Blockers tab to narrow to one blocker.
              </p>
            </div>
            <button v-if="filters.issue" class="secondary-btn" @click="filterIssue(filters.issue)">Clear blocker filter</button>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead><tr><th class="th">Contract</th><th class="th">Portfolio</th><th class="th text-right">Exposure</th><th class="th">State</th><th class="th">Blockers</th></tr></thead>
              <tbody>
                <tr v-for="c in pagedContracts" :key="c.contract_id">
                  <td class="td">
                    <div class="font-semibold text-gray-900">{{ c.contract_id }}</div>
                    <div v-if="!c.on_tape" class="text-xs text-amber-700">Not on the current tape</div>
                  </td>
                  <td class="td text-sm text-gray-600">{{ c.portfolio || '-' }}</td>
                  <td class="td text-right tabular-nums">{{ money(c.exposure) }}</td>
                  <td class="td"><span :class="stateClass(c.state)">{{ stateLabel(c.state) }}</span></td>
                  <td class="td">
                    <div class="flex flex-wrap gap-1">
                      <span v-for="code in c.issues" :key="code" class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-700">{{ code }}</span>
                      <span v-if="!c.issues.length" class="text-xs text-gray-400">-</span>
                    </div>
                  </td>
                </tr>
                <tr v-if="!contracts.length"><td colspan="5" class="p-10 text-center text-sm text-gray-500">No affected facilities for these filters.</td></tr>
              </tbody>
            </table>
          </div>
          <RowPager v-model="page" :total="contracts.length" class="border-t border-gray-100" />
        </template>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

const props = defineProps({
  period: { type: String, default: null },
  periods: { type: Array, default: () => [] },
  portfolios: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  summary: { type: Object, required: true },
  states: { type: Object, required: true },
  issues: { type: Array, default: () => [] },
  portfolioBreakdown: { type: Array, default: () => [] },
  contracts: { type: Array, default: () => [] },
  contractsTotal: { type: Number, default: 0 },
  drilldownLimit: { type: Number, default: 50 },
  activeTab: { type: String, default: 'blockers' },
})

const form = reactive({
  period: props.filters.period || props.period || '',
  portfolio: props.filters.portfolio || '',
})

const tab = computed(() => props.activeTab || 'blockers')
const VIEWS = {
  blockers: { label: 'Blockers', description: 'What is holding back the contracts that do not yet carry a locked EIR, ranked by exposure' },
  portfolios: { label: 'Coverage by Portfolio', description: 'How much of each portfolio carries a locked EIR' },
  facilities: { label: 'Largest Facilities', description: 'The largest facilities still without a locked EIR, and what blocks each one' },
}
const view = computed(() => VIEWS[tab.value] || VIEWS.blockers)
const compact = (v) => {
  const n = Math.abs(Number(v || 0))
  if (n >= 1e9) return (Number(v) / 1e9).toFixed(2) + 'bn'
  if (n >= 1e6) return (Number(v) / 1e6).toFixed(1) + 'm'
  return number(v)
}
const figures = computed(() => [
  { label: 'Contract coverage', value: `${props.summary.coverage_percent}%`, bar: props.summary.coverage_percent, sub: `${number(props.summary.covered)} of ${number(props.summary.in_scope)} locked`, valueClass: toneFor(props.summary.coverage_percent) },
  { label: 'Exposure coverage', value: `${props.summary.exposure_coverage_percent}%`, bar: props.summary.exposure_coverage_percent, sub: `${compact(props.summary.exposure_covered)} of ${compact(props.summary.exposure_in_scope)}`, valueClass: toneFor(props.summary.exposure_coverage_percent), hint: `${money(props.summary.exposure_covered)} of ${money(props.summary.exposure_in_scope)} carrying amount` },
  ...stateCards.value,
])
const page = ref(1)
watch(() => props.contracts, () => { page.value = 1 })
const pagedContracts = computed(() => props.contracts.slice((page.value - 1) * 15, page.value * 15))
const stateAccents = { LOCKED: '#15803d', CALCULATED: '#0284c7', READY: '#0f766e', BLOCKED: '#dc2626', OUT_OF_SCOPE: '#9ca3af' }
const stateCards = computed(() => Object.entries(props.states).map(([k, v]) => ({
  label: stateLabel(k), value: Number(v.contracts || 0), sub: compact(v.exposure), hint: money(v.exposure), accent: stateAccents[k] || null,
})))

const go = (params) => router.get(route('eir-coverage.index'), params,
  { preserveState: true, preserveScroll: true, replace: true })

const apply = () => go({ period: form.period, portfolio: form.portfolio, issue: props.filters.issue || '', tab: tab.value })

const filterIssue = (code) => go({
  period: form.period,
  portfolio: form.portfolio,
  tab: props.filters.issue === code ? tab.value : 'facilities',
  issue: props.filters.issue === code ? '' : code,
})

const number = (v) => Number(v || 0).toLocaleString()

const money = (v) => {
  const n = Number(v || 0)
  return (n < 0 ? '(' : '') + Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (n < 0 ? ')' : '')
}

// How far exposure coverage runs ahead of contract coverage.
const exposureLead = computed(() => {
  const byCount = Number(props.summary.coverage_percent || 0)
  return byCount > 0 ? Number(props.summary.exposure_coverage_percent || 0) / byCount : 0
})

function toneFor (percent) {
  const p = Number(percent || 0)
  if (p >= 90) return 'text-emerald-700'
  if (p >= 50) return 'text-amber-700'
  return 'text-rose-700'
}

const stateLabel = (s) => ({
  LOCKED: 'Locked', CALCULATED: 'Awaiting approval', READY: 'Ready to solve',
  BLOCKED: 'Blocked', OUT_OF_SCOPE: 'Out of scope',
}[s] || s)

const stateClass = (s) => {
  const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '
  if (s === 'LOCKED') return base + 'bg-emerald-100 text-emerald-800'
  if (s === 'CALCULATED') return base + 'bg-sky-100 text-sky-800'
  if (s === 'READY') return base + 'bg-maiic-100 text-maiic-800'
  if (s === 'BLOCKED') return base + 'bg-rose-100 text-rose-800'
  return base + 'bg-gray-100 text-gray-700'
}
</script>

<style scoped>
.form-input{@apply block rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-maiic-500 focus:outline-none focus:ring-2 focus:ring-maiic-500}
</style>
