<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/Shell/EmptyState.vue';
import StatCard from '@/Components/Shell/StatCard.vue';
import Icon from '@/Components/Shell/Icon.vue';
import { fmtPct, fmtInt } from '@/Components/Charts/format';

/*
 * FLI / Correlation Finder cockpit - the relationship register with each latest
 * fit verdict (applied / declined + reason, shown honestly), the ranked
 * suggestion sweep WITH its persisted pre-fit diagnostics (native Dickey-Fuller
 * stationarity, normality shape, recommended method/transform), and a queued
 * "Run Auto-Correlate" trigger with a LIVE progress panel: the page polls the
 * server progress feed while the sweep iterates the (X x Y x lag) grid, so the
 * lag iterations are visible as they happen. All figures are server-computed.
 */
const props = defineProps({
    relationships:   { type: Array, default: () => [] },
    suggestions:     { type: Array, default: () => [] },
    verdicts:        { type: Object, default: () => ({ applied: 0, declined: 0, pending: 0, total: 0 }) },
    sweep:           { type: Object, default: () => ({ pairs: 0, lags: 0, iterations: 0, max_history_years: 0, run_id: null }) },
    flash:           { type: Object, default: () => ({}) },
    queueConnection: { type: String, default: 'database' },
    notes:           { type: Array, default: () => [] },
});

const form = useForm({ period: '' });

/* RBAC (SYSTEM_DESIGN 10.2) - UI convenience only; the server gate is the real
 * control. Running auto-correlate is a User+Admin action. */
const canWrite = computed(() => {
    const role = usePage().props.auth?.user?.role ?? 'Viewer';
    return role === 'User' || role === 'Admin';
});
const isAdmin = computed(() => (usePage().props.auth?.user?.role ?? '') === 'Admin');

