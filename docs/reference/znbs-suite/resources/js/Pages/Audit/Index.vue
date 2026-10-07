<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ZnbsPageHeader from '@/Components/ZnbsPageHeader.vue';
import { dateTime } from '@/format';

interface GovernanceEntry {
    id: number;
    subject_type: string;
    subject_id: number;
    subject_label: string | null;
    event: string;
    module: string | null;
    actor: string;
    notes: string | null;
    properties: Record<string, unknown> | null;
    created_at: string | null;
}

const props = defineProps<{
    stats: {
        timeline_events: number;
        forecast_events: number;
        stress_runs: number;
        open_alerts: number;
        governance_events: number;
    };
    timeline: Array<{
        module: string;
        event: string;
        detail: string;
        actor: string;
        status: string;
        created_at: string | null;
        properties?: Record<string, unknown> | null;
    }>;
    governanceLog: GovernanceEntry[];
}>();

const activeTab = ref<'timeline' | 'governance'>('timeline');

const PAGE_SIZE = 15;
const currentPage = ref(1);
const moduleFilter = ref('');

const modules = computed(() => {
    const set = new Set(props.timeline.map((e) => e.module));
    return ['', ...Array.from(set).sort()];
});

const filteredTimeline = computed(() => {
    if (!moduleFilter.value) return props.timeline;
    return props.timeline.filter((e) => e.module === moduleFilter.value);
});

const pageCount = computed(() => Math.max(1, Math.ceil(filteredTimeline.value.length / PAGE_SIZE)));
const paginatedTimeline = computed(() => {
    const safePage = Math.min(currentPage.value, pageCount.value);
    const start = (safePage - 1) * PAGE_SIZE;
    return filteredTimeline.value.slice(start, start + PAGE_SIZE);
});
const firstItem = computed(() => (filteredTimeline.value.length === 0 ? 0 : ((Math.min(currentPage.value, pageCount.value) - 1) * PAGE_SIZE) + 1));
const lastItem = computed(() => Math.min(Math.min(currentPage.value, pageCount.value) * PAGE_SIZE, filteredTimeline.value.length));

function onModuleFilter(value: string) {
    moduleFilter.value = value;
    currentPage.value = 1;
}

// Audit entries are instants written in UTC; dateTime() converts to the
// viewer's timezone whether the payload is zoned or a bare UTC string.
function fmt(date: string | null): string {
    if (!date) {
        return 'Unknown time';
    }

    return dateTime(date, 'Unknown time');
}

function statusClass(status: string): string {
    const value = status.toLowerCase();

    if (['completed', 'approved', 'resolved', 'success', 'processing', 'recorded', 'run_completed', 'created'].includes(value)) {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200';
    }

    if (['open', 'acknowledged', 'draft', 'pending', 'run_started', 'updated', 'running'].includes(value)) {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-200';
    }

    if (['failed', 'error', 'rejected', 'run_failed', 'run_blocked', 'blocked'].includes(value)) {
        return 'border-red-500/30 bg-red-500/10 text-red-200';
    }

    return 'border-slate-700 bg-slate-800 text-slate-200';
}

// Humanise a snake_case event/status ("run_started" -> "Run started").
function pretty(value: string): string {
    if (!value) return '';
    const s = value.replace(/_/g, ' ').trim();
    return s.charAt(0).toUpperCase() + s.slice(1);
}

