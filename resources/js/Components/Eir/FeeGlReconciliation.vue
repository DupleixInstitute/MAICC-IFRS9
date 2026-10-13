<template>
    <div v-if="report" class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex flex-col gap-1 md:flex-row md:items-baseline md:justify-between">
            <h4 class="text-sm font-semibold text-gray-900">Fees against the fee GLs (4871 Legal, 4873 Arrangement)</h4>
            <span class="text-xs text-gray-500">Governance Centre: {{ report.setting || 'not set' }}</span>
        </div>

        <p v-if="!report.enabled" class="mt-2 text-sm text-gray-600">
            The check is switched off in the Governance Centre ("Fee imports checked against the fee GLs"), so imported fees are not compared with the GL.
            <Link v-if="canGovern" :href="route('eir-governance.index')" class="font-medium text-maiic-700 underline">Open the Governance Centre</Link>
        </p>

        <template v-else>
            <p class="mt-1 text-xs text-gray-600">
                Every fee held for the year, by the month of its date, beside the trial balance movement of its GL.
                A difference means fees not yet imported, or fees on loans outside the engine's schemes; the GL is never adjusted.
                Band: {{ toleranceText }}.
            </p>

            <p v-if="!years.length" class="mt-3 text-sm text-gray-600">No dated fees to check yet.</p>

            <div v-for="y in years" :key="y.year" class="mt-4">
                <div class="text-sm font-semibold text-gray-900">{{ y.year }}</div>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr>
                                <th class="th">GL</th>
                                <th class="th text-right">Fees imported</th>
                                <th class="th text-right">GL movement</th>
                                <th class="th text-right">Difference</th>
                                <th class="th">Status</th>
                                <th class="th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template v-for="g in Object.values(y.gls)" :key="y.year + g.gl_code">
                                <tr>
                                    <td class="td font-medium">{{ g.gl_code }} {{ glName(g.gl_code) }}</td>
                                    <td class="td text-right tabular-nums">{{ money(g.imported) }}</td>
                                    <td class="td text-right tabular-nums">{{ money(g.gl) }}</td>
                                    <td class="td text-right tabular-nums" :class="g.status === 'DIFFERENCE' ? 'text-amber-700 font-semibold' : ''">{{ money(g.difference) }}</td>
                                    <td class="td"><span :class="badge(g.status)">{{ label(g.status) }}</span>
                                        <div v-if="g.months_without_tb" class="text-xs text-gray-500">{{ g.months_without_tb }} month(s) with no trial balance loaded</div>
                                    </td>
                                    <td class="td text-right"><button v-if="g.months.length" type="button" class="text-xs font-medium text-maiic-700 underline" @click="toggle(y.year, g.gl_code)">{{ isOpen(y.year, g.gl_code) ? 'Hide months' : 'Months' }}</button></td>
                                </tr>
                                <tr v-if="isOpen(y.year, g.gl_code)">
                                    <td colspan="6" class="bg-gray-50 px-4 py-2">
                                        <table class="min-w-full text-xs">
                                            <thead><tr><th class="py-1 pr-4 text-left text-gray-500">Month</th><th class="py-1 pr-4 text-right text-gray-500">Imported</th><th class="py-1 pr-4 text-right text-gray-500">GL movement</th><th class="py-1 pr-4 text-right text-gray-500">Difference</th><th class="py-1 text-left text-gray-500">Status</th></tr></thead>
                                            <tbody>
                                                <tr v-for="m in g.months" :key="m.period">
                                                    <td class="py-1 pr-4">{{ monthName(m.month) }} {{ y.year }}<span v-if="m.tb_basis === 'PRECLOSING'" class="text-gray-500"> (pre-closing)</span></td>
                                                    <td class="py-1 pr-4 text-right tabular-nums">{{ money(m.imported) }}</td>
                                                    <td class="py-1 pr-4 text-right tabular-nums">{{ m.gl === null ? 'no TB' : money(m.gl) }}</td>
                                                    <td class="py-1 pr-4 text-right tabular-nums">{{ m.difference === null ? '' : money(m.difference) }}</td>
                                                    <td class="py-1"><span :class="badge(m.status)">{{ label(m.status) }}</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-if="y.paid_excluded.lines || y.unmapped.lines" class="mt-1 text-xs text-gray-500">
                    <span v-if="y.paid_excluded.lines">{{ y.paid_excluded.lines }} PAID line(s), {{ money(y.paid_excluded.amount) }}, left out: costs paid out belong on 6752, not on the income GLs. </span>
                    <span v-if="y.unmapped.lines">{{ y.unmapped.lines }} line(s), {{ money(y.unmapped.amount) }}, name no fee GL and are not arrangement or legal fees.</span>
                </div>
            </div>

            <p v-if="report.undated && report.undated.lines" class="mt-3 text-xs text-amber-700">
                {{ report.undated.lines }} fee line(s), {{ money(report.undated.amount) }}, carry no date and cannot be placed in a month. Add the transaction date and re-import them.
            </p>
        </template>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
    components: { Link },
    props: {
        report: { type: Object, default: null },
        canGovern: { type: Boolean, default: false },
    },
    data() {
        return { open: {} }
    },
    computed: {
        years() {
            return this.report && this.report.years ? Object.values(this.report.years) : []
        },
        toleranceText() {
            const t = (this.report && this.report.tolerance) || {}
            const parts = []
            if (Number(t.percent)) parts.push(`${t.percent} percent of the GL movement`)
            if (Number(t.floor)) parts.push(`floor MWK ${Number(t.floor).toLocaleString()}`)
            return parts.length ? parts.join(', ') : 'to the cent'
        },
    },
    methods: {
        money(v) {
            return v === null || v === undefined ? '' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        },
        glName(code) {
            return { 4871: 'Legal Fees', 4873: 'Arrangement Fees', 4874: 'Insurance Fees', 4875: 'PCG Guarantee Fees' }[code] || ''
        },
        monthName(m) {
            return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][m - 1]
        },
        label(s) {
            return { AGREES: 'Agrees', DIFFERENCE: 'Difference', INCOMPLETE: 'Trial balance incomplete', NO_TB: 'No trial balance' }[s] || s
        },
        badge(s) {
            return s === 'AGREES' ? 'badge-green' : s === 'DIFFERENCE' ? 'badge-yellow' : 'badge-gray'
        },
        key(year, gl) {
            return `${year}-${gl}`
        },
        isOpen(year, gl) {
            return !!this.open[this.key(year, gl)]
        },
        toggle(year, gl) {
            this.open = { ...this.open, [this.key(year, gl)]: !this.isOpen(year, gl) }
        },
    },
}
</script>
