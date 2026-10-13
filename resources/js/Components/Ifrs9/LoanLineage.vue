<template>
    <!-- How one loan's ECL was built, left to right: PD, LGD, EAD, ECL, then what it ran under. -->
    <div class="space-y-3 text-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <!-- PD -->
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="maiic-kpi-label">Probability of default</p>
                <dl class="space-y-1">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">12-month PD before FLI</dt><dd class="num">{{ rate(x.pd_pre) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">FLI adjustment</dt>
                        <dd class="num" :class="adjClass">{{ x.stage === 3 ? 'Not applied (defaulted)' : signed(x.fli_adj) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">12-month PD after FLI</dt><dd class="num font-semibold">{{ rate(x.pd_post) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-1"><dt class="text-gray-500">PD used, {{ horizonLabel }}</dt><dd class="num font-semibold text-maiic-800">{{ rate(x.horizon_pd_post) }}</dd></div>
                </dl>
                <p v-if="scenarios.length" class="mt-2 text-[11px] text-gray-500">
                    By scenario:
                    <span v-for="(s, i) in scenarios" :key="s.scenario">{{ s.scenario }} {{ Number(s.weight).toFixed(0) }}% at {{ rate(s.pd) }}<span v-if="i < scenarios.length - 1">, </span></span>
                </p>
                <p v-if="x.stage !== 3 && !x.tenor_recorded" class="mt-1 text-[11px] text-amber-700">No remaining term on the loan, so twelve months is used.</p>
            </div>

            <!-- LGD -->
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="maiic-kpi-label">Loss given default</p>
                <dl class="space-y-1">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">LGD used</dt><dd class="num font-semibold">{{ rate(x.lgd) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Source</dt><dd class="text-right">{{ x.lgd_basis || '-' }}</dd></div>
                </dl>
                <p class="mt-2 text-[11px] text-gray-500">FLI adjusts the PD only, so the LGD is the same before and after FLI.</p>
            </div>

            <!-- EAD -->
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="maiic-kpi-label">Exposure at default (MWK)</p>
                <dl class="space-y-1">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Carrying amount</dt><dd class="num">{{ money(x.carrying) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Undrawn commitment</dt><dd class="num">{{ money(x.undrawn) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">x utilisation rate</dt><dd class="num">{{ (x.utilisation * 100).toFixed(0) }}%<span v-if="!x.utilisation_recorded" class="text-gray-400"> (not recorded)</span></dd></div>
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-1"><dt class="text-gray-500">EAD</dt><dd class="num font-semibold text-maiic-800">{{ money(x.ead) }}</dd></div>
                </dl>
            </div>

            <!-- ECL -->
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                <p class="maiic-kpi-label">Expected credit loss (MWK)</p>
                <dl class="space-y-1">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">ECL before FLI</dt><dd class="num">{{ x.ecl_pre === null ? '-' : money(x.ecl_pre) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Forward-looking effect</dt><dd class="num">{{ x.fli_effect === null ? '-' : money(x.fli_effect) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-1"><dt class="text-gray-500">ECL booked</dt><dd class="num font-semibold text-maiic-800">{{ x.ecl === null ? 'Not calculated' : money(x.ecl) }}</dd></div>
                </dl>
                <p class="mt-2 text-[11px]" :class="x.ties ? 'text-maiic-700' : 'text-amber-700'">
                    EAD x PD used x LGD = {{ money(x.ecl_recalc) }}<template v-if="x.ecl !== null">: {{ x.ties ? 'matches the booked ECL' : (x.ties_pre ? 'the booked ECL used the PD before FLI' : 'does not match the booked ECL, run the ECL again') }}</template>
                </p>
            </div>
        </div>

        <!-- what it ran under -->
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
            <span class="font-bold uppercase tracking-wider text-gray-500">Ran under</span>
            <span v-if="loan.fli_route">FLI route: <b>{{ loan.fli_route }}</b><template v-if="loan.fli_method">, {{ loan.fli_method.toLowerCase() }}</template></span>
            <span v-else>No FLI route recorded</span>
            <span v-if="loan.fit" class="text-gray-300">|</span>
            <span v-if="loan.fit">Regression fit {{ loan.fit.id }}<template v-if="loan.fit.relationship"> ({{ loan.fit.relationship }})</template>
                <span class="maiic-badge ml-1" :class="loan.fit.status === 'APPROVED' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ sentence(loan.fit.status) }}</span></span>
            <span v-if="loan.scenario_set" class="text-gray-300">|</span>
            <span v-if="loan.scenario_set">Scenario set {{ loan.scenario_set.id }}<template v-if="loan.scenario_set.name">, {{ loan.scenario_set.name }}</template><template v-if="loan.scenario_set.version"> v{{ loan.scenario_set.version }}</template>
                <span v-if="loan.scenario_set.status" class="maiic-badge ml-1" :class="['APPROVED', 'LOCKED'].includes(loan.scenario_set.status) ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ sentence(loan.scenario_set.status) }}</span></span>
            <span class="text-gray-300">|</span>
            <span>EIR: <b v-if="loan.eir?.locked">{{ rate(loan.eir.rate) }} locked</b><span v-else>{{ loan.eir?.status || 'No EIR record' }}</span></span>
            <Link :href="route('audit-trace.index', { contract: loan.contract_id })" class="ml-auto inline-flex items-center gap-1 font-bold text-maiic-700 hover:text-maiic-900">
                <font-awesome-icon icon="history"/> Open in Audit Trace
            </Link>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ loan: { type: Object, required: true } });
const x = computed(() => props.loan.lineage || {});
const scenarios = computed(() => props.loan.fli_by_scenario_list || []);

const horizonLabel = computed(() => {
    if (x.value.horizon === 'defaulted') return 'defaulted';
    if (x.value.horizon === 'lifetime') return 'lifetime, ' + Math.round(x.value.horizon_months) + ' months';
    const m = Math.round(x.value.horizon_months || 12);
    return m < 12 ? m + ' months left' : '12 months';
});
const adjClass = computed(() => {
    const a = Number(x.value.fli_adj || 0);
    if (x.value.stage === 3 || !a) return 'text-gray-500';
    return a > 0 ? 'text-red-600 font-semibold' : 'text-maiic-700 font-semibold';
});

const rate = (v) => (v === null || v === undefined) ? '-' : (Number(v) * 100).toFixed(2) + '%';
const signed = (v) => (v === null || v === undefined) ? '-' : (Number(v) > 0 ? '+' : '') + (Number(v) * 100).toFixed(2) + '%';
const money = (v) => {
    const n = Number(v ?? 0);
    const abs = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '(' + abs + ')' : abs;
};
const sentence = (s) => s ? String(s).charAt(0) + String(s).slice(1).toLowerCase() : '';
</script>
