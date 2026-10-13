<template>
    <app-layout title="Product Groups" description="The loan product groups that loans are reported and staged under">
        <template #actions>
            <input v-model="search" type="search" class="maiic-input w-56" placeholder="Search product groups" aria-label="Search product groups"/>
            <button v-if="can('loans.products.create') && (unlisted || []).length" type="button" class="secondary-btn" @click="sync">Add the {{ unlisted.length }} from the loan book</button>
            <Link v-if="can('loans.products.create')" :href="route('groups.create')" class="primary-btn">New product group</Link>
        </template>

        <!-- live counts: the groups listed, the portfolios, and how the latest loan book uses the groups -->
        <KpiRow class="mb-4" :cards="cards"/>

        <p v-if="(unlisted || []).length" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:text-amber-200">The loan book of {{ periodLabel(latestPeriod) }} uses {{ unlisted.length }} product group(s) not listed here: {{ unlisted.join(', ') }}.</p>
        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key" :class="col.num ? 'num' : ''" :aria-sort="ariaSort(col.key)">
                            <button type="button" class="inline-flex items-center gap-1.5 uppercase tracking-wider hover:text-maiicgold-400 focus:outline-none focus-visible:underline"
                                    :class="col.num ? 'flex-row-reverse' : ''" @click="sortBy(col.key)"
                                    :title="'Sort by ' + col.label.toLowerCase()">
                                <span>{{ col.label }}</span>
                                <font-awesome-icon :icon="sortIcon(col.key)" class="h-3 w-3" :class="sort.key === col.key ? 'opacity-100' : 'opacity-40'" aria-hidden="true"/>
                            </button>
                        </th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="group in pageRows" :key="group.id">
                        <td class="font-semibold text-gray-900">{{ group.name }}</td>
                        <td>{{ group.description || '-' }}</td>
                        <td class="num">{{ loans(group).toLocaleString() }}</td>
                        <td class="num">{{ loans(group) ? formatAmount(gross(group)) : '-' }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="route('groups.edit', group.id)" :deletable="true" @delete="destroy(group)"/>
                        </td>
                    </tr>
                    <tr v-if="!pageRows.length">
                        <td class="maiic-empty" :colspan="columns.length + 1">{{ search ? 'No product group matches the search.' : 'No product group yet. Use New product group to add one.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <ClientPager v-model="page" :total="filtered.length" :per-page="perPage"/>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
import ClientPager from '@/Components/ClientPager.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import { Link } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
    components: { AppLayout, RowActions, ClientPager, KpiRow, Link },
    props: {
        groups: { type: Array, default: () => [] },
        filters: Object,
        loansByGroup: Object,
        grossByGroup: Object,
        latestPeriod: String,
        unlisted: Array,
        portfolioCount: Number,
    },
    data() {
        return {
            search: (this.filters && this.filters.search) || '',
            sort: { key: 'name', dir: 'asc' },
            page: 1,
            perPage: 15,
        }
    },
    computed: {
        columns() {
            const p = this.latestPeriod ? ' in ' + this.periodLabel(this.latestPeriod) : ''
            return [
                { key: 'name', label: 'Name' },
                { key: 'description', label: 'Description' },
                { key: 'loans', label: 'Loans' + p, num: true },
                { key: 'gross', label: 'Gross carrying amount', num: true },
            ]
        },
        filtered() {
            const q = this.search.trim().toLowerCase()
            const rows = q
                ? this.groups.filter(g => (g.name || '').toLowerCase().includes(q) || (g.description || '').toLowerCase().includes(q))
                : this.groups.slice()
            const { key, dir } = this.sort
            const val = g => key === 'loans' ? this.loans(g) : key === 'gross' ? this.gross(g) : String(g[key] || '').toLowerCase()
            rows.sort((a, b) => {
                const x = val(a), y = val(b)
                const c = typeof x === 'number' ? x - y : x.localeCompare(y)
                return dir === 'asc' ? c : -c
            })
            return rows
        },
        pageRows() {
            const start = (this.page - 1) * this.perPage
            return this.filtered.slice(start, start + this.perPage)
        },
        cards() {
            const book = this.loansByGroup || {}
            const bookGroups = Object.keys(book)
            const facilities = bookGroups.reduce((t, g) => t + Number(book[g] || 0), 0)
            const gross = Object.values(this.grossByGroup || {}).reduce((t, v) => t + Number(v || 0), 0)
            const unlisted = (this.unlisted || []).length
            const idle = this.groups.filter(g => !this.loans(g)).length
            const period = this.latestPeriod ? this.periodLabel(this.latestPeriod) : null
            return [
                { label: 'Product groups', value: this.groups.length, accent: '#0284c7',
                  sub: period ? `${idle.toLocaleString()} with no loans in ${period}` : 'No loan book loaded' },
                { label: 'Loan portfolios', value: this.portfolioCount ?? '-', accent: '#15803d', sub: 'The portfolios loans are grouped into' },
                { label: 'Loan book groups listed', raw: true, accent: unlisted ? '#d97706' : '#15803d',
                  value: period ? `${(bookGroups.length - unlisted).toLocaleString()} of ${bookGroups.length.toLocaleString()}` : '-',
                  sub: period ? (unlisted ? `${unlisted} used in ${period} but not listed` : `Every group used in ${period} is listed`) : '-' },
                { label: period ? `Facilities in ${period}` : 'Facilities', value: period ? facilities : '-', accent: '#65a30d',
                  sub: period ? `Gross ${this.compact(gross)} across ${bookGroups.length} group(s)` : '-', hint: period ? `Gross carrying amount ${this.formatAmount(gross)}` : null },
            ]
        },
    },
    watch: {
        search() { this.page = 1 },
    },
    methods: {
        loans(g) { return Number((this.loansByGroup || {})[g.name] || 0) },
        gross(g) { return Number((this.grossByGroup || {})[g.name] || 0) },
        sortBy(key) {
            this.sort = this.sort.key === key
                ? { key, dir: this.sort.dir === 'asc' ? 'desc' : 'asc' }
                : { key, dir: key === 'loans' || key === 'gross' ? 'desc' : 'asc' }
            this.page = 1
        },
        sortIcon(key) {
            if (this.sort.key !== key) return 'sort'
            return this.sort.dir === 'asc' ? 'sort-up' : 'sort-down'
        },
        ariaSort(key) {
            if (this.sort.key !== key) return 'none'
            return this.sort.dir === 'asc' ? 'ascending' : 'descending'
        },
        periodLabel(p) {
            if (!p) return ''
            const [y, m] = String(p).split('-')
            return m ? `${MONTHS[Number(m) - 1]} ${y}` : p
        },
        formatAmount(v) {
            const n = Number(v || 0)
            const code = this.$page.props.currency?.code
            return (code ? code + ' ' : '') + n.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        },
        // compact money for the cards: MWK 23.32B / 8.62M / 950.0K
        compact(v) {
            const n = Number(v || 0), a = Math.abs(n)
            const code = this.$page.props.currency?.code
            const c = code ? code + ' ' : ''
            if (a >= 1e9) return c + (n / 1e9).toFixed(2) + 'B'
            if (a >= 1e6) return c + (n / 1e6).toFixed(2) + 'M'
            if (a >= 1e3) return c + (n / 1e3).toFixed(1) + 'K'
            return c + n.toLocaleString('en-GB')
        },
        sync() { this.$inertia.post(this.route('groups.sync'), {}, { preserveScroll: true }) },
        async destroy(group) {
            if (!(await confirmDialog({
                title: 'Delete the ' + group.name + ' product group?',
                message: 'The product group is removed from the list. This cannot be undone.',
                confirmLabel: 'Delete',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('groups.destroy', group.id), { preserveScroll: true })
        },
    },
}
</script>
