<template>
  <app-layout title="Audit Trace" description="Pick a contract to see its full history, oldest to newest, and the settings each of its months was calculated under">
    <template #actions>
      <Link v-if="contract" :href="route('audit-trace.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">New trace</Link>
    </template>

    <div class="space-y-5">
      <!-- Search panel -->
      <form class="maiic-panel p-5" @submit.prevent="go">
        <div class="flex flex-col gap-3 md:flex-row md:items-end">
          <div class="md:w-96">
            <label for="trace-contract" class="maiic-flabel">Contract number</label>
            <input id="trace-contract" v-model="form.contract" list="trace-contracts" class="maiic-input" placeholder="Type or pick a contract number, e.g. 104420000005" autocomplete="off"/>
            <datalist id="trace-contracts"><option v-for="s in suggestions" :key="s.id" :value="s.id">{{ s.name }}</option></datalist>
          </div>
          <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50" :disabled="!form.contract || !form.contract.trim()">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.45 4.39l3.08 3.08a.75.75 0 11-1.06 1.06l-3.08-3.08A7 7 0 012 9z" clip-rule="evenodd"/></svg>
              Trace
            </button>
          </div>
          <p class="text-xs text-gray-500 md:ml-auto md:max-w-sm">{{ suggestions.length.toLocaleString('en-GB') }} contracts on file. Start typing a contract number or customer name and pick from the list.</p>
        </div>
      </form>

      <!-- Empty state: nothing traced yet -->
      <div v-if="!contract" class="maiic-panel px-6 py-12 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-maiic-50 text-maiic-600">
          <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-base font-bold text-gray-900">Trace one contract from start to finish</h3>
        <p class="mx-auto mt-1 max-w-xl text-sm text-gray-500">Enter a contract number above and press Trace. You will see:</p>
        <div class="mx-auto mt-5 grid max-w-4xl gap-3 text-left sm:grid-cols-3">
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-bold text-maiic-800">Contract details</div>
            <p class="mt-1 text-xs text-gray-600">Customer, product, GL account, origination date, effective interest rate and approval status.</p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-bold text-maiic-800">Timeline of events</div>
            <p class="mt-1 text-xs text-gray-600">Every schedule version, rate reset, fee, EIR calculation, monthly roll-forward and logged user action, oldest first.</p>
          </div>
          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-bold text-maiic-800">Month by month</div>
            <p class="mt-1 text-xs text-gray-600">Each reporting month's stage, days past due, PD, LGD and ECL, and the settings that month was calculated under.</p>
          </div>
        </div>
      </div>

      <template v-else>
        <!-- Contract facts -->
        <div class="maiic-panel p-5">
          <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h3 class="text-lg font-bold text-gray-900">Contract {{ facts?.contract_id || contract }}</h3>
            <span v-if="facts?.customer_name" class="text-sm text-gray-600">{{ facts.customer_name }}</span>
            <span v-if="facts?.schedule_status" class="maiic-badge" :class="facts.schedule_status === 'approved' ? 'maiic-badge-green' : 'maiic-badge-gold'">Schedule {{ humanise(facts.schedule_status).toLowerCase() }}</span>
            <span v-if="facts?.locked_at" class="maiic-badge maiic-badge-grey">Locked {{ facts.locked_at }}</span>
          </div>
          <dl v-if="facts && facts.customer_name !== undefined" class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 lg:grid-cols-6">
            <div><dt class="maiic-flabel">Product</dt><dd class="text-gray-900">{{ facts.product_type || '-' }}</dd></div>
            <div><dt class="maiic-flabel">GL account</dt><dd class="text-gray-900">{{ facts.gl || '-' }}</dd></div>
            <div><dt class="maiic-flabel">Originated</dt><dd class="text-gray-900">{{ facts.origination_date || '-' }}</dd></div>
            <div><dt class="maiic-flabel">Effective rate (annual)</dt><dd class="font-semibold tabular-nums text-gray-900">{{ facts.eir != null ? (Number(facts.eir) * 100).toFixed(4) + '%' : '-' }}</dd></div>
            <div><dt class="maiic-flabel">EIR status</dt><dd class="text-gray-900">{{ facts.calculation_status ? humanise(facts.calculation_status) : '-' }}</dd></div>
            <div><dt class="maiic-flabel">Months on file</dt><dd class="font-semibold tabular-nums text-gray-900">{{ months.length }}</dd></div>
          </dl>
          <p v-else class="mt-2 text-sm text-amber-700">This contract number is not in the EIR contract master. Any events and months found for it are shown below.</p>
        </div>

        <!-- Nothing found -->
        <div v-if="!events.length && !months.length" class="maiic-panel px-6 py-10 text-center">
          <h3 class="text-base font-bold text-gray-900">Nothing recorded for {{ contract }}</h3>
          <p class="mx-auto mt-1 max-w-lg text-sm text-gray-500">No schedule, fee, EIR, roll-forward, loan book month or logged action names this contract. Check the number, or pick one from the list in the search box.</p>
        </div>

        <!-- Results in tabs -->
        <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <!-- One view switch (the Audit section row above is the page's only tab row) -->
          <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 md:flex-row md:items-center md:justify-between">
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1" role="group" aria-label="View">
              <button v-for="t in tabs" :key="t.key" type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold transition"
                      :class="tab === t.key ? 'bg-white text-maiic-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'" :aria-pressed="tab === t.key" @click="tab = t.key">
                {{ t.label }}
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count.toLocaleString('en-GB') }}</span>
              </button>
            </div>
            <div v-if="tab === 'events'" class="flex items-center gap-2">
              <label for="trace-source" class="text-xs font-bold uppercase tracking-wider text-gray-500">Type of event</label>
              <select id="trace-source" v-model="sourceFilter" class="maiic-select w-56 py-1.5" @change="eventPage = 1">
                <option value="">All ({{ events.length }})</option>
                <option v-for="s in sources" :key="s.key" :value="s.key">{{ s.label }} ({{ s.count }})</option>
              </select>
            </div>
          </div>

          <!-- Timeline -->
          <div v-if="tab === 'events'">
            <p class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500">Everything that happened to this contract, oldest first.</p>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>When</th><th>Event</th><th>Detail</th><th>Type</th><th>By</th></tr></thead>
                <tbody>
                  <tr v-for="(e, i) in pagedEvents" :key="i" class="align-top">
                    <td class="whitespace-nowrap tabular-nums text-xs">{{ e.at }}</td>
                    <td class="font-semibold">{{ humanise(e.what) }}</td>
                    <td class="max-w-xl text-xs text-gray-700"><span class="break-words">{{ e.detail }}</span></td>
                    <td><span class="maiic-badge" :class="sourceBadge(e.source)">{{ sourceLabel(e.source) }}</span></td>
                    <td class="whitespace-nowrap text-xs">{{ e.who || 'System' }}</td>
                  </tr>
                  <tr v-if="!filteredEvents.length"><td colspan="5" class="maiic-empty">No events of this type.</td></tr>
                </tbody>
              </table>
            </div>
            <div v-if="filteredEvents.length > 15" class="border-t border-gray-100 p-4"><ClientPager v-model="eventPage" :total="filteredEvents.length"/></div>
          </div>

          <!-- Months -->
          <div v-else>
            <p class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500">Each reporting month's figures. Open a month (eye icon) to see the settings it was calculated under ({{ months[0]?.governance_source }}).</p>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Period</th><th>How the month was built</th><th>Stage</th><th class="num">Days past due</th><th class="num">Carrying amount</th><th class="num">PD before FLI</th><th class="num">PD after FLI</th><th class="num">LGD</th><th class="num">ECL</th><th>Forward-looking adjustment</th><th class="num">Settings</th></tr></thead>
                <tbody>
                  <template v-for="m in pagedMonths" :key="m.period">
                    <tr class="align-top">
                      <td class="whitespace-nowrap font-semibold">{{ m.period }}<span v-if="m.locked" class="maiic-badge maiic-badge-grey ml-2">Locked</span></td>
                      <td class="text-xs">{{ humanise(m.build) || '-' }}</td>
                      <td><span class="maiic-badge" :class="stageBadge(m.stage)">{{ m.stage ? 'Stage ' + m.stage : '-' }}</span></td>
                      <td class="num">{{ m.dpd ?? '-' }}</td>
                      <td class="num">{{ fmt(m.carrying) }}</td>
                      <td class="num">{{ pct(m.pd_pre) }}</td>
                      <td class="num">{{ pct(m.pd_post) }}</td>
                      <td class="num">{{ pct(m.lgd) }}</td>
                      <td class="num font-semibold">{{ fmt(m.ecl) }}</td>
                      <td class="text-xs">{{ humanise(m.fli) || '-' }}</td>
                      <td class="num">
                        <button type="button" class="maiic-action maiic-action-view" :title="open === m.period ? 'Hide settings' : 'Show the settings this month ran under'" @click="open = open === m.period ? null : m.period">
                          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.66 10.59a1.65 1.65 0 010-1.18 10 10 0 0118.68 0c.15.38.15.8 0 1.18a10 10 0 01-18.68 0zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="open === m.period">
                      <td colspan="11" class="bg-maiic-50/40">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Settings for {{ m.period }} ({{ m.governance_source }})</div>
                        <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-xs sm:grid-cols-2 lg:grid-cols-4">
                          <div v-for="(v, k) in m.governance" :key="k" class="flex justify-between gap-3 border-b border-gray-100 py-1">
                            <dt class="text-gray-500">{{ settingLabel(k) }}</dt><dd class="font-semibold text-gray-900">{{ v === null || v === '' ? 'Not set' : humanise(String(v)) }}</dd>
                          </div>
                        </dl>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="!months.length"><td colspan="11" class="maiic-empty">This contract has no loan book months yet.</td></tr>
                </tbody>
              </table>
            </div>
            <div v-if="months.length > 15" class="border-t border-gray-100 p-4"><ClientPager v-model="monthPage" :total="months.length"/></div>
          </div>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import ClientPager from '@/Components/ClientPager.vue'
