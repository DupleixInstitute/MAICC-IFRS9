import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Components/Maiic/KpiRow.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''            <div v-if="card.sub" class="mt-0.5 text-[11px] text-gray-500 truncate">{{ card.sub }}</div>''','''            <div v-if="card.bar !== undefined && card.bar !== null" class="mt-1 h-1 w-full overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full bg-maiic-600" :style="{ width: Math.max(0, Math.min(100, Number(card.bar))) + '%' }"></div>
            </div>
            <div v-if="card.sub" class="mt-0.5 text-[11px] text-gray-500 truncate">{{ card.sub }}</div>''')
s=rep(s,"// [{ label, value, sub?, hint?, accent?, valueClass?, raw? }]","// [{ label, value, sub?, hint?, accent?, valueClass?, raw?, bar? (0-100 progress) }]")
open(p,'w',encoding='utf-8').write(s)

p='Pages/Eir/Coverage.vue'; s=open(p,encoding='utf-8').read()
a=s.index('    <template #header>'); b=s.index('      <div>\n      <PageTabs')
s=s[:a]+'''    <template #header>
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

'''+s[b:]
s=rep(s,'''      <div>
      <PageTabs v-model="tab" :tabs="tabs" flush />
      <div class="rounded-b-xl border border-t border-gray-200 bg-white shadow-sm overflow-hidden">''','''      <div class="maiic-panel">''')
s=rep(s,'''          <RowPager v-model="page" :total="contracts.length" class="border-t border-gray-100" />
        </template>
      </div>
      </div>
    </div>''','''          <RowPager v-model="page" :total="contracts.length" class="border-t border-gray-100" />
        </template>
      </div>
    </div>''')
s=rep(s,'''            <p class="mt-1 text-xs text-gray-500">
              Ranked by carrying amount rather than contract count, because a blocker on many small dormant facilities and one on a few
              large facilities look the same in a count. <strong>Sole blocker</strong> counts the contracts this is the only thing holding
              back: clearing it makes exactly those solvable.
            </p>''','''            <p class="mt-1 text-xs text-gray-500">Ranked by carrying amount, not count. <strong>Sole blocker</strong> counts the contracts this alone holds back.</p>''')
s=rep(s,'''                The {{ number(contracts.length) }} largest by exposure of {{ number(contractsTotal) }} affected facilities.
                Use the eye on the Blockers tab to see the facilities behind one blocker.''','''                The {{ number(contracts.length) }} largest by exposure of {{ number(contractsTotal) }}. Use the eye on the Blockers tab to narrow to one blocker.''')
s=rep(s,"import PageTabs from '@/Components/Maiic/PageTabs.vue'\n","")
s=rep(s,'''const tab = ref(props.filters.issue ? 'facilities' : 'blockers')''','''const tab = computed(() => props.activeTab || 'blockers')
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
])''')
s=rep(s,'''const tabs = computed(() => [
  { key: 'blockers', label: 'Blockers', count: props.issues.length },
  { key: 'portfolios', label: 'By portfolio', count: props.portfolioBreakdown.length },
  { key: 'facilities', label: 'Largest affected facilities', count: props.contractsTotal },
])
''','')
s=rep(s,'''label: stateLabel(k), value: Number(v.contracts || 0), sub: money(v.exposure), accent: stateAccents[k] || null,''','''label: stateLabel(k), value: Number(v.contracts || 0), sub: compact(v.exposure), hint: money(v.exposure), accent: stateAccents[k] || null,''')
s=rep(s,'''const filterIssue = (code) => {
  if (props.filters.issue !== code) tab.value = 'facilities'
  goIssue(code)
}
const goIssue = (code) => go({
  portfolio: form.portfolio,'''.replace('  portfolio: form.portfolio,','  period: form.period,\n  portfolio: form.portfolio,'),'''const filterIssue = (code) => go({
  period: form.period,
  portfolio: form.portfolio,
  tab: props.filters.issue === code ? tab.value : 'facilities','''.replace('  tab: props','  tab: props'))
s=rep(s,'''const apply = () => go({ period: form.period, portfolio: form.portfolio, issue: props.filters.issue || '' })''','''const apply = () => go({ period: form.period, portfolio: form.portfolio, issue: props.filters.issue || '', tab: tab.value })''')
s=rep(s,'''  drilldownLimit: { type: Number, default: 50 },''','''  drilldownLimit: { type: Number, default: 50 },
  activeTab: { type: String, default: 'blockers' },''')
s=rep(s,"import { Link, router } from '@inertiajs/vue3'","import { router } from '@inertiajs/vue3'")
open(p,'w',encoding='utf-8').write(s)
print('ok')
