<script setup>
import { ref, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ReportIcon from '../Partials/ReportIcon.vue'
import TabBar from '../Partials/TabBar.vue'
import KpiRow from '../Partials/KpiRow.vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    company: { type: String, default: '' },
})

// The group opened last comes back on ?tab= (the report pages link back with it).
const initialTab = (() => {
    try {
        const t = new URLSearchParams(window.location.search).get('tab')
        if (t && props.categories.some(c => c.key === t)) return t
    } catch (e) { /* no window during SSR */ }
    return props.categories[0]?.key ?? ''
})()

const period = ref(props.periods[0] ?? '')
const activeTab = ref(initialTab)
const search = ref('')

watch(activeTab, (t) => {
    try {
        const url = new URL(window.location.href)
        url.searchParams.set('tab', t)
        window.history.replaceState(window.history.state, '', url)
    } catch (e) { /* ignore */ }
})

const tabs = computed(() => props.categories.map(c => ({ key: c.key, label: c.name, count: c.reports.length })))
const totalReports = computed(() => props.categories.reduce((n, c) => n + c.reports.length, 0))
const current = computed(() => props.categories.find(c => c.key === activeTab.value) || { reports: [], description: '' })

// A search looks across every group.
const matches = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return null
    return props.categories.flatMap(c => c.reports
        .filter(r => (r.title + ' ' + r.description + ' ' + c.name).toLowerCase().includes(q))
        .map(r => ({ ...r, group: c.name })))
})
const shown = computed(() => matches.value ?? current.value.reports)

function href(r) {
    return route(r.route, r.period && period.value ? { period: period.value } : {})
}

const badge = { PDF: 'maiic-badge-red', Excel: 'maiic-badge-green', CSV: 'maiic-badge-grey', ZIP: 'maiic-badge-gold' }
</script>

<template>
    <AppLayout title="Reports">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Reports</h2>
            <p class="mt-0.5 text-sm text-gray-500">Every report, reconciliation and export of the system in one place. Pick a group, then a report; most download as PDF, Excel or CSV.</p>
        </template>

        <template #actions>
            <label for="hub-period" class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Reporting period</label>
            <select id="hub-period" v-model="period" class="maiic-select w-40" :disabled="!periods.length">
                <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                <option v-if="!periods.length" value="">No ECL periods</option>
            </select>
        </template>

        <div class="w-full space-y-5">
            <div v-if="!periods.length" class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <ReportIcon name="alert" class="h-5 w-5 flex-none text-amber-600"/>
                <div>
                    <p class="font-semibold">No reporting period has a calculated ECL yet</p>
                    <p class="mt-0.5">Run the ECL calculation for a month first; the ECL reports then open on that month. The reconciliations, exports and EIR reports still work.</p>
                </div>
            </div>

            <KpiRow :items="[
                { label: 'Reports you can open', value: totalReports },
                { label: 'Report groups', value: categories.length },
                { label: 'Latest ECL period', value: periods[0] || '-', tone: 'amber' },
                { label: 'Periods with a calculated ECL', value: periods.length, tone: 'amber' },
            ]"/>

            <div class="maiic-panel">
                <TabBar v-model="activeTab" :tabs="tabs" @update:modelValue="search = ''"/>

                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ matches ? 'Search results' : current.name }}</h3>
                        <p class="text-xs text-gray-500">{{ matches ? matches.length + ' report(s) match "' + search + '" across all groups' : current.description }}</p>
                    </div>
                    <div class="relative w-full md:w-72">
                        <ReportIcon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"/>
                        <input v-model="search" type="search" class="maiic-input pl-8" placeholder="Find a report" aria-label="Find a report">
                    </div>
                </div>

                <div v-if="shown.length" class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="r in shown" :key="r.key" :href="href(r)"
                          class="group flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-maiic-400 hover:shadow-md">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-maiic-50 text-maiic-700 transition group-hover:bg-maiic-600 group-hover:text-white">
                            <ReportIcon :name="r.icon" class="h-5 w-5"/>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-2">
                                <span class="block font-bold text-gray-900 group-hover:text-maiic-700">{{ r.title }}</span>
                                <ReportIcon name="arrow" class="mt-0.5 h-4 w-4 flex-none text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-maiic-600"/>
                            </span>
                            <span class="mt-0.5 block text-sm leading-snug text-gray-500">{{ r.description }}</span>
                            <span class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span v-if="r.group" class="maiic-badge maiic-badge-grey">{{ r.group }}</span>
                                <span v-for="f in r.formats" :key="f" class="maiic-badge" :class="badge[f] || 'maiic-badge-grey'">{{ f }}</span>
                            </span>
                        </span>
                    </Link>
                </div>
                <div v-else class="maiic-empty">
                    <template v-if="matches">No report matches "{{ search }}". Try a shorter word, such as ECL, stage or RBM.</template>
                    <template v-else>You do not have access to any report in this group. Ask your administrator if you need one.</template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