import { Link, router } from '@inertiajs/vue3'

// Plain-English names for the record sources and settings the trace reads.
const SOURCES = {
  'audit log': 'User action',
  contract_cashflow_schedule: 'Schedule',
  rate_reset_events: 'Rate reset',
  contract_fees: 'Fee',
  eir_calculation_history: 'EIR calculation',
  eir_amortisation: 'Roll-forward',
}
const SETTINGS = {
  loan_book_build_method: 'Loan book build method',
  dpd_basis: 'Days past due basis',
  stage3_missed_instalments: 'Stage 3 after missed instalments',
  stage3_interest_basis: 'Stage 3 interest basis',
  fli_adjustment_route: 'FLI adjustment route',
  fli_transmission_method: 'FLI transmission method',
  scenario_weighting_method: 'Scenario weighting method',
  plr_mid_period: 'Prime lending rate mid-period',
}

export default {
  components: { AppLayout, ClientPager, Link },
  props: { contract: String, facts: Object, events: Array, months: Array, suggestions: Array },
  data() {
    return { form: { contract: this.contract }, tab: 'events', sourceFilter: '', eventPage: 1, monthPage: 1, open: null }
  },
  computed: {
    tabs() {
      return [
        { key: 'events', label: 'Timeline', count: this.events.length },
        { key: 'months', label: 'Month by month', count: this.months.length },
      ]
    },
    sources() {
      const counts = {}
      this.events.forEach(e => { counts[e.source] = (counts[e.source] || 0) + 1 })
      return Object.keys(counts).map(k => ({ key: k, label: this.sourceLabel(k), count: counts[k] }))
    },
    filteredEvents() { return this.sourceFilter ? this.events.filter(e => e.source === this.sourceFilter) : this.events },
    pagedEvents() { return this.filteredEvents.slice((this.eventPage - 1) * 15, this.eventPage * 15) },
    pagedMonths() { return this.months.slice((this.monthPage - 1) * 15, this.monthPage * 15) },
  },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    pct(v) { return v == null ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    humanise(s) {
      if (!s) return ''
      const t = String(s).replace(/_/g, ' ')
      // Codes stored in capitals (PENDING, NOT_GENERATED) read as sentences.
      return (t === t.toUpperCase() ? t.toLowerCase() : t).replace(/^./, c => c.toUpperCase())
    },
    sourceLabel(s) { return SOURCES[s] || this.humanise(s) },
    sourceBadge(s) { return s === 'audit log' ? 'maiic-badge-gold' : s === 'rate_reset_events' ? 'maiic-badge-red' : s === 'eir_amortisation' ? 'maiic-badge-grey' : 'maiic-badge-green' },
    stageBadge(s) { return String(s) === '3' ? 'maiic-badge-red' : String(s) === '2' ? 'maiic-badge-gold' : 'maiic-badge-green' },
    settingLabel(k) { return SETTINGS[k] || this.humanise(k) },
    go() {
      const c = (this.form.contract || '').trim()
      if (c) router.get(route('audit-trace.index'), { contract: c })
    },
  },
}
</script>
