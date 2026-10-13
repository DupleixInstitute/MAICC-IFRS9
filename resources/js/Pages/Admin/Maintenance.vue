<template>
    <app-layout title="Maintenance" description="Run the system's setup, month-end, import and check commands from here, with live output. Every run is audit logged.">
        <template #actions>
            <span class="maiic-badge maiic-badge-green">Admin only</span>
            <span class="maiic-badge maiic-badge-gold">Audit logged</span>
        </template>

        <!-- figures -->
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="maiic-kpi" style="--accent: #15803d"><div class="maiic-kpi-label">Database</div><div class="truncate text-base font-bold text-gray-900">{{ status.database || '-' }}</div></div>
            <div class="maiic-kpi" style="--accent: #0369a1"><div class="maiic-kpi-label">Loan book months</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ status.loan_book_months }}<span class="ml-2 text-xs font-semibold text-gray-500">latest {{ periodLabel(status.latest_month) }}</span></div></div>
            <div class="maiic-kpi" style="--accent: #d97706"><div class="maiic-kpi-label">Months with ECL</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ status.ecl_months }}</div></div>
            <div class="maiic-kpi" :style="{ '--accent': status.running ? '#dc2626' : '#64748b' }"><div class="maiic-kpi-label">Now running</div><div class="truncate text-sm font-bold" :class="status.running ? 'text-red-700' : 'text-gray-500'">{{ status.running ? status.running.label : 'Nothing' }}</div></div>
        </div>

        <p v-if="!can.run" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
            You can see this console, but running a command is restricted to a system administrator.
        </p>

        <!-- tabs -->
        <div class="mb-5 rounded-xl border border-gray-200 bg-white px-5 pt-4 shadow-sm">
            <nav class="flex gap-6 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key"
                        class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold transition-colors"
                        :class="tab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'">
                    {{ t.label }}
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
                </button>
            </nav>
        </div>

        <!-- month picker for the month-end steps -->
        <div v-if="tab === 'month'" class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <label class="text-[11px] font-bold uppercase tracking-wider text-gray-500" for="m-period">Month</label>
            <select id="m-period" v-model="period" class="rounded-lg border-gray-300 py-1.5 pl-3 pr-8 text-sm font-semibold">
                <option v-for="p in periodChoices" :key="p" :value="p">{{ periodLabel(p) }}</option>
            </select>
            <span class="text-xs text-gray-500">Run the steps in order. A new PD run (step 3) clears the month's post-FLI PD, so steps 5 and 6 follow it.</span>
        </div>

        <!-- command cards -->
        <div v-if="tab !== 'history'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="c in cards" :key="c.key" class="flex flex-col rounded-xl border bg-white p-4 shadow-sm"
                 :class="c.danger === 'destructive' ? 'border-red-300' : 'border-gray-200'">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span v-if="tab === 'month' || tab === 'setup'" class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-maiic-600 text-xs font-bold text-white">{{ c.step }}</span>
                        <h3 class="font-bold text-gray-900">{{ c.label }}</h3>
                    </div>
                    <span class="maiic-badge flex-none" :class="dangerBadge(c.danger)">{{ dangerLabel(c.danger) }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ c.description }}</p>
                <code class="mt-3 block truncate rounded bg-gray-50 px-2 py-1 text-[11px] text-gray-600" :title="shownFor(c)">{{ shownFor(c) }}</code>
                <p v-if="c.note" class="mt-2 text-xs text-gray-500">{{ c.note }}</p>
                <div v-if="c.danger === 'destructive'" class="mt-3">
                    <label class="text-[11px] font-bold uppercase tracking-wider text-red-700">Type {{ c.confirm }} to enable</label>
                    <input v-model="typed[c.key]" type="text" class="mt-1 w-full rounded-lg border-red-300 text-sm" :placeholder="c.confirm" autocomplete="off"/>
                </div>
                <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                    <span class="text-[11px] text-gray-400">{{ lastRunText(c) }}</span>
                    <button type="button" @click="start(c)" :disabled="!canStart(c)"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-bold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                            :class="c.danger === 'destructive' ? 'bg-red-600 hover:bg-red-700' : 'bg-maiic-600 hover:bg-maiic-700'">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> Run
                    </button>
                </div>
            </div>
        </div>

        <!-- run history -->
        <div v-else class="maiic-panel">
            <table class="maiic-table">
                <thead><tr><th>Started</th><th>Command</th><th>Run by</th><th>Took</th><th>Status</th><th class="num">Output</th></tr></thead>
                <tbody>
                    <tr v-for="r in pagedRecent" :key="r.id">
                        <td class="whitespace-nowrap">{{ fmt(r.started_at) }}</td>
                        <td><div class="font-semibold text-gray-800">{{ r.label }}</div><code class="text-[11px] text-gray-500">{{ r.command }}</code></td>
                        <td>{{ r.started_by || '-' }}</td>
                        <td>{{ r.duration || '-' }}</td>
                        <td><span class="maiic-badge" :class="statusBadge(r.status)">{{ r.status }}</span></td>
                        <td class="num"><button type="button" class="maiic-action maiic-action-view" title="View the output" @click="view(r.id)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button></td>
                    </tr>
                    <tr v-if="!recent.length"><td colspan="6" class="maiic-empty">No command has been run from this console yet.</td></tr>
                </tbody>
            </table>
            <div v-if="recent.length > 15" class="flex items-center gap-1 border-t border-gray-100 px-4 py-3">
                <button v-for="n in Math.ceil(recent.length / 15)" :key="n" type="button" @click="page = n"
                        class="rounded-lg border px-3 py-1 text-sm font-semibold" :class="page === n ? 'border-maiic-600 bg-maiic-600 text-white' : 'border-gray-200 text-gray-700'">{{ n }}</button>
            </div>
        </div>

        <!-- output panel -->
        <div v-if="run" class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l border-gray-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                <div class="min-w-0">
                    <h3 class="truncate font-bold text-gray-900">{{ run.label }}</h3>
                    <code class="block truncate text-[11px] text-gray-500">{{ run.command }}</code>
                </div>
                <div class="flex flex-none items-center gap-2">
                    <span class="maiic-badge" :class="statusBadge(run.status)">{{ run.status }}{{ run.duration ? ' · ' + run.duration : '' }}</span>
                    <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="run = null">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
            </div>
            <pre ref="out" class="flex-1 overflow-auto whitespace-pre bg-gray-950 px-5 py-4 font-mono text-[12px] leading-relaxed text-gray-100">{{ run.output }}</pre>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
    components: { AppLayout },
    props: { catalog: Array, recent: Array, periods: Array, status: Object, can: Object },
    data() {
        return { tab: 'setup', period: this.periods[0] || null, typed: {}, run: null, timer: null, page: 1 }
    },
    computed: {
        tabs() {
            const n = (g) => this.catalog.filter(c => c.group === g).length
            return [
                { key: 'setup', label: 'First-time setup', count: n('setup') },
                { key: 'month', label: 'Month-end', count: n('month') },
                { key: 'imports', label: 'Imports', count: n('imports') },
                { key: 'diagnostics', label: 'Diagnostics', count: n('diagnostics') },
                { key: 'history', label: 'Run history', count: this.recent.length },
            ]
        },
        cards() {
            return this.catalog.filter(c => c.group === this.tab).sort((a, b) => a.step - b.step)
        },
        // the months the system holds, plus the month after the latest (to build it)
        periodChoices() {
            const next = this.nextMonth(this.periods[0])
            return next ? [next, ...this.periods] : this.periods
        },
        pagedRecent() {
            return this.recent.slice((this.page - 1) * 15, this.page * 15)
        },
    },
    methods: {
        nextMonth(p) {
            if (!p) return null
            const [y, m] = p.split('-').map(Number)
            return m === 12 ? `${y + 1}-01` : `${y}-${String(m + 1).padStart(2, '0')}`
        },
        periodLabel(p) {
            if (!p) return '-'
            const [y, m] = String(p).split('-')
            return m ? `${MONTHS[Number(m) - 1]} ${y}` : p
        },
        shownFor(c) {
            return c.period ? c.shown.replace('{period}', this.period || 'YYYY-MM') : c.shown
        },
        fmt(ts) { return ts ? ts.replace('T', ' ').slice(0, 16) : '-' },
        dangerLabel(d) { return { safe: 'Read only', caution: 'Writes data', destructive: 'Destructive' }[d] || d },
        dangerBadge(d) { return { safe: 'maiic-badge-green', caution: 'maiic-badge-gold', destructive: 'maiic-badge-red' }[d] || 'maiic-badge-grey' },
        statusBadge(s) { return { done: 'maiic-badge-green', running: 'maiic-badge-gold', failed: 'maiic-badge-red' }[s] || 'maiic-badge-grey' },
        lastRunText(c) {
            const r = this.recent.find(x => x.label === c.label || x.label.startsWith(c.label + ' ('))
            return r ? `Last: ${this.fmt(r.started_at)} · ${r.status}` : 'Not run from here yet'
        },
        canStart(c) {
            if (!this.can.run || this.status.running || (this.run && this.run.status === 'running')) return false
            if (c.danger === 'destructive') return (this.typed[c.key] || '') === c.confirm
            if (c.period) return !!this.period
            return true
        },
        xsrf() {
            const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
            return m ? decodeURIComponent(m[1]) : ''
        },
        async start(c) {
            if (c.danger === 'destructive') {
                if (!(await confirmDialog({ title: 'Wipe and rebuild this database?', message: `${c.label}: every record in ${this.status.database} is destroyed and rebuilt. This cannot be undone.`, confirmLabel: 'Wipe and rebuild', tone: 'danger' }))) return
            } else if (c.danger === 'caution') {
                const when = c.period ? ` for ${this.periodLabel(this.period)}` : ''
                if (!(await confirmDialog({ title: `${c.label}${when}?`, message: c.note || c.description, confirmLabel: 'Run' }))) return
            }
            this.run = { label: c.label, command: this.shownFor(c), status: 'running', output: 'Starting...' }
            try {
                const res = await fetch(route('maintenance.run'), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': this.xsrf() },
                    body: JSON.stringify({ key: c.key, confirm: this.typed[c.key] || '', period: c.period ? this.period : null }),
                })
                const j = await res.json().catch(() => ({ ok: false, message: res.status === 403 ? 'Refused: running a command is restricted to a system administrator.' : 'The command could not be started.' }))
                if (!j.ok) { this.run = { ...this.run, status: 'failed', output: j.message }; return }
                this.typed[c.key] = ''
                this.poll(j.id)
            } catch (e) {
                this.run = { ...this.run, status: 'failed', output: 'The command could not be started.' }
            }
        },
        poll(id) {
            const tick = async () => {
                try {
                    const j = await (await fetch(route('maintenance.output', id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()
                    if (j.ok) {
                        this.run = { ...j.run, output: j.run.output || '...' }
                        this.$nextTick(() => { if (this.$refs.out) this.$refs.out.scrollTop = this.$refs.out.scrollHeight })
                        if (j.run.status === 'running') { this.timer = setTimeout(tick, 1500); return }
                        router.reload({ only: ['recent', 'status', 'periods'] })
                    }
                } catch (e) { this.timer = setTimeout(tick, 3000) }
            }
            tick()
        },
        async view(id) {
            this.run = { label: '...', command: '', status: 'running', output: 'Loading...' }
            const j = await (await fetch(route('maintenance.output', id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })).json()
            if (j.ok) this.run = { ...j.run, output: j.run.output || '(no output)' }
        },
    },
    beforeUnmount() { if (this.timer) clearTimeout(this.timer) },
}
</script>
