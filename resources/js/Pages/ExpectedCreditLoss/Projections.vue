<template>
    <app-layout title="ECL Projection Audit" description="The month-by-month working behind a discounted ECL run: marginal PD x LGD x exposure, scenario weights and discounting at the locked EIR">
        <template #actions>
            <Link :href="route('expected-credit-loss.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to ECL results</Link>
        </template>

        <div class="space-y-5">
            <!-- No discounted run yet -->
            <div v-if="!run" class="maiic-panel px-6 py-12 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-maiic-50 text-maiic-600">
                    <font-awesome-icon icon="chart-line" class="text-xl"/>
                </div>
                <h3 class="text-base font-bold text-gray-900">No time-phased ECL run yet</h3>
                <p class="mx-auto mt-1 max-w-xl text-sm text-gray-500">This page fills in after an ECL calculation is run on the discounted basis with approved scenario assumptions. Run one from the ECL Calculation page, choosing "Discounted ECL, at the EIR".</p>
                <Link :href="route('expected-credit-loss.create')" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700"><font-awesome-icon icon="calculator"/> Run ECL calculation</Link>
            </div>

            <template v-else>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="maiic-kpi" style="--accent:#15803d"><div class="maiic-kpi-label">Contracts calculated</div><div class="maiic-kpi-value">{{ count(run.contracts_processed) }}</div></div>
                    <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Unresolved</div><div class="maiic-kpi-value">{{ count(run.contracts_unresolved) }}</div></div>
                    <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">Undiscounted ECL (MWK)</div><div class="maiic-kpi-value">{{ money(run.undiscounted_ecl) }}</div></div>
                    <div class="maiic-kpi" style="--accent:#b45309"><div class="maiic-kpi-label">Discounted ECL (MWK)</div><div class="maiic-kpi-value">{{ money(run.discounted_ecl) }}</div></div>
                </div>

                <form class="maiic-filterbar mb-0" @submit.prevent="filter">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4 md:items-end">
                        <div>
                            <label for="proj-run" class="maiic-flabel">Run</label>
                            <select id="proj-run" v-model="form.run_id" class="maiic-select">
                                <option v-for="r in runs" :key="r.run_id" :value="r.run_id">{{ r.reporting_period }} - run {{ r.run_id.slice(0, 8) }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="proj-scenario" class="maiic-flabel">Scenario</label>
                            <select id="proj-scenario" v-model="form.scenario" class="maiic-select">
                                <option value="">All scenarios</option>
                                <option v-for="s in scenarios" :key="s.scenario_code" :value="s.scenario_code">{{ s.name }} ({{ (Number(s.weight) * 100).toFixed(0) }}%)</option>
                            </select>
                        </div>
                        <div>
                            <label for="proj-search" class="maiic-flabel">Contract</label>
                            <input id="proj-search" v-model="form.search" class="maiic-input" placeholder="Contract number">
                        </div>
                        <div><button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700"><font-awesome-icon icon="search"/> Apply</button></div>
                    </div>
                </form>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 pt-4">
                        <nav class="flex gap-6 overflow-x-auto">
                            <button v-for="t in tabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                                    :class="tab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'" @click="tab = t.key">
                                {{ t.label }}
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-700' : 'bg-gray-100 text-gray-600'">{{ count(t.count) }}</span>
                            </button>
                        </nav>
                    </div>

                    <div v-if="tab === 'contracts'">
                        <div class="maiic-table-wrap">
                            <table class="maiic-table">
                                <thead><tr><th>Contract</th><th>Stage</th><th class="num">Undiscounted ECL (MWK)</th><th class="num">Discounted ECL (MWK)</th><th class="num">Horizon</th></tr></thead>
                                <tbody>
                                    <tr v-for="c in pagedContracts" :key="c.contract_id">
                                        <td class="font-semibold">{{ c.contract_id }}</td>
                                        <td><span class="maiic-badge" :class="String(c.ifrs9_stage) === '3' ? 'maiic-badge-red' : String(c.ifrs9_stage) === '2' ? 'maiic-badge-gold' : 'maiic-badge-green'">Stage {{ c.ifrs9_stage }}</span></td>
                                        <td class="num">{{ money(c.undiscounted_ecl) }}</td>
                                        <td class="num font-semibold">{{ money(c.weighted_ecl) }}</td>
                                        <td class="num">{{ Number(c.horizon).toFixed(2) }} years</td>
                                    </tr>
                                    <tr v-if="!contracts.length"><td colspan="5" class="maiic-empty">No contracts in this run.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="contracts.length > 15" class="border-t border-gray-100 p-4"><ClientPager v-model="contractPage" :total="contracts.length"/></div>
                    </div>

                    <div v-else>
                        <div class="maiic-table-wrap">
                            <table class="maiic-table">
                                <thead><tr><th>Contract</th><th>Scenario</th><th class="num">Month</th><th>Date</th><th class="num">Opening EAD</th><th class="num">Marginal PD</th><th class="num">LGD</th><th class="num">Shortfall</th><th class="num">Discount factor</th><th class="num">Weighted, discounted</th></tr></thead>
                                <tbody>
                                    <tr v-for="r in rows.data" :key="r.id">
                                        <td class="font-semibold">{{ r.contract_id }}</td>
                                        <td>{{ r.scenario_code }}</td>
                                        <td class="num">{{ r.period_index }}</td>
                                        <td class="whitespace-nowrap">{{ r.projection_date }}</td>
                                        <td class="num">{{ money(r.opening_ead) }}</td>
                                        <td class="num">{{ pct(r.marginal_pd) }}</td>
                                        <td class="num">{{ pct(r.lgd) }}</td>
                                        <td class="num">{{ money(r.undiscounted_shortfall) }}</td>
                                        <td class="num">{{ Number(r.discount_factor).toFixed(6) }}</td>
                                        <td class="num font-semibold">{{ money(r.weighted_discounted_shortfall) }}</td>
                                    </tr>
                                    <tr v-if="!rows.data.length"><td colspan="10" class="maiic-empty">No projection lines match these filters.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 px-4 pb-4"><Pagination :links="rows.links"/></div>
                    </div>
                </div>
            </template>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import ClientPager from '@/Components/ClientPager.vue';
import { Link, router } from '@inertiajs/vue3';

export default {
    components: { AppLayout, Link, Pagination, ClientPager },
    props: { run: Object, rows: Object, contracts: Array, runs: Array, scenarios: Array, filters: Object },
    data() {
        // Paging through the evidence lines keeps the evidence tab open.
        const onEvidence = new URLSearchParams(window.location.search).has('page');
        return { form: { ...this.filters }, tab: onEvidence ? 'evidence' : 'contracts', contractPage: 1 };
    },
    computed: {
        tabs() {
            return [
                { key: 'contracts', label: 'Contract totals', count: this.contracts.length },
                { key: 'evidence', label: 'Projection evidence', count: this.rows?.total ?? (this.rows?.data || []).length },
            ];
        },
        pagedContracts() { return this.contracts.slice((this.contractPage - 1) * 15, this.contractPage * 15); },
    },
    methods: {
        money(v) { return Number(v || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        count(v) { return Number(v || 0).toLocaleString('en-GB'); },
        pct(v) { return (Number(v || 0) * 100).toFixed(4) + '%'; },
        filter() { router.get(this.route('expected-credit-loss.projections'), this.form, { preserveState: false }); },
    },
};
</script>
