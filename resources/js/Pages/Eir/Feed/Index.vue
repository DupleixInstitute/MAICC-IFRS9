<template>
  <app-layout title="E-Banker Feed" description="The queries, the loads with their gate results, the watermarks, the quarantine and the Build with its approval (spec v4 section 6.8)">
    <template #actions>
      <a :href="route('eir-feed.queries')" class="secondary-btn text-sm" title="The versioned queries as the file Barry runs">Download the queries</a>
    </template>

    <div class="space-y-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Route in force</div><div class="text-sm font-bold text-gray-900 dark:text-slate-100">{{ routeInForce }}</div><div class="text-xs text-gray-500">Governance Centre: ebanker_feed_route</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Build method in force</div><div class="text-sm font-bold text-gray-900 dark:text-slate-100">{{ methodInForce }}</div><div class="text-xs text-gray-500">Governance Centre: loan_book_build_method</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Last posting loaded</div><div class="maiic-kpi-value">{{ lastLedgerDate || '-' }}</div><div class="text-xs text-gray-500">A view or build after this date is refused, never estimated</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Locked periods</div><div class="maiic-kpi-value">{{ locks.length }}</div><div class="text-xs text-gray-500">{{ locks.join(', ') || 'none' }}</div></div>
      </div>

      <!-- Loads -->
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">Loads</h3>
            <p class="text-sm text-gray-500">Every pack that entered by the one door, whichever route it came by; a pack that fails a gate is quarantined with the file and row named</p>
          </div>
          <form v-if="canGovern" @submit.prevent="landPack" class="flex items-center gap-2">
            <input type="file" accept=".zip" @change="packFile = $event.target.files[0]" class="text-sm"/>
            <button type="submit" :disabled="!packFile || landing" class="primary-btn text-sm">Land a pack (route 1)</button>
          </form>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Load</th><th>Pack</th><th>Route</th><th>Period</th><th>Status</th><th class="num">Files</th><th>Loaded</th><th>Watermarks</th></tr></thead>
            <tbody>
              <tr v-for="l in loads" :key="l.id" class="align-top">
                <td class="font-mono text-xs">{{ l.id }} <span class="text-gray-400">{{ l.hash }}</span></td>
                <td>{{ l.pack }}
                  <ul v-if="l.failed" class="mt-1 space-y-0.5 text-xs text-red-700 dark:text-red-300"><li v-for="f in l.failures" :key="f.file"><span class="font-semibold">{{ f.file }}</span>: {{ f.failures.join('; ') }}</li></ul>
                  <ul v-if="l.gates && l.gates.length" class="mt-1 space-y-0.5 text-xs">
                    <li v-for="g in l.gates" :key="g.gate" :class="g.result === 'FAIL' ? 'text-red-700 dark:text-red-300' : (g.result === 'WARN' ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500')">
                      <span class="font-mono">{{ g.gate }}</span> {{ g.result }}<span v-if="g.detail">: {{ g.detail }}</span><span v-if="g.failures.length"> — {{ g.failures.join('; ') }}</span>
                    </li>
                  </ul>
                  <ul v-if="l.accepted_exceptions.length" class="mt-1 space-y-0.5 text-xs text-amber-700 dark:text-amber-300"><li v-for="(e, i) in l.accepted_exceptions" :key="i">Accepted exception: {{ e }}</li></ul>
                </td>
                <td class="text-xs">{{ l.route }}</td>
                <td>{{ l.period || 'history' }}</td>
                <td><span class="maiic-badge" :class="l.status === 'LANDED' ? 'maiic-badge-green' : (l.status === 'QUARANTINED' ? 'maiic-badge-red' : 'maiic-badge-grey')">{{ l.status }}</span></td>
                <td class="num">{{ l.files }}<span v-if="l.failed" class="text-red-600"> ({{ l.failed }} failed)</span></td>
                <td class="text-xs">{{ l.loaded_at }}<br><span class="text-gray-500">{{ l.loaded_by || 'system' }}</span></td>
                <td class="text-xs font-mono"><span v-for="(v, k) in l.watermarks" :key="k" class="mr-2 whitespace-nowrap">{{ k }}={{ v }}</span></td>
              </tr>
              <tr v-if="!loads.length"><td colspan="8" class="maiic-empty">No pack has been landed yet. The bootstrap lands the committed pack; route 1 lands a zip here.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Build -->
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">Build the loan book</h3>
            <p class="text-sm text-gray-500">One person asks for the build, a second approves it; the differences from the previous build are shown first and a locked period is never restated (spec v4 section 6.6)</p>
          </div>
          <form v-if="canGovern" @submit.prevent="requestBuild" class="flex flex-wrap items-end gap-2">
            <label class="text-xs"><span class="maiic-flabel">From</span><input v-model="build.from" type="month" class="maiic-input" required/></label>
            <label class="text-xs"><span class="maiic-flabel">To</span><input v-model="build.to" type="month" class="maiic-input" required/></label>
            <label class="text-xs"><span class="maiic-flabel">Method</span>
              <select v-model="build.method" class="maiic-select"><option value="">In force on each period end</option><option value="A">A: bootstrap of the stored run</option><option value="B">B: derived from the ledger</option></select>
            </label>
            <label class="flex items-center gap-1 text-xs"><input v-model="build.retire_stale" type="checkbox"/> retire old test rows</label>
            <button type="submit" class="primary-btn text-sm" :disabled="building">Propose a build</button>
          </form>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Build</th><th>Method</th><th>Periods</th><th>Status</th><th>Requested by</th><th>Approved by</th><th>Result</th><th v-if="canGovern"></th></tr></thead>
            <tbody>
              <tr v-for="b in builds" :key="b.id" class="align-top">
                <td class="font-mono text-xs">{{ b.id }}</td>
                <td>{{ b.method === '?' ? 'in force' : b.method }}</td>
                <td>{{ b.from }} to {{ b.to }}</td>
                <td><span class="maiic-badge" :class="b.status === 'BUILT' ? 'maiic-badge-green' : (b.status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')">{{ b.status }}</span></td>
                <td class="text-xs">{{ b.requested_by || 'system' }}<br><span class="text-gray-500">{{ b.created_at }}</span></td>
                <td class="text-xs">{{ b.approver || '-' }}</td>
                <td class="text-xs">
                  <span v-for="p in b.summary.slice(0, 6)" :key="p.period" class="mr-2 whitespace-nowrap">{{ p.period }}: {{ p.rows }} rows<span v-if="p.changed">, {{ p.changed }} changed</span><span v-if="p.flagged">, {{ p.flagged }} flagged</span></span>
                  <span v-if="b.summary.length > 6" class="text-gray-400">… {{ b.summary.length - 6 }} more</span>
                </td>
                <td v-if="canGovern"><button v-if="b.status === 'PROPOSED'" @click="approve(b.id)" class="secondary-btn text-xs">Approve and build</button></td>
              </tr>
              <tr v-if="!builds.length"><td colspan="8" class="maiic-empty">No build yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Periods built -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">Periods built</h3><p class="text-sm text-gray-500">Every row records its method, its load and the inputs it was built from; a flag names a difference, never hides it</p></div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Period</th><th>Method</th><th class="num">Rows</th><th class="num">Flagged</th><th>Locked</th></tr></thead>
            <tbody>
              <tr v-for="p in periods" :key="p.reporting_period + p.build_method"><td>{{ p.reporting_period }}</td><td>{{ p.build_method }}</td><td class="num">{{ p.n }}</td><td class="num">{{ p.flagged }}</td><td>{{ locks.includes(p.reporting_period) ? 'yes' : '' }}</td></tr>
              <tr v-if="!periods.length"><td colspan="5" class="maiic-empty">Nothing built from the landing zone yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Queries -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The query register</h3><p class="text-sm text-gray-500">Each extract the landing zone knows: its source table, its key, and whether it is pulled incrementally by key from the watermark</p></div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Query</th><th>Title</th><th>Source table</th><th>Key</th><th>Date column</th><th>Incremental</th><th class="num">Rows landed</th><th>Last date</th></tr></thead>
            <tbody>
              <tr v-for="q in queries" :key="q.query_id"><td class="font-mono text-xs">{{ q.query_id }} v{{ q.version }}</td><td>{{ q.title }}</td><td class="font-mono text-xs">{{ q.source_table || '-' }}</td><td class="font-mono text-xs">{{ q.key_column || 'row' }}</td><td class="font-mono text-xs">{{ q.date_column || '-' }}</td><td>{{ q.incremental ? 'yes' : '' }}</td><td class="num">{{ q.rows }}</td><td class="text-xs">{{ q.last_date || '' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { loads: Array, queries: Array, builds: Array, periods: Array, routeInForce: String, methodInForce: String, locks: Array, lastLedgerDate: String, canGovern: Boolean },
  data() {
    return { packFile: null, landing: false, building: false, build: { from: '', to: '', method: '', retire_stale: false } }
  },
  methods: {
    landPack() {
      if (!this.packFile) return
      this.landing = true
      router.post(route('eir-feed.land'), { pack: this.packFile }, { forceFormData: true, onFinish: () => { this.landing = false; this.packFile = null } })
    },
    requestBuild() {
      this.building = true
      router.post(route('eir-feed.build'), this.build, { preserveScroll: true, onFinish: () => { this.building = false } })
    },
    approve(id) {
      if (!confirm('Approve build ' + id + ' and write it? Differences are recorded on the build.')) return
      router.post(route('eir-feed.approve', id), {}, { preserveScroll: true })
    },
  },
}
</script>
