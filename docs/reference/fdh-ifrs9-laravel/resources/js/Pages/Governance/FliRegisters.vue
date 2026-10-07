<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Shell/Icon.vue';
import EmptyState from '@/Components/Shell/EmptyState.vue';

/*
 * FLI Registers (SYSTEM_DESIGN 7.2) - the policy (relationships), the fit
 * lineage with its guardrail verdict (applied / declined + reason), and the
 * correlation-finder suggestions. All values are controller props read from
 * fli_relationships / fli_fits / fli_suggestions; each section empty-states
 * until the FLI engine populates it.
 */
const props = defineProps({
    relationships: { type: Array, default: () => [] },
    fits: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
});

const dt = (v) => (v ? String(v).replace('T', ' ').slice(0, 16) : '');
const verdictClass = (v) => (v === 'applied'
    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20'
    : 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20');
</script>

<template>
    <Head title="FLI Registers" />

    <AppLayout description="Forward-looking indicator governance - the policy, the reproducible fit lineage with its guardrail verdict, and correlation suggestions.">
        <div class="space-y-8">
            <!-- Relationship Register (policy) -->
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-slate-100">
                    <Icon name="trend" class="h-4 w-4 text-amber-500" /> Relationship Register (policy)
                </h2>
                <EmptyState
                    v-if="!relationships.length"
                    icon="trend"
                    title="No relationships registered"
                    message="The rule (statistic, proxy, expected sign, R-squared cutoff, method) is approved before the data is seen."
                />
                <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                    <th class="px-4 py-2.5 text-left">Statistic</th>
                                    <th class="px-4 py-2.5 text-left">Proxy</th>
                                    <th class="px-4 py-2.5 text-left">Business Unit</th>
                                    <th class="px-4 py-2.5 text-left">Sign</th>
                                    <th class="px-4 py-2.5 text-right">R2 Cutoff</th>
                                    <th class="px-4 py-2.5 text-left">Method</th>
                                    <th class="px-4 py-2.5 text-right">Lag (m)</th>
                                    <th class="px-4 py-2.5 text-left">Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in relationships" :key="r.id" class="border-b border-gray-100 last:border-0 dark:border-slate-800/70">
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ r.statistic_code }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ r.proxy_code }}</td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-slate-400">{{ r.business_unit_name || '-' }}</td>
                                    <td class="px-4 py-2.5 capitalize text-gray-600 dark:text-slate-400">{{ r.expected_sign }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ r.r2_cutoff }}</td>
                                    <td class="px-4 py-2.5 capitalize text-gray-600 dark:text-slate-400">{{ r.method }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ r.lag_months }}</td>
                                    <td class="px-4 py-2.5">{{ r.is_active ? 'Yes' : 'No' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Fit Lineage + Guardrail verdict -->
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-slate-100">
                    <Icon name="beaker" class="h-4 w-4 text-amber-500" /> Fit Lineage &amp; Guardrail Decisions
                </h2>
                <EmptyState
                    v-if="!fits.length"
                    icon="beaker"
                    title="No fits computed"
                    message="Every fit records slope, R-squared, p-value, n and the verdict - applied, or declined with a reason."
                />
                <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                    <th class="px-4 py-2.5 text-left">Period</th>
                                    <th class="px-4 py-2.5 text-left">Statistic</th>
                                    <th class="px-4 py-2.5 text-left">Proxy</th>
                                    <th class="px-4 py-2.5 text-right">R2</th>
                                    <th class="px-4 py-2.5 text-right">p</th>
                                    <th class="px-4 py-2.5 text-right">n</th>
                                    <th class="px-4 py-2.5 text-left">Verdict</th>
                                    <th class="px-4 py-2.5 text-left">Declined Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in fits" :key="f.id" class="border-b border-gray-100 last:border-0 dark:border-slate-800/70">
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ f.reporting_period }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ f.statistic_code }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ f.proxy_code }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ f.r_squared }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ f.p_value }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ f.n_obs }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset" :class="verdictClass(f.verdict)">{{ f.verdict }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-slate-400">{{ f.declined_reason || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Correlation Finder suggestions -->
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-slate-100">
                    <Icon name="grid" class="h-4 w-4 text-amber-500" /> Correlation Finder Suggestions
                </h2>
                <EmptyState
                    v-if="!suggestions.length"
                    icon="grid"
                    title="No suggestions yet"
                    message="The correlation finder proposes candidate statistic/proxy pairs ranked by score, for governed review."
                />
                <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                                    <th class="px-4 py-2.5 text-left">Statistic</th>
                                    <th class="px-4 py-2.5 text-left">Proxy</th>
                                    <th class="px-4 py-2.5 text-right">Lag (m)</th>
                                    <th class="px-4 py-2.5 text-right">Score</th>
                                    <th class="px-4 py-2.5 text-right">R2</th>
                                    <th class="px-4 py-2.5 text-left">Sign OK</th>
                                    <th class="px-4 py-2.5 text-left">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in suggestions" :key="s.id" class="border-b border-gray-100 last:border-0 dark:border-slate-800/70">
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ s.statistic_code }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-gray-700 dark:text-slate-300">{{ s.proxy_code }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ s.lag_months }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ s.score }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ s.r_squared }}</td>
                                    <td class="px-4 py-2.5">{{ s.sign_ok ? 'Yes' : 'No' }}</td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-slate-400">{{ s.reason || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
