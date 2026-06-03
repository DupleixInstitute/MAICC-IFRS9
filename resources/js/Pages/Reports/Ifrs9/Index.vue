<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    company: { type: String, default: '' },
})

const period = ref(props.periods[0] ?? '')
const activeTab = ref(props.categories.length ? props.categories[0].name : '')

const current = computed(() =>
    props.categories.find(c => c.name === activeTab.value) || { reports: [] })
</script>

<template>
    <AppLayout title="IFRS 9 Reports">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">IFRS 9 Reporting Suite</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-gradient-to-r from-maiic-600 to-maiic-800 rounded-2xl shadow-lg p-7 text-white mb-6">
                    <p class="text-xs uppercase tracking-widest opacity-80">{{ company }}</p>
                    <h1 class="text-2xl font-bold mt-1">IFRS 9 Reports</h1>
                    <p class="opacity-90 mt-1 text-sm max-w-2xl">
                        Pick a section, then a report — only the report you choose is loaded.
                        Every report exports to PDF.
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <label class="text-sm font-medium opacity-90">Reporting Period</label>
                        <select v-model="period"
                                class="rounded-lg border-0 text-gray-800 text-sm py-2 px-3 shadow focus:ring-2 focus:ring-white">
                            <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                            <option v-if="!periods.length" value="">No ECL-calculated periods</option>
                        </select>
                    </div>
                </div>

                <div v-if="!periods.length"
                     class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 text-sm">
                    No reporting period has a calculated ECL yet. Run the ECL calculation first.
                </div>

                <div class="flex flex-wrap gap-2 border-b border-gray-200 mb-6">
                    <button v-for="cat in categories" :key="cat.name"
                            @click="activeTab = cat.name"
                            :class="['px-4 py-2 text-sm font-medium rounded-t-lg transition-colors',
                                     activeTab === cat.name
                                        ? 'bg-maiic-600 text-white'
                                        : 'text-gray-500 hover:text-maiic-700 hover:bg-maiic-50']">
                        {{ cat.name }}
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <Link v-for="r in current.reports" :key="r.key"
                          :href="route('ifrs9-reports.' + r.key, period ? { period } : {})"
                          class="group block rounded-xl bg-white shadow-sm hover:shadow-lg transition-all border border-gray-100 overflow-hidden">
                        <div class="h-1.5 bg-maiic-600"></div>
                        <div class="p-5">
                            <h3 class="font-semibold text-gray-900 group-hover:text-maiic-700">{{ r.title }}</h3>
                            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">{{ r.subtitle }}</p>
                            <span class="inline-flex items-center text-maiic-700 text-sm font-medium mt-3">
                                Open
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