// Module colour map. Keyed by a normalised name so the timeline's ucfirst'd
// values ("Op_risk", "Ifrs9") and the governance log's raw values ("op_risk",
// "RWA") both match. Covers the real module set written by the engines
// (alm, bootstrap, capital, concentration, ifrs9, irrbb, liquidity, op_risk,
// rwa) plus the governance/admin modules, with a slate fallback.
const MODULE_CLS: Record<string, string> = {
    capital:       'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
    rwa:           'border-amber-500/30 bg-amber-500/10 text-amber-200',
    liquidity:     'border-sky-500/30 bg-sky-500/10 text-sky-200',
    ifrs9:         'border-teal-500/30 bg-teal-500/10 text-teal-200',
    irrbb:         'border-indigo-500/30 bg-indigo-500/10 text-indigo-200',
    oprisk:        'border-orange-500/30 bg-orange-500/10 text-orange-200',
    concentration: 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-200',
    alm:           'border-cyan-500/30 bg-cyan-500/10 text-cyan-200',
    stress:        'border-red-500/30 bg-red-500/10 text-red-200',
    bootstrap:     'border-purple-500/30 bg-purple-500/10 text-purple-200',
    maintenance:   'border-znbs-gold/30 bg-znbs-gold/10 text-znbs-gold-light',
    macro:         'border-lime-500/30 bg-lime-500/10 text-lime-200',
    forecast:      'border-yellow-500/30 bg-yellow-500/10 text-yellow-200',
    users:         'border-blue-500/30 bg-blue-500/10 text-blue-200',
    roles:         'border-blue-500/30 bg-blue-500/10 text-blue-200',
    settings:      'border-slate-500/40 bg-slate-500/10 text-slate-200',
    reports:       'border-pink-500/30 bg-pink-500/10 text-pink-200',
    tax:           'border-violet-500/30 bg-violet-500/10 text-violet-200',
    ews:           'border-rose-500/30 bg-rose-500/10 text-rose-200',
};

