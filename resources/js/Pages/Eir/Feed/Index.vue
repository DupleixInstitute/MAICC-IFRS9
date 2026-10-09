<template>
  <app-layout title="E-Banker Feed" description="Packs loaded from E-Banker, the checks each pack passed, and the approved builds of the loan book">
    <template #actions>
      <a :href="route('eir-feed.queries')" class="secondary-btn" title="The extract queries, as one file to run on E-Banker">Download the queries</a>
    </template>

    <div class="space-y-5">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Route in force</div><div class="truncate text-base font-bold text-gray-900" :title="routeInForce">{{ routeInForce }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Build method in force</div><div class="truncate text-base font-bold text-gray-900" :title="methodInForce">{{ methodInForce }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488" title="A view or build after this date is refused, never estimated"><div class="maiic-kpi-label">Last posting loaded</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ lastLedgerDate || '-' }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488" :title="locks.join(', ') || 'No period is locked'"><div class="maiic-kpi-label">Locked periods</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ locks.length }}</div></div>
      </div>

      <div>
        <div class="maiic-panel">

          <!-- Loads -->
          <template v-if="tab === 'loads'">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">Every pack that came in, by any route. A pack that fails a check is quarantined, with the file and row named.</p>
              <form v-if="canGovern" @submit.prevent="landPack" class="flex flex-wrap items-center gap-2">
                <input type="file" accept=".zip" @change="packFile = $event.target.files[0]" class="text-sm"/>
                <button type="submit" :disabled="!packFile || landing" class="primary-btn">{{ landing ? 'Loading...' : 'Load a pack' }}</button>
              </form>
            </div>
            <details v-if="canGovern" class="border-b border-gray-200 px-5 py-2 text-sm text-gray-700">
              <summary class="cursor-pointer font-semibold text-maiic-800">How to load a pack</summary>
              <p class="mt-1 pb-1">Run the queries (Download the queries, top right) on E-Banker, put the CSV extracts and their <span class="font-mono">manifest.json</span> in one zip file, and load the zip here. The manifest lists each file with its row count; the checks compare every file with it before anything is kept.</p>
            </details>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Load</th><th>Pack</th><th>Route</th><th>Period</th><th>Status</th><th class="num">Files</th><th>Checks</th><th>Loaded</th><th class="text-right">View</th></tr></thead>
                <tbody>
                  <template v-for="l in pageOf(loads, 'loads')" :key="l.id">
                    <tr>
                      <td class="font-mono text-xs" :title="'Pack fingerprint ' + l.hash">{{ l.id }}</td>
                      <td class="max-w-xs truncate" :title="l.pack">{{ l.pack }}</td>
                      <td class="text-xs whitespace-nowrap">{{ l.route }}</td>
                      <td class="whitespace-nowrap">{{ l.period || 'History' }}</td>
                      <td><span class="maiic-badge" :class="statusBadge(l.status)">{{ l.status }}</span></td>
                      <td class="num">{{ l.files }}<span v-if="l.failed" class="text-red-600"> ({{ l.failed }} failed)</span></td>
                      <td class="text-xs whitespace-nowrap">
                        <span v-if="gateCount(l, 'PASS')" class="maiic-badge maiic-badge-green mr-1">{{ gateCount(l, 'PASS') }} passed</span>
                        <span v-if="gateCount(l, 'WARN')" class="maiic-badge maiic-badge-gold mr-1">{{ gateCount(l, 'WARN') }} warning</span>
                        <span v-if="gateCount(l, 'FAIL') || l.failed" class="maiic-badge maiic-badge-red">{{ gateCount(l, 'FAIL') + l.failed }} failed</span>
                        <span v-if="l.accepted_exceptions.length" class="maiic-badge maiic-badge-gold">{{ l.accepted_exceptions.length }} accepted {{ l.accepted_exceptions.length === 1 ? 'exception' : 'exceptions' }}</span>
                        <span v-if="!l.gates.length && !l.failed && !l.accepted_exceptions.length" class="text-gray-400">None recorded</span>
                      </td>
                      <td class="text-xs whitespace-nowrap">{{ String(l.loaded_at || '').slice(0, 16) }}<br><span class="text-gray-500">{{ l.loaded_by || 'system' }}</span></td>
                      <td class="text-right">
                        <button type="button" class="maiic-action maiic-action-view" :title="open === l.id ? 'Hide the checks and watermarks' : 'Show the checks and watermarks'" @click="open = open === l.id ? null : l.id">
                          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.66 10.59a1.65 1.65 0 010-1.18C2.1 5.74 5.73 3 10 3s7.9 2.74 9.34 6.41c.15.38.15.8 0 1.18C17.9 14.26 14.27 17 10 17S2.1 14.26.66 10.59zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="open === l.id" class="bg-gray-50">
                      <td colspan="9" class="!py-4">
                        <div class="grid gap-4 md:grid-cols-2">
                          <div>
                            <div class="maiic-kpi-label">Checks</div>
                            <ul class="space-y-1 text-xs">
                              <li v-for="g in l.gates" :key="g.gate" :class="g.result === 'FAIL' ? 'text-red-700' : (g.result === 'WARN' ? 'text-amber-700' : 'text-gray-600')">
                                <span class="font-mono font-semibold">{{ g.gate }}</span> {{ g.result }}<span v-if="g.detail">: {{ g.detail }}</span><span v-if="g.failures.length"> - {{ g.failures.join('; ') }}</span>
                              </li>
                              <li v-for="f in l.failures" :key="f.file" class="text-red-700"><span class="font-semibold">{{ f.file }}</span>: {{ f.failures.join('; ') }}</li>
                              <li v-for="(e, i) in l.accepted_exceptions" :key="'e' + i" class="text-amber-700">Accepted exception: {{ e }}</li>
                              <li v-if="!l.gates.length && !l.failures.length && !l.accepted_exceptions.length" class="text-gray-400">No checks recorded on this load.</li>
                            </ul>
                          </div>
                          <div>
                            <div class="maiic-kpi-label">Watermarks (last key loaded per extract)</div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-gray-600">
                              <span v-for="(v, k) in l.watermarks" :key="k" class="whitespace-nowrap">{{ k }} = {{ v }}</span>
                              <span v-if="!Object.keys(l.watermarks || {}).length" class="font-sans text-gray-400">None recorded.</span>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="!loads.length"><td colspan="9" class="maiic-empty">No pack has been loaded yet. Download the queries, run them on E-Banker and load the zip here.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.loads" :total="loads.length"/>
          </template>

          <!-- Build -->
          <template v-else-if="tab === 'builds'">
            <div class="border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">One person proposes a build of the loan book and a second approves it. The differences from the previous build are shown first; a locked period is never restated.</p>
              <form v-if="canGovern" @submit.prevent="requestBuild" class="mt-2 flex flex-wrap items-end gap-3">
                <label><span class="maiic-flabel">From</span><input v-model="build.from" type="month" class="maiic-input" required/></label>
                <label><span class="maiic-flabel">To</span><input v-model="build.to" type="month" class="maiic-input" required/></label>
                <label><span class="maiic-flabel">Method</span>
                  <select v-model="build.method" class="maiic-select"><option value="">In force on each period end</option><option value="A">A: from the stored run</option><option value="B">B: derived from the ledger</option></select>
                </label>
                <label class="flex items-center gap-2 pb-2 text-sm text-gray-700"><input v-model="build.retire_stale" type="checkbox"/> Retire old test rows</label>
                <button type="submit" class="primary-btn" :disabled="building">{{ building ? 'Proposing...' : 'Propose a build' }}</button>
              </form>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Build</th><th>Method</th><th>Periods</th><th>Status</th><th>Proposed by</th><th>Approved by</th><th>Result</th><th v-if="canGovern" class="text-right">Action</th></tr></thead>
                <tbody>
                  <tr v-for="b in pageOf(builds, 'builds')" :key="b.id" class="align-top">
                    <td class="font-mono text-xs">{{ b.id }}</td>
                    <td>{{ b.method === '?' ? 'In force' : b.method }}</td>
                    <td class="whitespace-nowrap">{{ b.from }} to {{ b.to }}</td>
                    <td><span class="maiic-badge" :class="b.status === 'BUILT' ? 'maiic-badge-green' : (b.status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')">{{ b.status }}</span></td>
                    <td class="text-xs whitespace-nowrap">{{ b.requested_by || 'system' }}<br><span class="text-gray-500">{{ b.created_at }}</span></td>
                    <td class="text-xs">{{ b.approver || '-' }}</td>
                    <td class="text-xs">
                      <span v-for="p in b.summary.slice(0, 4)" :key="p.period" class="mr-3 inline-block whitespace-nowrap">{{ p.period }}: {{ p.rows }} rows<span v-if="p.changed">, {{ p.changed }} changed</span><span v-if="p.flagged">, {{ p.flagged }} flagged</span></span>
                      <span v-if="b.summary.length > 4" class="text-gray-400" :title="b.summary.slice(4).map(p => p.period + ': ' + p.rows + ' rows').join('\n')">and {{ b.summary.length - 4 }} more periods</span>
                    </td>
                    <td v-if="canGovern" class="text-right whitespace-nowrap">
                      <button v-if="b.status === 'PROPOSED'" @click="approve(b.id)" class="secondary-btn" title="Approve this build and write it; the differences are kept on the build">Approve and build</button>
                    </td>
                  </tr>
                  <tr v-if="!builds.length"><td :colspan="canGovern ? 8 : 7" class="maiic-empty">No build yet. Choose the periods above and propose one.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.builds" :total="builds.length"/>
          </template>

          <!-- Periods built -->
          <template v-else-if="tab === 'periods'">
            <div class="border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">The loan book rows built for each period, by method. A flag names a difference from the source, it never hides one.</p>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Period</th><th>Method</th><th class="num">Rows</th><th class="num">Flagged</th><th>Locked</th></tr></thead>
                <tbody>
                  <tr v-for="p in pageOf(periods, 'periods')" :key="p.reporting_period + p.build_method">
                    <td>{{ p.reporting_period }}</td><td>{{ p.build_method }}</td><td class="num">{{ fmt(p.n) }}</td><td class="num">{{ fmt(p.flagged) }}</td>
                    <td><span v-if="locks.includes(p.reporting_period)" class="maiic-badge maiic-badge-grey">Locked</span></td>
                  </tr>
                  <tr v-if="!periods.length"><td colspan="5" class="maiic-empty">Nothing has been built from the loaded packs yet. Use the Loan Book Builds tab to propose one.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.periods" :total="periods.length"/>
          </template>

          <!-- Query register -->
          <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">Each E-Banker extract: its source table, its key, and whether it is pulled incrementally from the last key loaded.</p>
              <input v-model="querySearch" class="maiic-input md:w-72" placeholder="Search query, title or table" @input="page.queries = 1"/>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Query</th><th>Title</th><th>Source table</th><th>Key</th><th>Date column</th><th>Incremental</th><th class="num">Rows loaded</th><th>Last date</th></tr></thead>
                <tbody>
                  <tr v-for="q in pageOf(filteredQueries, 'queries')" :key="q.query_id">
                    <td class="font-mono text-xs whitespace-nowrap">{{ q.query_id }} v{{ q.version }}</td><td>{{ q.title }}</td><td class="font-mono text-xs">{{ q.source_table || '-' }}</td><td class="font-mono text-xs">{{ q.key_column || 'row' }}</td><td class="font-mono text-xs">{{ q.date_column || '-' }}</td>
                    <td><span v-if="q.incremental" class="maiic-badge maiic-badge-green">Yes</span><span v-else class="text-gray-400">No</span></td>
                    <td class="num">{{ fmt(q.rows) }}</td><td class="text-xs whitespace-nowrap">{{ q.last_date || '' }}</td>
                  </tr>
                  <tr v-if="!filteredQueries.length"><td colspan="8" class="maiic-empty">{{ queries.length ? 'No query matches the search.' : 'The query register is empty.' }}</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.queries" :total="filteredQueries.length"/>
          </template>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
  components: { AppLayout, LocalPager },
  props: { tab: { type: String, default: 'loads' }, tabCounts: Object, loads: Array, queries: Array, builds: Array, periods: Array, routeInForce: String, methodInForce: String, locks: Array, lastLedgerDate: String, canGovern: Boolean },
  data() {
    return {
      open: null, querySearch: '',
      page: { loads: 1, builds: 1, periods: 1, queries: 1 },
      packFile: null, landing: false, building: false, build: { from: '', to: '', method: '', retire_stale: false },
    }
  },
  computed: {
    filteredQueries() {
      const s = this.querySearch.trim().toLowerCase()
      if (!s) return this.queries
      return this.queries.filter(q => [q.query_id, q.title, q.source_table].some(v => String(v || '').toLowerCase().includes(s)))
    },
  },
  methods: {
    pageOf(rows, key) { const p = this.page[key]; return rows.slice((p - 1) * 15, p * 15) },
    fmt(n) { return Number(n || 0).toLocaleString() },
    gateCount(l, result) { return (l.gates || []).filter(g => g.result === result).length },
    statusBadge(s) { return s === 'LANDED' ? 'maiic-badge-green' : (s === 'QUARANTINED' ? 'maiic-badge-red' : 'maiic-badge-grey') },
    landPack() {
      if (!this.packFile) return
      this.landing = true
      router.post(route('eir-feed.land'), { pack: this.packFile }, { forceFormData: true, onFinish: () => { this.landing = false; this.packFile = null } })
    },
    requestBuild() {
      this.building = true
      router.post(route('eir-feed.build'), this.build, { preserveScroll: true, onFinish: () => { this.building = false } })
    },
    async approve(id) {
      if (!(await confirmDialog({ title: 'Approve build ' + id + '?', message: 'The build is written to the loan book. The differences from the previous build are kept on the build.', confirmLabel: 'Approve and build' }))) return
      router.post(route('eir-feed.approve', id), {}, { preserveScroll: true })
    },
  },
}
</script>
