<template>
  <app-layout title="Forward-Looking Overview" description="The month's forward-looking close, step by step: what each step needs, where it stands, and the button that runs it">
    <template #actions>
      <select v-model="form.period" @change="go" class="maiic-input !w-40" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <button v-if="canGovern" type="button" class="primary-btn text-sm" :disabled="busy" @click="start('fli-all')" title="Apply the route, project the scenarios, calculate the ECL and record the month, stopping at the first step that needs a person">
        Run the remaining steps
      </button>
    </template>

    <div class="space-y-4">
      <KpiRow :cards="kpis" />

      <!-- a month-end stopped part-way: the ECL and the record on screen are not current -->
      <div v-if="currency && currency.mid_run" role="alert" class="maiic-panel border-l-4 !border-l-red-600 bg-red-50 px-5 py-3 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200">
        <span class="font-semibold">The month is mid-run.</span>&nbsp;{{ currency.message }}
      </div>
      <!-- no model version in force, the governed setting lets the month proceed with the PD held -->
      <div v-if="withoutModel" role="status" class="maiic-panel border-l-4 !border-l-amber-500 bg-amber-50 px-5 py-3 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-200">
        <span class="font-semibold">No forward-looking model in force.</span>&nbsp;{{ withoutModel }}
      </div>

      <div v-if="next" class="maiic-panel border-l-4 !border-l-maiicgold-500 px-5 py-3 text-sm">
        <span class="font-semibold text-gray-900">Next: {{ next.title }}.</span>&nbsp;<span class="text-gray-600">{{ next.status }}</span>
      </div>
      <div v-else class="maiic-panel border-l-4 !border-l-maiic-600 bg-maiic-50/60 px-5 py-3 text-sm font-semibold text-maiic-800">
        Every step of {{ monthLabel(period) }} is done.
      </div>

      <!-- the ten steps -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">The month-end, in order</h3><p class="text-xs text-gray-500">The approvals stay with people; the other steps run here in the background, and the output opens on the right.</p></div>
        <ol class="divide-y divide-gray-100 dark:divide-slate-700">
          <li v-for="(s, i) in steps" :key="s.key" class="flex flex-wrap items-center gap-3 px-5 py-3">
            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-bold" :class="dot(s.state)">{{ s.state === 'done' ? '✓' : i + 1 }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-gray-900">{{ s.title }}</span><span class="maiic-badge" :class="badge(s.state)">{{ stateLabel(s.state) }}</span></div>
              <div class="text-xs text-gray-600">{{ s.status }}</div>
            </div>
            <div class="flex flex-none items-center gap-1.5">
              <Link v-if="s.screen && s.screen !== 'fli.overview'" :href="route(s.screen, screenParams(s))" class="secondary-btn !px-3 !py-1 text-xs">Open</Link>
              <button v-if="canGovern && s.command && s.state !== 'blocked'" type="button" :disabled="busy" @click="start(s.command)"
                      class="primary-btn !px-3 !py-1 text-xs">{{ s.state === 'done' ? 'Run again' : 'Run' }}</button>
            </div>
          </li>
        </ol>
      </div>

      <!-- approvals waiting for a second person -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Waiting for a second person</h3><p class="text-xs text-gray-500">{{ pending.length }} item(s). Maker-checker: you cannot approve what you proposed.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>What</th><th>Proposed by</th><th></th></tr></thead>
          <tbody>
            <tr v-for="p in pending" :key="p.kind + p.id">
              <td><div class="font-semibold text-gray-800">{{ p.title }}</div><div v-if="p.note" class="text-xs text-gray-500">{{ p.note }}</div></td>
              <td>{{ p.proposer || '-' }}</td>
              <td class="num whitespace-nowrap">
                <span v-if="p.own" class="text-xs text-gray-400">your proposal</span>
                <button v-else-if="canGovern" type="button" class="maiic-action maiic-action-view" :title="'Approve ' + p.title" @click="approve(p)"><font-awesome-icon icon="check" /></button>
              </td>
            </tr>
            <tr v-if="!pending.length"><td colspan="3" class="maiic-empty">Nothing is waiting for approval this month.</td></tr>
          </tbody></table></div>
      </div>

      <!-- the month's record and its sign-off -->
      <div v-if="snapshot" class="maiic-panel">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div>
            <h3 class="font-semibold text-gray-900">The month's record <span class="maiic-badge ml-1" :class="snapshot.status === 'SIGNED_OFF' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ snapshot.status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded' }}</span>
              <span v-if="snapshot.stale" class="maiic-badge maiic-badge-red ml-1" :title="snapshot.stale">Not current: the month is mid-run</span>
              <span v-if="snapshot.without_model" class="maiic-badge maiic-badge-gold ml-1">No forward-looking model in force</span></h3>
            <p class="text-xs text-gray-500">Recorded {{ snapshot.created_at }} by {{ snapshot.recorder || '-' }}<span v-if="snapshot.signed_off_at"> · signed off {{ snapshot.signed_off_at }} by {{ snapshot.signer || snapshot.approver_label }}</span></p>
          </div>
          <Link :href="route('fli.impact', { period })" class="secondary-btn !px-3 !py-1 text-xs">Impact Analysis</Link>
        </div>
        <div class="grid gap-4 p-5 lg:grid-cols-2">
          <table class="maiic-table">
            <tbody>
              <tr><td>ECL before FLI</td><td class="num">{{ mwk(snapshot.pre_fli_ecl) }}</td></tr>
              <tr><td>Forward-looking effect of the model</td><td class="num">{{ mwk(snapshot.model_effect) }}</td></tr>
              <tr><td>Manual overlays</td><td class="num">{{ mwk(snapshot.overlay_effect) }}</td></tr>
              <tr class="font-semibold"><td>ECL, engine measure (undiscounted)</td><td class="num">{{ mwk(snapshot.booked_ecl) }}</td></tr>
              <tr><td>Weighted over the scenarios</td><td class="num">{{ mwk(snapshot.weighted_ecl) }}</td></tr>
              <tr><td>Difference</td><td class="num" :class="snapshot.reconciles ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">{{ mwk(snapshot.difference) }} {{ snapshot.reconciles ? '(ties)' : '(does not tie)' }}</td></tr>
              <template v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined">
                <tr><td colspan="2" class="!pt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-slate-400">Discounted at the EIR (IFRS 9 5.5.17)</td></tr>
                <tr v-if="Number(snapshot.discount_unresolved_loans) > 0"><td>Less: loans not discounted ({{ snapshot.discount_unresolved_loans }})</td><td class="num">{{ mwk(-Number(snapshot.unresolved_ecl)) }}</td></tr>
                <tr><td>Projection effect (EAD run down the schedule)</td><td class="num">{{ mwk(snapshot.projection_effect) }}</td></tr>
                <tr><td>Discounting effect</td><td class="num">{{ mwk(snapshot.discounting_effect) }}</td></tr>
                <tr class="font-semibold"><td>ECL booked, discounted ({{ snapshot.discounted_loans }} loans)</td><td class="num">{{ mwk(snapshot.booked_ecl_discounted) }}</td></tr>
                <tr><td>Weighted over the scenarios, discounted</td><td class="num">{{ mwk(snapshot.weighted_ecl_discounted) }}</td></tr>
                <tr><td>Difference</td><td class="num" :class="snapshot.discounted_reconciles ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">{{ mwk(snapshot.discounted_difference) }} {{ snapshot.discounted_reconciles ? '(ties)' : '(does not tie)' }}</td></tr>
              </template>
              <tr v-if="Number(snapshot.programme_loans) > 0"><td>Mega Farm programme, its own engine (D30), not in the figures above ({{ snapshot.programme_loans }} loans)</td><td class="num">{{ mwk(snapshot.programme_ecl) }}</td></tr>
              <tr v-if="snapshot.movement_pct !== null"><td>Against the previous month-end</td><td class="num" :class="snapshot.needs_commentary ? 'text-red-700 font-semibold' : ''">{{ (Number(snapshot.movement_pct) * 100).toFixed(1) }}%</td></tr>
            </tbody>
          </table>
          <div>
            <table class="maiic-table">
              <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">ECL</th><th v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined" class="num">Discounted</th></tr></thead>
              <tbody><tr v-for="r in snapshot.per_scenario || []" :key="r.scenario"><td>{{ r.scenario }}</td><td class="num">{{ r.weight }}%</td><td class="num">{{ mwk(r.ecl) }}</td><td v-if="snapshot.booked_ecl_discounted !== null && snapshot.booked_ecl_discounted !== undefined" class="num">{{ mwk(r.ecl_discounted) }}</td></tr></tbody>
            </table>
            <div v-if="Number(snapshot.discount_unresolved_loans) > 0" class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-200">
              {{ snapshot.discount_unresolved_loans }} loan(s) have no discounted allowance, so the discounted figure covers part of the book; signing off needs a commentary saying why.
              <ul class="mt-1 list-disc pl-4"><li v-for="u in (snapshot.discount_detail?.unresolved || []).slice(0, 8)" :key="u.contract_id">{{ u.contract_id }}: {{ u.reason || u.status }}</li></ul>
            </div>
            <div v-if="snapshot.status === 'RECORDED' && canGovern" class="mt-3 space-y-2">
              <label class="text-xs font-semibold text-gray-700" for="commentary">Commentary<span v-if="snapshot.needs_commentary" class="text-red-700 dark:text-red-300"> (required: the movement is above {{ tolerance !== null ? (tolerance * 100).toFixed(0) + '%' : 'the tolerance' }})</span><span v-else-if="snapshot.without_model" class="text-red-700 dark:text-red-300"> (required: no forward-looking model was in force; say so)</span><span v-else-if="Number(snapshot.discount_unresolved_loans) > 0" class="text-red-700 dark:text-red-300"> (required: loans are not discounted)</span></label>
              <textarea id="commentary" v-model="commentary" rows="3" class="maiic-input w-full" placeholder="Why the ECL moved, and what the forward-looking information added"></textarea>
              <div class="flex justify-end gap-2">
                <button type="button" class="secondary-btn text-sm" :disabled="!commentary.trim()" @click="saveComment">Save commentary</button>
                <button type="button" class="primary-btn text-sm" :disabled="snapshot.created_by === userId || !snapshot.reconciles || snapshot.discounted_reconciles === false || !!snapshot.stale || (snapshot.without_model && !commentary.trim())" :title="snapshot.created_by === userId ? 'A second person signs off the month' : (snapshot.stale ? snapshot.stale : (snapshot.without_model && !commentary.trim() ? 'No forward-looking model was in force: a commentary saying so is needed' : ''))" @click="signOff">Sign off the month</button>
              </div>
            </div>
            <p v-else-if="snapshot.commentary" class="mt-3 rounded bg-gray-50 p-3 text-xs text-gray-700 dark:bg-slate-900/60">{{ snapshot.commentary }}</p>
          </div>
        </div>
      </div>

      <!-- runs from here -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Recent runs</h3></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Started</th><th>Step</th><th>Run by</th><th>Took</th><th>Status</th><th class="num">Output</th></tr></thead>
          <tbody>
            <tr v-for="r in recent" :key="r.id">
              <td class="whitespace-nowrap">{{ fmt(r.started_at) }}</td><td>{{ r.label }}</td><td>{{ r.started_by || '-' }}</td><td>{{ r.duration || '-' }}</td>
              <td><span class="maiic-badge" :class="runBadge(r.status)">{{ r.status }}</span></td>
              <td class="num"><button type="button" class="maiic-action maiic-action-view" title="View the output" @click="view(r.id)"><font-awesome-icon icon="eye" /></button></td>
            </tr>
            <tr v-if="!recent.length"><td colspan="6" class="maiic-empty">No step has been run from here yet.</td></tr>
          </tbody></table></div>
      </div>
    </div>

    <!-- output panel -->
    <div v-if="run" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
      <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-700">
        <div class="min-w-0"><h3 class="truncate font-bold text-gray-900">{{ run.label }}</h3><code class="block truncate text-[11px] text-gray-500">{{ run.command }}</code></div>
        <div class="flex flex-none items-center gap-2">
          <span class="maiic-badge" :class="runBadge(run.status)">{{ run.status }}{{ run.duration ? ' · ' + run.duration : '' }}</span>
          <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="run = null"><font-awesome-icon icon="times" /></button>
        </div>
      </div>
      <pre ref="out" class="flex-1 overflow-auto whitespace-pre bg-gray-950 px-5 py-4 font-mono text-[12px] leading-relaxed text-gray-100">{{ run.output }}</pre>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import { Link, router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, KpiRow, Link },
  props: { period: String, periods: Array, steps: Array, next: Object, pending: Array, snapshot: Object, tolerance: Number, recent: Array, running: Object, commands: Object, canGovern: Boolean, userId: Number, withoutModel: { type: String, default: null }, currency: { type: Object, default: null } },
  data() { return { form: { period: this.period }, run: null, timer: null, commentary: this.snapshot?.commentary || '' } },
  computed: {
    busy() { return !!this.running || (this.run && this.run.status === 'running') },
    kpis() {
      const s = this.snapshot
      const step = (k) => this.steps.find(x => x.key === k)
      const done = this.steps.filter(x => x.state === 'done').length
      return [
        { label: 'Steps done', value: done + ' of ' + this.steps.length, raw: true, accent: '#15803d', bar: done / this.steps.length * 100 },
        { label: 'Model in force', value: step('model')?.version_id ? ((step('model')?.status || '').split(':')[0] || '-') : (step('model')?.without_model ? 'None: the PD holds' : 'None'), raw: true, accent: '#0284c7', hint: step('model')?.status },
        { label: 'Scenario set', value: step('set')?.state === 'done' ? 'Approved' : 'Not approved', raw: true, accent: '#7c3aed', hint: step('set')?.status },
        { label: s && s.booked_ecl_discounted !== null && s.booked_ecl_discounted !== undefined ? 'ECL booked, discounted' : 'ECL booked', value: s ? this.short(s.booked_ecl_discounted ?? s.booked_ecl) : '-', raw: true, accent: '#d97706', sub: s ? 'FLI effect ' + this.short(Number(s.model_effect) + Number(s.overlay_effect)) + ' (undiscounted)' + (Number(s.discount_unresolved_loans) > 0 ? '; ' + s.discount_unresolved_loans + ' loans not discounted' : '') : 'month not recorded' },
        { label: 'Waiting for approval', value: this.pending.length, accent: this.pending.length ? '#dc2626' : '#64748b' },
      ]
    },
  },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go() { router.get(route('fli.overview'), { period: this.form.period }) },
    screenParams(s) { return s.screen === 'macro-statistics.index' ? { tab: 'dashboard' } : { period: this.period } },
    dot(state) { return { done: 'bg-maiic-600 text-white', todo: 'bg-maiicgold-400 text-maiic-900', blocked: 'bg-gray-200 text-gray-500', warn: 'bg-amber-200 text-amber-900', optional: 'bg-sky-100 text-sky-800' }[state] || 'bg-gray-200' },
    badge(state) { return { done: 'maiic-badge-green', todo: 'maiic-badge-gold', blocked: 'maiic-badge-grey', warn: 'maiic-badge-gold', optional: 'maiic-badge-grey' }[state] || 'maiic-badge-grey' },
    stateLabel(state) { return { done: 'Done', todo: 'To do', blocked: 'Waiting on an earlier step', warn: 'Done, with a warning', optional: 'Optional' }[state] || state },
    runBadge(s) { return { done: 'maiic-badge-green', running: 'maiic-badge-gold', failed: 'maiic-badge-red' }[s] || 'maiic-badge-grey' },
    fmt(ts) { return ts ? ts.replace('T', ' ').slice(0, 16) : '-' },
    mwk(v) { return v === null || v === undefined ? '-' : 'MWK ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    short(v) { if (v === null || v === undefined || isNaN(v)) return '-'; const n = Number(v); const a = Math.abs(n); const s = a >= 1e9 ? (a / 1e9).toFixed(2) + 'bn' : a >= 1e6 ? (a / 1e6).toFixed(1) + 'm' : a.toLocaleString(); return (n < 0 ? '(' : '') + 'MWK ' + s + (n < 0 ? ')' : '') },
    xsrf() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : '' },
    async start(key) {
      if (!(await confirmDialog({ title: `${this.commands[key]} for ${this.monthLabel(this.period)}?`, message: key === 'fli-all' ? 'Runs every automated step that is not done, in order, and stops at the first that needs a person.' : 'It runs in the background; the output opens on the right.', confirmLabel: 'Run' }))) return
      this.run = { label: this.commands[key], command: '', status: 'running', output: 'Starting...' }
      try {
        const res = await fetch(route('fli.run'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': this.xsrf() }, body: JSON.stringify({ key, period: this.period }) })
        const j = await res.json().catch(() => ({ ok: false, message: res.status === 403 ? 'Refused: running a step needs the governance permission.' : 'The step could not be started.' }))
        if (!j.ok) { this.run = { ...this.run, status: 'failed', output: j.message }; return }
        this.run.command = j.command
        this.poll(j.id)
      } catch (e) { this.run = { ...this.run, status: 'failed', output: 'The step could not be started.' } }
    },
    poll(id) {
      const tick = async () => {
        try {
          const j = await (await fetch(route('fli.run.output', id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()
          if (j.ok) {
            this.run = { ...j.run, output: j.run.output || '...' }
            this.$nextTick(() => { if (this.$refs.out) this.$refs.out.scrollTop = this.$refs.out.scrollHeight })
            if (j.run.status === 'running') { this.timer = setTimeout(tick, 1500); return }
            router.reload({ preserveScroll: true })
          }
        } catch (e) { this.timer = setTimeout(tick, 3000) }
      }
      tick()
    },
    async view(id) {
      this.run = { label: '...', command: '', status: 'running', output: 'Loading...' }
      const j = await (await fetch(route('fli.run.output', id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()
      if (j.ok) this.run = { ...j.run, output: j.run.output || '(no output)' }
    },
    async approve(p) {
      if (!(await confirmDialog({ title: 'Approve this?', message: p.title + (p.note ? '. ' + p.note : ''), confirmLabel: 'Approve' }))) return
      router.post(route(p.route, p.id), {}, { preserveScroll: true })
    },
    saveComment() { router.post(route('fli.comment', this.snapshot.id), { commentary: this.commentary }, { preserveScroll: true }) },
    async signOff() {
      if (!(await confirmDialog({ title: `Sign off ${this.monthLabel(this.period)}?`, message: 'The record of the month, its scenarios and its reconciliation are signed off in your name.', confirmLabel: 'Sign off' }))) return
      router.post(route('fli.sign-off', this.snapshot.id), { commentary: this.commentary }, { preserveScroll: true })
    },
  },
  beforeUnmount() { if (this.timer) clearTimeout(this.timer) },
}
</script>