function moduleKey(module: string | null | undefined): string {
    return (module ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

function moduleClass(module: string | null | undefined): string {
    return MODULE_CLS[moduleKey(module)] ?? 'border-slate-600 bg-slate-800 text-slate-300';
}

// Display names for modules whose ucfirst'd DB value reads badly ("Op_risk").
const MODULE_LABELS: Record<string, string> = {
    alm: 'ALM', ifrs9: 'IFRS 9', irrbb: 'IRRBB', rwa: 'RWA', oprisk: 'Op Risk', ews: 'EWS', coa: 'CoA',
};

function prettyModule(module: string | null | undefined): string {
    const label = MODULE_LABELS[moduleKey(module)];
    if (label) return label;
    const s = (module ?? '').replace(/_/g, ' ').trim();
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '-';
}
</script>

<template>
    <AppLayout title="Audit Log">
        <div class="mx-auto max-w-[88rem] space-y-6 px-6 py-6 text-slate-100">
            <ZnbsPageHeader
                eyebrow="Governance & Monitoring"
                title="Audit Log"
                subtitle="A cross-module record of imports, forecast actions, stress runs, and early-warning events."
            />

            <!-- Tabs -->
            <div class="inline-flex gap-1 rounded-xl border border-slate-800 bg-slate-900/70 p-1">
                <button
                    type="button"
                    @click="activeTab = 'timeline'"
                    :class="activeTab === 'timeline' ? 'bg-znbs-green text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                >
                    Activity Timeline
                </button>
                <button
                    type="button"
                    @click="activeTab = 'governance'"
                    :class="activeTab === 'governance' ? 'bg-znbs-green text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                    class="rounded-lg px-4 py-2 text-sm font-medium transition"
                >
                    Governance Log
                    <span v-if="stats.governance_events > 0" class="ml-1.5 rounded-full px-2 py-0.5 text-xs" :class="activeTab === 'governance' ? 'bg-white/20 text-white' : 'bg-znbs-green/20 text-znbs-green-light'">{{ stats.governance_events }}</span>
                </button>
            </div>

            <section v-show="activeTab === 'timeline'" class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
                <div class="flex flex-col gap-3 border-b border-slate-800 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-white">Activity Timeline</h2>
                        <p class="text-sm text-slate-400">Newest entries first, merged across the main ICAAP workflow modules.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                        <select
                            :value="moduleFilter"
                            @change="onModuleFilter(($event.target as HTMLSelectElement).value)"
                            class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-slate-300 focus:border-znbs-green/50 focus:outline-none"
                        >
                            <option value="">All modules</option>
                            <option v-for="mod in modules.slice(1)" :key="mod" :value="mod">{{ prettyModule(mod) }}</option>
                        </select>
                        <span>{{ firstItem }}-{{ lastItem }} of {{ filteredTimeline.length }}</span>
                        <button type="button" @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage <= 1"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-40">Prev</button>
                        <span class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300">{{ Math.min(currentPage, pageCount) }} / {{ pageCount }}</span>
                        <button type="button" @click="currentPage = Math.min(pageCount, currentPage + 1)" :disabled="currentPage >= pageCount"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-40">Next</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-znbs-green/15 to-slate-900 text-slate-200">
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Module</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Event</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Detail</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.14em]">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Actor</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em]">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/70">
                            <tr v-for="item in paginatedTimeline" :key="`${item.module}-${item.event}-${item.created_at}-${item.detail}`" class="transition hover:bg-slate-900/50">
                                <td class="px-5 py-3 align-top">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em]" :class="moduleClass(item.module)">{{ prettyModule(item.module) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top font-medium text-white">{{ pretty(item.event) }}</td>
                                <td class="max-w-md px-4 py-3 align-top leading-6 text-slate-300">{{ item.detail }}</td>
                                <td class="px-4 py-3 align-top text-center">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em]" :class="statusClass(item.status)">{{ pretty(item.status) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-300">{{ item.actor }}</td>
                                <td class="whitespace-nowrap px-5 py-3 align-top text-right text-xs text-slate-400">{{ fmt(item.created_at) }}</td>
                            </tr>
                            <tr v-if="paginatedTimeline.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No activity recorded for this filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-slate-800 px-5 py-3 text-xs text-slate-400">
                    <span>Showing {{ firstItem }}-{{ lastItem }} of {{ filteredTimeline.length }} timeline events</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage <= 1"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-40">Prev</button>
                        <button type="button" @click="currentPage = Math.min(pageCount, currentPage + 1)" :disabled="currentPage >= pageCount"
                            class="rounded-lg border border-slate-700 px-3 py-1.5 font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-40">Next</button>
                    </div>
                </div>
            </section>
            <!-- Governance Log Tab -->
            <section v-show="activeTab === 'governance'" class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h2 class="text-base font-semibold text-white">Governance Log</h2>
                    <p class="text-sm text-slate-400">Approval, rejection, and key state-change events across all modules.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-znbs-green/15 to-slate-900 text-slate-200">
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Module</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Event</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Notes</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em]">Actor</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em]">Time</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.14em]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/70">
                            <tr v-for="entry in governanceLog" :key="entry.id" class="transition hover:bg-slate-900/50">
                                <td class="px-5 py-3 align-top">
                                    <span v-if="entry.module" class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em]" :class="moduleClass(entry.module)">{{ prettyModule(entry.module) }}</span>
                                    <span v-else class="text-slate-600">-</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em]" :class="statusClass(entry.event)">{{ pretty(entry.event) }}</span>
                                </td>
                                <td class="px-4 py-3 align-top font-medium text-white">{{ entry.subject_label ?? entry.subject_type + ' #' + entry.subject_id }}</td>
                                <td class="max-w-sm px-4 py-3 align-top leading-6 text-slate-300">{{ entry.notes ?? '-' }}</td>
                                <td class="px-4 py-3 align-top text-slate-300">{{ entry.actor }}</td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-right text-xs text-slate-400">{{ fmt(entry.created_at) }}</td>
                                <td class="px-5 py-3 align-top text-right">
                                    <Link v-if="entry.subject_type && entry.subject_id"
                                        :href="route('audit.trace', [entry.subject_type, entry.subject_id])"
                                        class="inline-block rounded-lg border border-znbs-green/30 bg-znbs-green/10 px-3 py-1.5 text-xs font-medium text-znbs-green-light transition hover:bg-znbs-green/20">View trace</Link>
                                    <span v-else class="text-xs text-slate-500">No subject</span>
                                </td>
                            </tr>
                            <tr v-if="governanceLog.length === 0">
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">No governance events recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-800 px-5 py-3 text-xs text-slate-500">
                    Showing {{ governanceLog.length }} most recent governance events.
                </div>
            </section>
        </div>
    </AppLayout>
</template>
