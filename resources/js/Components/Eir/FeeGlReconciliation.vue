<template>
    <!-- One row per year; a year opens to its GLs and a GL to its months. Years with
         no trial balance loaded at all cannot be checked and share one muted row. -->
    <div v-if="report" class="maiic-panel">
        <div class="flex flex-col gap-1 border-b border-gray-200 px-5 py-3 md:flex-row md:items-baseline md:justify-between dark:border-slate-700">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Fees against the fee GLs (4871 Legal, 4873 Arrangement)</h4>
            <span class="text-xs text-gray-500 dark:text-slate-400">Governance Centre: {{ report.setting || 'not set' }}</span>
        </div>

        <p v-if="!report.enabled" class="px-5 py-3 text-sm text-gray-600 dark:text-slate-300">
            The check is switched off in the Governance Centre ("Fee imports checked against the fee GLs"), so imported fees are not compared with the GL.
            <Link v-if="canGovern" :href="route('eir-governance.index')" class="font-medium text-maiic-700 underline">Open the Governance Centre</Link>
        </p>

        <template v-else>
            <p class="px-5 py-2 text-xs text-gray-500 dark:text-slate-400">
                The fees held for each year, by the month of their date, beside the trial balance movement of their GL.
                A difference means fees not yet imported, or fees on loans outside the engine's schemes; the GL is never adjusted.
                Band: {{ toleranceText }}. Open a year for its GLs, and a GL for its months.
            </p>

            <p v-if="!years.length" class="px-5 pb-4 text-sm text-gray-600 dark:text-slate-300">No dated fees to check yet.</p>

            <div v-else class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th class="num">Fees imported</th>
                            <th class="num">GL movement</th>
                            <th class="num">Difference</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="unchecked.length" class="text-gray-500 dark:text-slate-400">
                            <td class="whitespace-nowrap font-semibold">{{ uncheckedLabel }}</td>
                            <td class="num">{{ money(sum(unchecked, 'imported')) }}</td>
                            <td class="num">-</td>
                            <td class="num">-</td>
                            <td><span class="badge-gray">No trial balance</span></td>
                            <td class="text-xs">No trial balance is loaded for {{ unchecked.length === 1 ? 'this year' : 'these years' }}, so the fees cannot be checked.</td>
                        </tr>
                        <template v-for="y in checked" :key="y.year">
                            <tr>
                                <td class="font-semibold">{{ y.year }}</td>
                                <td class="num">{{ money(y.imported) }}</td>
                                <td class="num">{{ money(y.gl) }}</td>
                                <td class="num" :class="y.status === 'DIFFERENCE' ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ money(y.difference) }}</td>
                                <td><span :class="badge(y.status)">{{ label(y.status) }}</span></td>
                                <td class="text-right">
                                    <button type="button" class="text-xs font-medium text-maiic-700 underline dark:text-maiic-300" @click="toggle(y.year)">{{ isOpen(y.year) ? 'Hide GLs' : 'GLs' }}</button>
                                </td>
                            </tr>
                            <template v-if="isOpen(y.year)">
                                <template v-for="g in y.glList" :key="y.year + g.gl_code">
                                    <tr class="bg-gray-50 dark:bg-slate-800/60">
                                        <td class="text-sm"><span class="ml-5 inline-block">{{ g.gl_code }} {{ glName(g.gl_code) }}</span></td>
                                        <td class="num">{{ money(g.imported) }}</td>
                                        <td class="num">{{ money(g.gl) }}</td>
                                        <td class="num" :class="g.status === 'DIFFERENCE' ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ money(g.difference) }}</td>
                                        <td><span :class="badge(g.status)">{{ label(g.status) }}</span>
                                            <div v-if="g.months_without_tb" class="text-xs text-gray-500 dark:text-slate-400">{{ g.months_without_tb }} month(s) with no trial balance</div>
                                        </td>
                                        <td class="text-right"><button v-if="g.months.length" type="button" class="text-xs font-medium text-maiic-700 underline dark:text-maiic-300" @click="toggle(y.year + '-' + g.gl_code)">{{ isOpen(y.year + '-' + g.gl_code) ? 'Hide months' : 'Months' }}</button></td>
                                    </tr>
                                    <tr v-for="m in (isOpen(y.year + '-' + g.gl_code) ? g.months : [])" :key="y.year + g.gl_code + m.period" class="bg-gray-50 text-xs dark:bg-slate-800/60">
                                        <td><span class="ml-10 inline-block">{{ monthName(m.month) }} {{ y.year }}<span v-if="m.tb_basis === 'PRECLOSING'" class="text-gray-500"> (pre-closing)</span></span></td>
                                        <td class="num">{{ money(m.imported) }}</td>
                                        <td class="num">{{ m.gl === null ? 'no TB' : money(m.gl) }}</td>
                                        <td class="num">{{ m.difference === null ? '' : money(m.difference) }}</td>
                                        <td><span :class="badge(m.status)">{{ label(m.status) }}</span></td>
                                        <td></td>
                                    </tr>
                                </template>
                                <tr v-if="y.paid_excluded.lines || y.unmapped.lines" class="bg-gray-50 dark:bg-slate-800/60">
                                    <td colspan="6" class="text-xs text-gray-500 dark:text-slate-400">
                                        <span v-if="y.paid_excluded.lines">{{ y.paid_excluded.lines }} PAID line(s), {{ money(y.paid_excluded.amount) }}, left out: costs paid out belong on 6752, not on the income GLs. </span>
                                        <span v-if="y.unmapped.lines">{{ y.unmapped.lines }} line(s), {{ money(y.unmapped.amount) }}, name no fee GL and are not arrangement or legal fees.</span>
                                    </td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>

            <p v-if="report.undated && report.undated.lines" class="px-5 py-2 text-xs text-amber-700 dark:text-amber-300">
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
            const years = this.report && this.report.years ? Object.values(this.report.years) : []
            return years.map(y => {
                const glList = Object.values(y.gls || {})
                const total = k => glList.reduce((s, g) => s + Number(g[k] || 0), 0)
                const noTb = glList.length > 0 && glList.every(g => g.months.length > 0 && Number(g.months_without_tb || 0) >= g.months.length)
                const status = noTb ? 'NO_TB'
                    : glList.some(g => g.status === 'DIFFERENCE') ? 'DIFFERENCE'
                    : glList.every(g => g.status === 'AGREES') ? 'AGREES' : 'INCOMPLETE'
                return { ...y, glList, status, imported: total('imported'), gl: total('gl'), difference: total('difference') }
            })
        },
        unchecked() {
            return this.years.filter(y => y.status === 'NO_TB')
        },
        checked() {
            return this.years.filter(y => y.status !== 'NO_TB').slice().reverse()
        },
        uncheckedLabel() {
            const ys = this.unchecked.map(y => Number(y.year)).sort()
            return ys.length === 1 ? String(ys[0]) : `${ys[0]} to ${ys[ys.length - 1]}`
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
        sum(rows, k) {
            return rows.reduce((s, r) => s + Number(r[k] || 0), 0)
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
        isOpen(k) {
            return !!this.open[k]
        },
        toggle(k) {
            this.open = { ...this.open, [k]: !this.isOpen(k) }
        },
    },
}
</script>
