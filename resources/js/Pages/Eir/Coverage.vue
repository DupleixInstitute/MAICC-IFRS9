<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">Coverage &amp; Blockers</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">EIR Coverage &amp; Blockers</h2>
          <p class="mt-1 text-sm text-gray-600">How much of the book carries a locked EIR, and what is holding back the rest</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('eir-calculations.index')" class="secondary-btn">EIR Calculations</Link>
          <Link :href="route('eir-reconciliation.index')" class="primary-btn">GL Reconciliation</Link>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-5">
      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="apply">
          <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Reporting period</label>
            <select v-model="form.period" class="form-input md:w-44" @change="apply">
              <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Portfolio</label>
            <select v-model="form.portfolio" class="form-input md:w-44" @change="apply">
              <option value="">All portfolios</option>
              <option v-for="p in portfolios" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <button type="submit" class="secondary-btn">Apply</button>
        </form>
      </div>

      <!-- Coverage headline: count and exposure tell different stories -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-baseline justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Coverage by contract count</span>
            <span class="text-2xl font-bold" :class="toneFor(summary.coverage_percent)">{{ summary.coverage_percent }}%</span>
          </div>
          <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-maiic-600" :style="{ width: Math.min(100, summary.coverage_percent) + '%' }"></div>
          </div>
          <p class="mt-2 text-xs text-gray-600">{{ number(summary.covered) }} of {{ number(summary.in_scope) }} in-scope contracts carry a locked EIR</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-baseline justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Coverage by exposure</span>
            <span class="text-2xl font-bold" :class="toneFor(summary.exposure_coverage_percent)">{{ summary.exposure_coverage_percent }}%</span>
          </div>
          <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-emerald-600" :style="{ width: Math.min(100, summary.exposure_coverage_percent) + '%' }"></div>
          </div>
          <p class="mt-2 text-xs text-gray-600">{{ money(summary.exposure_covered) }} of {{ money(summary.exposure_in_scope) }} carrying amount</p>
        </div>
      </div>

      <div v-if="exposureLead > 1.5" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
        <h4 class="text-sm font-semibold text-emerald-900">Covered facilities are disproportionately large</h4>
        <p class="mt-1 text-xs text-emerald-800">
          Exposure coverage ({{ summary.exposure_coverage_percent }}%) runs {{ exposureLead.toFixed(1) }}&times; ahead of contract
          coverage ({{ summary.coverage_percent }}%). The solved population is weighted towards the facilities that matter,
          so materiality is further along than the headline count suggests.
        </p>
      </div>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div v-for="(v, k) in states" :key="k" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ number(v.contracts) }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ stateLabel(k) }}</div>
          <div class="mt-1 text-xs text-gray-500">{{ money(v.exposure) }}</div>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 p-4">
          <h3 class="font-semibold text-gray-900">Blockers, ranked by exposure</h3>
          <p class="mt-1 text-xs text-gray-500">
            Ranked by carrying amount rather than contract count &mdash; a blocker affecting thousands of dormant
            facilities and one affecting a handful of large facilities look identical in a count.
            <strong>Sole blocker</strong> counts the contracts this is the <em>only</em> thing holding back: clearing it makes
            exactly those solvable.
          </p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr>
                <th class="th">Blocker</th><th class="th text-right">Contracts</th>
                <th class="th text-right">Exposure</th><th class="th text-right">% of book</th>
                <th class="th text-right">Sole blocker</th><th class="th"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in issues" :key="i.code" :class="filters.issue === i.code ? 'bg-maiic-50' : ''">
                <td class="td">
                  <div class="font-semibold text-gray-900">{{ i.label }}</div>
                  <div class="font-mono text-xs text-gray-500">{{ i.code }}</div>
                </td>
                <td class="td text-right tabular-nums">{{ number(i.contracts) }}</td>
                <td class="td text-right tabular-nums">{{ money(i.exposure) }}</td>
                <td class="td text-right tabular-nums font-semibold">{{ i.exposure_percent }}%</td>
                <td class="td text-right tabular-nums">{{ number(i.sole_blocker) }}</td>
                <td class="td text-right">
                  <button class="text-xs font-semibold text-maiic-700 hover:underline" @click="filterIssue(i.code)">
                    {{ filters.issue === i.code ? 'Clear' : 'Show' }}
                  </button>
                </td>
              </tr>
              <tr v-if="!issues.length">
                <td colspan="6" class="p-10 text-center text-sm text-emerald-700">No blockers &mdash; every in-scope contract is solvable.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 p-4">
          <h3 class="font-semibold text-gray-900">By portfolio</h3>
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
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-col gap-2 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">
              Largest affected facilities<span v-if="filters.issue" class="text-gray-500"> &mdash; {{ filters.issue }}</span>
            </h3>
            <p class="text-xs text-gray-500">
              Showing the {{ Math.min(drilldownLimit, contractsTotal) }} largest by exposure of {{ number(contractsTotal) }}.
            </p>
          </div>
          <button v-if="filters.issue" class="secondary-btn" @click="filterIssue(filters.issue)">Clear blocker filter</button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead><tr><th class="th">Contract</th><th class="th">Portfolio</th><th class="th text-right">Exposure</th><th class="th">State</th><th class="th">Blockers</th></tr></thead>
            <tbody>
              <tr v-for="c in contracts" :key="c.contract_id">
                <td class="td">
                  <div class="font-semibold text-gray-900">{{ c.contract_id }}</div>
                  <div v-if="!c.on_tape" class="text-xs text-amber-700">Not on the current tape</div>
                </td>
                <td class="td text-sm text-gray-600">{{ c.portfolio || '—' }}</td>
                <td class="td text-right tabular-nums">{{ money(c.exposure) }}</td>
                <td class="td"><span :class="stateClass(c.state)">{{ stateLabel(c.state) }}</span></td>
                <td class="td">
                  <div class="flex flex-wrap gap-1">
                    <span v-for="code in c.issues" :key="code" class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-700">{{ code }}</span>
                    <span v-if="!c.issues.length" class="text-xs text-gray-400">—</span>
                  </div>
                </td>
              </tr>
              <tr v-if="!contracts.length"><td colspan="5" class="p-10 text-center text-sm text-gray-500">No contracts match.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
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
  summary: { type: Object, required: true },
  states: { type: Object, required: true },
  issues: { type: Array, default: () => [] },
  portfolioBreakdown: { type: Array, default: () => [] },
  contracts: { type: Array, default: () => [] },
  contractsTotal: { type: Number, default: 0 },
  drilldownLimit: { type: Number, default: 50 },
})

const form = reactive({
  period: props.filters.period || props.period || '',
  portfolio: props.filters.portfolio || '',
})

const go = (params) => router.get(route('eir-coverage.index'), params,
  { preserveState: true, preserveScroll: true, replace: true })

const apply = () => go({ period: form.period, portfolio: form.portfolio, issue: props.filters.issue || '' })

const filterIssue = (code) => go({
  period: form.period,
  portfolio: form.portfolio,
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