/* Approve a provisional/'either' expected sign into a governed one (Admin). */
function approveSign(rel, sign) {
    if (!isAdmin.value) return;
    router.post(route('calc.fli.approve-sign', rel.id), { expected_sign: sign }, { preserveScroll: true });
}
const signStatusClass = (s) => (s === 'approved'
    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30'
    : 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30');

/* ---- Live progress (polls the server feed while a run is in flight) ---- */
const progress = ref(null);
let pollTimer = null;
const activeStates = ['queued', 'running', 'fitting'];
const isActive = computed(() => progress.value && activeStates.includes(progress.value.state));

async function fetchProgress() {
    try {
        const res = await fetch(route('calc.fli.progress'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const wasActive = isActive.value;
        progress.value = data;
        if (activeStates.includes(data.state)) {
            schedulePoll();
        } else if (wasActive && (data.state === 'complete' || data.state === 'failed')) {
            // Run just finished: refresh the server-computed tables once.
            router.reload({ only: ['suggestions', 'relationships', 'verdicts'] });
        }
    } catch (e) { /* transient poll failure - try again on the next tick */ schedulePoll(); }
}
function schedulePoll() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(fetchProgress, 1500);
}
onMounted(fetchProgress);
onBeforeUnmount(() => clearTimeout(pollTimer));

function runAutoCorrelate() {
    if (!canWrite.value) return;
    form.post(route('calc.fli.auto-correlate'), {
        preserveScroll: true,
        onSuccess: () => { progress.value = { state: 'queued', message: 'Auto-Correlate queued; waiting for a worker.' }; schedulePoll(); },
    });
}

/* ---- Diagnostics (expandable per suggestion) ---- */
const openDiag = ref(null);
function toggleDiag(i) { openDiag.value = openDiag.value === i ? null : i; }

const stationarityClass = (v) => ({
    stationary:     'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    borderline:     'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    non_stationary: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
}[v] || 'bg-gray-50 text-gray-500 ring-gray-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700');

const suggestionVerdictClass = (v) => ({
    recommended:        'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    usable_with_caveat: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30',
    rejected:           'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
}[v] || 'bg-gray-50 text-gray-500 ring-gray-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700');

const num = (v, dp = 3) => (v === null || v === undefined ? '-' : Number(v).toFixed(dp));
/* R-squared shown as a percentage to 2 decimals (0.997 -> 99.70%). */
const pctR2 = (v) => (v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%');
const fmtInt2 = (v) => Number(v ?? 0).toLocaleString('en-US');
/* Frequency of a dataset, abbreviated (Annual / Quarterly / Monthly). */
const freqAbbr = (f) => ({ annual: 'A', quarterly: 'Q', monthly: 'M' }[f] || (f ? f[0].toUpperCase() : '-'));
/* Pair frequency label: one letter if X and Y match, else "X/Y". */
const pairFreq = (fx, fy) => (fx && fy && fx === fy ? freqAbbr(fx) : `${freqAbbr(fx)}/${freqAbbr(fy)}`);
const verdictClass = (v) => ({
    applied:  'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30',
    declined: 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30',
}[v] || 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700');
const queued = computed(() => props.flash && props.flash.auto);
</script>

<template>
    <Head title="FLI / Correlation Finder" />

    <AppLayout description="Forward-looking correlation finder: relationship fits with honest verdicts, ranked suggestions with stationarity diagnostics, and a live auto-correlate run.">
        <div class="space-y-6">
            <!-- LIVE PROGRESS PANEL: visible whenever a run is queued/running/fitting,
                 and shows the completion summary when it finishes. -->
            <div v-if="progress && progress.state !== 'idle'" class="rounded-2xl border p-5 shadow-sm"
                :class="progress.state === 'failed'
                    ? 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10'
                    : (progress.state === 'complete'
                        ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                        : 'border-indigo-200 bg-indigo-50/70 dark:border-indigo-500/30 dark:bg-indigo-500/10')">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span v-if="isActive" class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-indigo-500"></span>
                        </span>
                        <Icon v-else :name="progress.state === 'failed' ? 'alert' : 'check'" class="h-4 w-4"
                            :class="progress.state === 'failed' ? 'text-rose-600' : 'text-emerald-600'" />
                        <h3 class="text-sm font-semibold capitalize text-gray-900 dark:text-slate-100">
                            Auto-Correlate: {{ progress.state }}
                        </h3>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-slate-500">{{ progress.updated_at }}</span>
                </div>

                <!-- Iteration progress: the (X x Y x lag) grid being swept -->
                <template v-if="progress.state === 'running'">
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-indigo-100 dark:bg-slate-800">
                        <div class="h-2 rounded-full bg-indigo-500 transition-all duration-500" :style="{ width: (progress.pct || 0) + '%' }"></div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <p class="font-mono text-indigo-800 dark:text-indigo-300">
                            {{ progress.done }}/{{ progress.total }} iterations -
                            testing <span class="font-bold">{{ progress.current?.x }}</span> x
                            <span class="font-bold">{{ progress.current?.y }}</span>
                            at lag <span class="font-bold">{{ progress.current?.lag }}m</span>
                        </p>
                        <p v-if="progress.best" class="text-indigo-700/90 dark:text-indigo-300/90">
                            Best so far: <span class="font-mono font-semibold">{{ progress.best.x }} x {{ progress.best.y }}</span>
                            (lag {{ progress.best.lag }}m, score {{ num(progress.best.score) }}<template v-if="progress.best.r2">, R2 {{ num(progress.best.r2) }}</template>)
                        </p>
                    </div>
                    <div v-if="progress.verdicts && Object.keys(progress.verdicts).length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="(n, v) in progress.verdicts" :key="v" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset" :class="suggestionVerdictClass(v)">
                            {{ v }}: {{ n }}
                        </span>
                    </div>
                </template>

                <p v-else class="mt-2 text-xs" :class="progress.state === 'failed' ? 'text-rose-700 dark:text-rose-300' : 'text-gray-600 dark:text-slate-300'">
                    {{ progress.message }}
                </p>

                <!-- Completion summary -->
                <div v-if="progress.state === 'complete'" class="mt-2 flex flex-wrap gap-4 text-xs text-emerald-800 dark:text-emerald-300">
                    <span><strong>{{ progress.suggestions }}</strong> suggestions from <strong>{{ progress.pairs_evaluated }}</strong> iterations</span>
                    <span v-if="progress.fitted !== null">fits: <strong>{{ progress.fitted }}</strong> (applied {{ progress.applied }}, declined {{ progress.declined }})</span>
                    <span v-for="(n, v) in (progress.by_verdict || {})" :key="v" class="capitalize">{{ v.replaceAll('_', ' ') }}: <strong>{{ n }}</strong></span>
                </div>
            </div>

            <!-- Queued confirmation (flash, kept for no-JS fallback) -->
            <div
                v-if="queued && (!progress || progress.state === 'idle')"
                class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                <Icon name="check" class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">Auto-Correlate queued.</p>
                    <p class="mt-0.5 text-emerald-700/80 dark:text-emerald-300/80">
                        <span v-if="flash.auto.period">Period {{ flash.auto.period }}. </span>
                        The sweep + guardrail runs once a queue worker picks it up.
                    </p>
                </div>
            </div>

            <!-- Verdict tiles -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Relationships" :value="fmtInt(verdicts.total)" icon="trend" hint="Governed FLI relationships" />
                <StatCard label="Applied" :value="fmtInt(verdicts.applied)" icon="signoff" hint="Passed the guardrail" />
                <StatCard label="Declined" :value="fmtInt(verdicts.declined)" icon="alert" hint="Failed a gate (reason logged)" />
                <StatCard label="Pending" :value="fmtInt(verdicts.pending)" icon="clock" hint="Not yet fitted" />
            </div>

            <!-- Run trigger + queue note -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <form
                    class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2"
                    @submit.prevent="runAutoCorrelate"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Run Auto-Correlate</h3>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-slate-500">Sweeps every macro driver x credit-loss statistic across the governed lag grid (live progress above), then fits + applies the guardrail.</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-slate-400">Governance period (optional)</label>
                        <input
                            v-model="form.period"
                            type="text"
                            placeholder="YYYY-MM (blank = latest)"
                            class="w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-900 focus:border-indigo-400 focus:ring-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                        <p v-if="form.errors.period" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.period }}</p>
                    </div>
                    <button
                        v-if="canWrite"
                        type="submit"
                        :disabled="form.processing || isActive"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Icon name="trend" class="h-4 w-4" />
                        {{ isActive ? 'Run in progress...' : (form.processing ? 'Queueing...' : 'Run Auto-Correlate') }}
                    </button>
                    <p v-else class="text-xs text-gray-400 dark:text-slate-500">Your role is read-only; running auto-correlate requires the User or Admin role.</p>
                </form>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                    <div class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-300">
                        <Icon name="alert" class="h-4 w-4" /> A worker must be running
                    </div>
                    <p class="mt-2 text-amber-700/90 dark:text-amber-200/80">
                        The run executes on the <span class="font-mono">{{ queueConnection }}</span> queue, so it never blocks this page. Start a worker:
                    </p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-amber-900/90 px-3 py-2 font-mono text-xs text-amber-50">php artisan queue:work</pre>
                    <p class="mt-3 text-amber-700/90 dark:text-amber-200/80">Or run the same sweep terminal-side:</p>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-amber-900/90 px-3 py-2 font-mono text-xs text-amber-50">php artisan fli:auto-correlate</pre>
                </div>
            </div>

            <!-- Relationship register -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Relationship Register &amp; Latest Fit</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500">Verdict is shown honestly: a fit is APPLIED only when it passes every guardrail gate.</p>
                    <p class="mt-0.5 text-xs text-indigo-600/90 dark:text-indigo-400/90">
                        Latest sweep: <span class="font-semibold tabular-nums">{{ fmtInt2(sweep.iterations) }}</span> iterations
                        (<span class="tabular-nums">{{ fmtInt2(sweep.pairs) }}</span> pairs &times; <span class="tabular-nums">{{ sweep.lags }}</span> lags),
                        window &le; <span class="tabular-nums">{{ sweep.max_history_years }}</span>y<span v-if="sweep.run_id"> &middot; run #{{ sweep.run_id }}</span>.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                <th class="px-4 py-2.5 text-left">Statistic</th>
                                <th class="px-4 py-2.5 text-left">Proxy</th>
                                <th class="px-4 py-2.5 text-center">Sign</th>
                                <th class="px-4 py-2.5 text-right">R</th>
                                <th class="px-4 py-2.5 text-right">R-sq %</th>
                                <th class="px-4 py-2.5 text-right">p</th>
                                <th class="px-4 py-2.5 text-center" title="Dataset frequency (Annual / Quarterly / Monthly)">Freq</th>
                                <th class="px-4 py-2.5 text-right" title="Annual-equivalent sample size (years of matching data)">n (yrs)</th>
                                <th class="px-4 py-2.5 text-center">Verdict</th>
                                <th class="px-4 py-2.5 text-left">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in relationships" :key="i" class="border-b border-gray-100 last:border-0 dark:border-slate-800/70">
                                <td class="px-4 py-2 font-mono text-gray-700 dark:text-slate-300">{{ r.statistic }}</td>
                                <td class="px-4 py-2 font-mono text-gray-500 dark:text-slate-400">{{ r.proxy }}</td>
                                <td class="px-4 py-2 text-center">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-xs font-medium text-gray-600 dark:text-slate-300">{{ r.expectedSign || 'either' }}</span>
                                        <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset" :class="signStatusClass(r.signStatus)" :title="r.signNote || ''">{{ r.signStatus }}</span>
                                        <div v-if="isAdmin && r.signStatus === 'provisional'" class="mt-0.5 flex gap-1">
                                            <button type="button" title="Approve expected sign as positive" class="rounded border border-emerald-300 px-1 text-[10px] font-bold leading-none text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/40 dark:text-emerald-300" @click="approveSign(r, 'positive')">+</button>
                                            <button type="button" title="Approve expected sign as negative" class="rounded border border-rose-300 px-1 text-[10px] font-bold leading-none text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300" @click="approveSign(r, 'negative')">&minus;</button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-slate-400">{{ num(r.correlationR) }}</td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-900 dark:text-slate-100">{{ pctR2(r.rSquared) }}</td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-slate-400">{{ num(r.pValue) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold text-gray-500 ring-1 ring-inset ring-gray-200 dark:text-slate-400 dark:ring-slate-700" :title="'X: ' + (r.freqX || 'n/a') + ' | Y: ' + (r.freqY || 'n/a')">{{ pairFreq(r.freqX, r.freqY) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-slate-400" :title="(r.nObs ?? '-') + ' matched points'">
                                    {{ r.nYears === null || r.nYears === undefined ? (r.nObs ?? '-') : Number(r.nYears).toFixed(0) }}
                                    <span v-if="r.nYears !== null && r.nYears !== undefined" class="text-[10px] text-gray-400">yr</span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span v-if="r.verdict" class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ring-1" :class="verdictClass(r.verdict)">{{ r.verdict }}</span>
                                    <span v-else class="text-xs text-gray-400 dark:text-slate-500">pending</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500 dark:text-slate-400">{{ r.declinedReason || '-' }}</td>
                            </tr>
                            <tr v-if="!relationships.length">
                                <td colspan="10" class="px-4 py-8 text-center text-gray-400 dark:text-slate-500">No FLI relationships governed yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Ranked suggestions + diagnostics -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Ranked Suggestions</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500">Advisory candidate correlations ranked by score. Click a row for its pre-fit diagnostics: Dickey-Fuller stationarity (tau vs MacKinnon finite-sample critical values), distribution shape and the recommended method.</p>
                </div>
                <div class="overflow-x-auto">
                    <table v-if="suggestions.length" class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                <th class="px-4 py-2.5 text-left">#</th>
                                <th class="px-4 py-2.5 text-left">Statistic</th>
                                <th class="px-4 py-2.5 text-left">Proxy</th>
                                <th class="px-4 py-2.5 text-right">Lag</th>
                                <th class="px-4 py-2.5 text-right">Score</th>
                                <th class="px-4 py-2.5 text-right">R-sq %</th>
                                <th class="px-4 py-2.5 text-center">Verdict</th>
                                <th class="px-4 py-2.5 text-left">Reason</th>
                                <th class="px-4 py-2.5 text-right">Tests</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(s, i) in suggestions" :key="i">
                                <tr class="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-indigo-50/40 dark:border-slate-800/70 dark:hover:bg-slate-800/40" @click="toggleDiag(i)">
                                    <td class="px-4 py-2 font-mono tabular-nums text-gray-400 dark:text-slate-500">{{ i + 1 }}</td>
                                    <td class="px-4 py-2 font-mono text-gray-700 dark:text-slate-300">{{ s.statistic }}</td>
                                    <td class="px-4 py-2 font-mono text-gray-500 dark:text-slate-400">{{ s.proxy }}</td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-slate-400">{{ s.lagMonths }}m</td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-900 dark:text-slate-100">{{ num(s.score) }}</td>
                                    <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-600 dark:text-slate-400">{{ pctR2(s.rSquared) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span v-if="s.verdict" class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset" :class="suggestionVerdictClass(s.verdict)">{{ s.verdict.replaceAll('_', ' ') }}</span>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-slate-400">{{ s.reason }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <span v-if="s.diagnostics" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                            Diagnostics
                                            <Icon name="chevron" class="h-3 w-3 transition-transform" :class="openDiag === i ? 'rotate-180' : ''" />
                                        </span>
                                        <span v-else class="text-xs text-gray-300 dark:text-slate-600">-</span>
                                    </td>
                                </tr>
                                <!-- Expandable diagnostics: DF stationarity + shape + method -->
                                <tr v-if="openDiag === i && s.diagnostics" class="border-b border-gray-100 bg-gray-50/60 dark:border-slate-800/70 dark:bg-slate-950/40">
                                    <td colspan="9" class="px-6 py-4">
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                            <!-- Stationarity (Dickey-Fuller) -->
                                            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500">Stationarity - Dickey-Fuller (unit root)</p>
                                                <div v-for="(st, lbl) in { 'X (driver)': s.diagnostics.stationarity_x, 'Y (credit-loss)': s.diagnostics.stationarity_y }" :key="lbl" class="mt-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-xs font-medium text-gray-600 dark:text-slate-300">{{ lbl }}</span>
                                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset" :class="stationarityClass(st?.verdict)">{{ (st?.verdict || 'n/a').replaceAll('_', ' ') }}</span>
                                                    </div>
                                                    <p v-if="st?.tau !== null && st?.tau !== undefined" class="mt-0.5 font-mono text-[11px] text-gray-500 dark:text-slate-400">
                                                        tau {{ st.tau }} vs cv 1% {{ st.crit_1pct }} / 5% {{ st.crit_5pct }} / 10% {{ st.crit_10pct }} (n={{ st.n }})
                                                    </p>
                                                    <p class="mt-0.5 text-[11px] text-gray-400 dark:text-slate-500">{{ st?.reason }}</p>
                                                </div>
                                                <p class="mt-2 text-[10px] italic text-gray-400 dark:text-slate-500">Distribution: DF tau under H0 (unit root), NOT Student-t - MacKinnon (2010) finite-sample critical values. Sidecar ADF+KPSS supersedes when configured ({{ s.diagnostics.sidecar }}).</p>
                                            </div>
                                            <!-- Distribution shape -->
                                            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500">Distribution shape (normality)</p>
                                                <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 font-mono text-[11px] text-gray-600 dark:text-slate-300">
                                                    <span>skew X: {{ num(s.diagnostics.normality?.skew_x, 2) }}</span>
                                                    <span>kurt X: {{ num(s.diagnostics.normality?.kurtosis_x, 2) }}</span>
                                                    <span>skew Y: {{ num(s.diagnostics.normality?.skew_y, 2) }}</span>
                                                    <span>kurt Y: {{ num(s.diagnostics.normality?.kurtosis_y, 2) }}</span>
                                                </div>
                                                <p class="mt-2 text-xs text-gray-600 dark:text-slate-300">Verdict: <span class="font-semibold">{{ (s.diagnostics.normality?.verdict || 'n/a').replaceAll('_', ' ') }}</span></p>
                                                <p v-if="s.diagnostics.break?.flag" class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">Window spans structural event(s): {{ (s.diagnostics.break.registered_events_in_window || []).join(', ') }}</p>
                                            </div>
                                            <!-- Recommendation -->
                                            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-slate-500">Recommended treatment</p>
                                                <p class="mt-2 text-xs text-gray-700 dark:text-slate-200">Method: <span class="font-mono font-semibold">{{ s.diagnostics.recommended_method }}</span></p>
                                                <p class="mt-1 text-xs text-gray-700 dark:text-slate-200">Transform: <span class="font-mono font-semibold">{{ s.diagnostics.recommended_transform }}</span></p>
                                                <ul v-if="(s.diagnostics.reasons || []).length" class="mt-2 list-disc space-y-0.5 pl-4 text-[11px] text-gray-500 dark:text-slate-400">
                                                    <li v-for="(reason, ri) in s.diagnostics.reasons" :key="ri">{{ reason }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-else class="px-4 py-8 text-center text-sm text-gray-400 dark:text-slate-500">No suggestion sweep recorded yet. Run Auto-Correlate to populate.</p>
                </div>
            </div>

            <!-- Methodology notes -->
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-5 text-sm dark:border-indigo-500/30 dark:bg-indigo-500/10">
                <div class="flex items-center gap-2 font-semibold text-indigo-800 dark:text-indigo-300">
                    <Icon name="trend" class="h-4 w-4" /> Guardrail methodology
                </div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-indigo-700/90 dark:text-indigo-200/80">
                    <li v-for="(n, i) in notes" :key="i">{{ n }}</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
