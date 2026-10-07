<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import ZnbsPageHeader from '@/Components/ZnbsPageHeader.vue';
import JsonValue from '@/Components/JsonValue.vue';
import { tryParseJson, dateTime } from '@/format';

interface TraceEntry {
    id: number;
    event: string;
    module: string | null;
    actor: string;
    notes: string | null;
    properties: Record<string, unknown> | null;
    created_at: string | null;
}

const props = defineProps<{
    subjectType: string;
    subjectId: number;
    subjectLabel: string | null;
    entries: TraceEntry[];
}>();

// Trace entries are instants written in UTC; dateTime() converts to the
// viewer's timezone whether the payload is zoned or a bare UTC string.
function fmt(date: string | null): string {
    return dateTime(date, 'Unknown time');
}

// Humanise a snake_case event ("run_started" -> "Run started").
function prettyEvent(event: string): string {
    if (!event) return 'Event';
    const s = event.replace(/_/g, ' ').trim();
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function prettyKey(key: string): string {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

// Humanise a module value ("op_risk" -> "Op Risk", "ifrs9" -> "IFRS 9").
const MODULE_LABELS: Record<string, string> = {
    alm: 'ALM', ifrs9: 'IFRS 9', irrbb: 'IRRBB', rwa: 'RWA', oprisk: 'Op Risk', ews: 'EWS', coa: 'CoA',
};

function prettyModule(module: string | null): string {
    const key = (module ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
    if (MODULE_LABELS[key]) return MODULE_LABELS[key];
    const s = (module ?? '').replace(/_/g, ' ').trim();
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

// Render any value as readable text — never raw JSON. Arrays join their items;
// objects become "Label: value" pairs with humanised keys.
function fmtValue(v: unknown): string {
    if (v === null || v === undefined) return '-';
    if (v === true) return 'Yes';
    if (v === false) return 'No';
    if (typeof v === 'number') return new Intl.NumberFormat('en-GB').format(v);
    if (Array.isArray(v)) return v.length ? v.map((item) => fmtValue(item)).join(', ') : '-';
    if (typeof v === 'object') {
        const entries = Object.entries(v as Record<string, unknown>);
        if (!entries.length) return '-';
        return entries.map(([k, val]) => `${prettyKey(k)}: ${fmtValue(val)}`).join(' · ');
    }
    return String(v);
}

// A run event's properties keep only the fields that carry information; a null
// (e.g. "year": null) is noise, not a value, so it is dropped.
function visibleProps(properties: Record<string, unknown> | null): Array<[string, unknown]> {
    if (!properties) return [];
    return Object.entries(properties).filter(([, v]) => v !== null && v !== undefined && v !== '');
}

function dotClass(event: string): string {
    const v = event.toLowerCase();
    if (['run_completed', 'approved', 'created', 'imported'].includes(v)) return 'bg-emerald-400 ring-emerald-400/30';
    if (['run_started', 'updated', 'acknowledged', 'running'].includes(v)) return 'bg-znbs-gold ring-znbs-gold/30';
    if (['run_failed', 'rejected', 'run_blocked', 'blocked'].includes(v)) return 'bg-red-400 ring-red-400/30';
    return 'bg-slate-500 ring-slate-500/30';
}

function eventClass(event: string): string {
    const v = event.toLowerCase();
    if (['run_completed', 'approved', 'created', 'imported'].includes(v)) return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200';
    if (['run_started', 'updated', 'acknowledged', 'running'].includes(v)) return 'border-znbs-gold/30 bg-znbs-gold/10 text-znbs-gold-light';
    if (['run_failed', 'rejected', 'run_blocked', 'blocked'].includes(v)) return 'border-red-500/30 bg-red-500/10 text-red-200';
    return 'border-slate-600 bg-slate-800 text-slate-300';
}

function hasDiff(p: Record<string, unknown> | null): boolean {
    return !!p && ('old' in p || 'new' in p);
}

function diffKeys(properties: Record<string, unknown>): string[] {
    const oldKeys = Object.keys((properties.old as Record<string, unknown>) ?? {});
    const newKeys = Object.keys((properties.new as Record<string, unknown>) ?? {});
    return [...new Set([...oldKeys, ...newKeys])];
}

// One side of a before/after diff row. A setting's value may itself be a JSON
// string (e.g. the ICAAP sign-off designations map) — the template routes such
// values through <JsonValue> instead of printing the raw blob.
function diffVal(properties: Record<string, unknown>, side: 'old' | 'new', key: string): unknown {
    return (properties[side] as Record<string, unknown> | undefined)?.[key];
}

function initials(name: string): string {
    return (name || '?').split(/\s+/).map((p) => p.charAt(0)).slice(0, 2).join('').toUpperCase();
}
</script>

<template>
    <AppLayout :title="`Audit Trace: ${subjectLabel ?? subjectType + ' #' + subjectId}`">
        <div class="mx-auto max-w-5xl space-y-5 px-6 py-6 text-slate-100">
            <div class="flex items-center gap-2 text-xs">
                <a href="/system/audit" class="text-slate-400 transition hover:text-znbs-green-light">Audit Log</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-300">Trace</span>
            </div>

            <ZnbsPageHeader
                eyebrow="Governance & Monitoring"
                :title="subjectLabel ?? subjectType + ' #' + subjectId"
                subtitle="Full audit trail for this record, oldest to newest."
            />

            <!-- Summary bar: who + how many + subject -->
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-znbs-green to-znbs-green/60 text-sm font-semibold text-white">
                        {{ initials(entries[0]?.actor ?? 'System') }}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-white">{{ entries[0]?.actor ?? 'System' }}</div>
                        <div class="text-xs text-slate-500">Most recent actor on this record</div>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="rounded-full border border-znbs-green/30 bg-znbs-green/10 px-3 py-1 font-semibold text-znbs-green-light">
                        {{ entries.length }} {{ entries.length === 1 ? 'event' : 'events' }}
                    </span>
                    <span class="rounded-full border border-slate-700 bg-slate-800 px-3 py-1 font-mono text-slate-400">{{ subjectType }} #{{ subjectId }}</span>
                </div>
            </div>

            <div v-if="entries.length === 0" class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-5 py-16 text-center text-sm text-slate-500">
                No events recorded for this record.
            </div>

            <!-- Timeline -->
            <ol v-else class="relative space-y-4 border-l border-slate-800 pl-7">
                <li v-for="(entry, idx) in entries" :key="entry.id" class="relative">
                    <span class="absolute -left-[2.05rem] top-4 flex h-3.5 w-3.5 items-center justify-center rounded-full ring-4 ring-slate-950" :class="dotClass(entry.event)"></span>

                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition hover:border-slate-700">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-semibold text-slate-600">{{ idx + 1 }}.</span>
                                    <span v-if="entry.module" class="rounded-md border border-slate-700 bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-400">
                                        {{ prettyModule(entry.module) }}
                                    </span>
                                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="eventClass(entry.event)">
                                        {{ prettyEvent(entry.event) }}
                                    </span>
                                </div>
                                <div v-if="entry.notes" class="text-sm leading-6 text-slate-300">{{ entry.notes }}</div>
                            </div>
                            <div class="shrink-0 text-right text-xs">
                                <div class="font-medium text-slate-300">{{ entry.actor }}</div>
                                <div class="mt-0.5 text-slate-500">{{ fmt(entry.created_at) }}</div>
                            </div>
                        </div>

                        <!-- Before/After diff -->
                        <div v-if="hasDiff(entry.properties)" class="mt-4 overflow-hidden rounded-xl border border-slate-800">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-slate-800 bg-slate-800/50">
                                        <th class="px-4 py-2 text-left font-medium text-slate-400">Field</th>
                                        <th class="px-4 py-2 text-left font-medium text-red-300">Before</th>
                                        <th class="px-4 py-2 text-left font-medium text-emerald-300">After</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800">
                                    <tr v-for="key in diffKeys(entry.properties!)" :key="key">
                                        <td class="px-4 py-2 align-top font-medium text-slate-300">{{ prettyKey(key) }}</td>
                                        <td class="px-4 py-2 align-top font-mono text-red-300">
                                            <JsonValue v-if="tryParseJson(diffVal(entry.properties!, 'old', key))" :value="diffVal(entry.properties!, 'old', key)" />
                                            <template v-else>{{ fmtValue(diffVal(entry.properties!, 'old', key) ?? '-') }}</template>
                                        </td>
                                        <td class="px-4 py-2 align-top font-mono text-emerald-300">
                                            <JsonValue v-if="tryParseJson(diffVal(entry.properties!, 'new', key))" :value="diffVal(entry.properties!, 'new', key)" />
                                            <template v-else>{{ fmtValue(diffVal(entry.properties!, 'new', key) ?? '-') }}</template>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Properties as a readable key/value grid (no raw JSON) -->
                        <dl v-else-if="visibleProps(entry.properties).length > 0" class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-800 bg-slate-800/40 sm:grid-cols-3">
                            <div v-for="[key, value] in visibleProps(entry.properties)" :key="key" class="bg-slate-900/50 px-4 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-500">{{ prettyKey(key) }}</dt>
                                <dd v-if="tryParseJson(value)" class="mt-1"><JsonValue :value="value" /></dd>
                                <dd v-else class="mt-0.5 truncate text-sm font-medium text-slate-200" :title="fmtValue(value)">{{ fmtValue(value) }}</dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ol>
        </div>
    </AppLayout>
</template>
