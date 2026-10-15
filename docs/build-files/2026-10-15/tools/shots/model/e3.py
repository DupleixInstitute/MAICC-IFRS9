import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Coverage.vue'; s=open(p,encoding='utf-8').read()
a=s.index('      <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">')
b=s.index('  </app-layout>')
new='''      <KpiRow :cards="stateCards" />

      <PageTabs v-model="tab" :tabs="tabs" flush />
      <div class="-mt-5 rounded-b-xl border border-t-0 border-gray-200 bg-white shadow-sm overflow-hidden">
        <!-- Blockers -->
        <template v-if="tab === 'blockers'">
          <div class="border-b border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Blockers, ranked by exposure</h3>
            <p class="mt-1 text-xs text-gray-500">
              Ranked by carrying amount rather than contract count, because a blocker on many small dormant facilities and one on a few
              large facilities look the same in a count. <strong>Sole blocker</strong> counts the contracts this is the only thing holding
              back: clearing it makes exactly those solvable.
            </p>
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
                The {{ number(contracts.length) }} largest by exposure of {{ number(contractsTotal) }} affected facilities.
                Use the eye on the Blockers tab to see the facilities behind one blocker.
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
'''
s=s[:a]+new+s[b:]
s=rep(s,'''      <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="apply">''','''      <div class="maiic-filterbar">
        <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="apply">''')
s=rep(s,'''          Exposure coverage ({{ summary.exposure_coverage_percent }}%) runs {{ exposureLead.toFixed(1) }}&times; ahead of contract''','''          Exposure coverage ({{ summary.exposure_coverage_percent }}%) runs {{ exposureLead.toFixed(1) }} times ahead of contract''')
s=rep(s,"import { computed, reactive } from 'vue'","import { computed, reactive, ref, watch } from 'vue'")
s=rep(s,"import AppLayout from '@/Layouts/AppLayout.vue'","""import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'""")
s=rep(s,'''const go = (params) =>''','''const tab = ref(props.filters.issue ? 'facilities' : 'blockers')
const page = ref(1)
watch(() => props.contracts, () => { page.value = 1 })
const pagedContracts = computed(() => props.contracts.slice((page.value - 1) * 15, page.value * 15))
const tabs = computed(() => [
  { key: 'blockers', label: 'Blockers', count: props.issues.length },
  { key: 'portfolios', label: 'By portfolio', count: props.portfolioBreakdown.length },
  { key: 'facilities', label: 'Largest affected facilities', count: props.contractsTotal },
])
const stateAccents = { LOCKED: '#15803d', CALCULATED: '#0284c7', READY: '#0f766e', BLOCKED: '#dc2626', OUT_OF_SCOPE: '#9ca3af' }
const stateCards = computed(() => Object.entries(props.states).map(([k, v]) => ({
  label: stateLabel(k), value: Number(v.contracts || 0), sub: money(v.exposure), accent: stateAccents[k] || null,
})))

const go = (params) =>''')
s=rep(s,'''const filterIssue = (code) => go({''','''const filterIssue = (code) => {
  if (props.filters.issue !== code) tab.value = 'facilities'
  goIssue(code)
}
const goIssue = (code) => go({''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
