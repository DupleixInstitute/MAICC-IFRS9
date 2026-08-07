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

// Section accents drawn from the MAIIC logo family (greens, golds, red,
// charcoal) so every tab is visibly coloured even when inactive.
const ACCENTS = ['#16a34a', '#d97706', '#dc2626', '#15803d', '#b45309', '#991b1b', '#f59e0b', '#374151']
const accent = (name) => {
    const i = props.categories.findIndex(c => c.name === name)
    return ACCENTS[(i >= 0 ? i : 0) % ACCENTS.length]
}
const currentAccent = computed(() => accent(activeTab.value))
</script>

<template>
    <AppLayout title="IFRS 9 Reports">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">IFRS 9 Reporting Suite</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- compact header strip (same deep-green ramp as the sidebar) -->
                <div class="rounded-xl shadow p-4 text-white mb-5 flex flex-wrap items-center justify-between gap-3"
                     style="background: linear-gradient(120deg, #0b2b1a 0%, #14532d 55%, #15803d 100%); border: 1px solid rgba(212,160,23,0.25);">
                    <div class="min-w-0">
                        <h1 class="text-lg font-bold leading-tight">IFRS 9 Reports</h1>
                        <p class="opacity-80 text-xs">{{ company }} · pick a section, then a report. Every report exports to PDF.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wider opacity-90">Reporting Period</label>
                        <select v-model="period"
                                class="rounded-lg border-0 text-gray-800 text-sm py-1.5 px-3 shadow focus:ring-2 focus:ring-white">
                            <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                            <option v-if="!periods.length" value="">No ECL-calculated periods</option>
                        </select>
                    </div>
                </div>

                <div v-if="!periods.length"
                     class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-6 text-sm">
                    No reporting period has a calculated ECL yet. Run the ECL calculation first.
                </div>

                <!-- coloured section tabs -->
                <div class="flex flex-wrap gap-2 mb-6">
                    <button v-for="cat in categories" :key="cat.name"
                            @click="activeTab = cat.name"
                            class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition-all border"
                            :style="activeTab === cat.name
                                ? { backgroundColor: accent(cat.name), borderColor: accent(cat.name), color: '#fff' }
                                : { backgroundColor: accent(cat.name) + '14', borderColor: accent(cat.name) + '55', color: accent(cat.name) }">
                        <span class="h-2 w-2 rounded-full"
                              :style="{ backgroundColor: activeTab === cat.name ? '#fff' : accent(cat.name) }"></span>
                        {{ cat.name }}
                        <span class="rounded-full px-1.5 text-[11px] font-bold"
                              :style="activeTab === cat.name
                                  ? { backgroundColor: 'rgba(255,255,255,0.25)', color: '#fff' }
                                  : { backgroundColor: accent(cat.name) + '22', color: accent(cat.name) }">
                            {{ (cat.reports || []).length }}
                        </span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <Link v-for="r in current.reports" :key="r.key"
                          :href="route('ifrs9-reports.' + r.key, period ? { period } : {})"
                          class="group block rounded-xl bg-white shadow-sm hover:shadow-lg transition-all border border-gray-100 overflow-hidden">
                        <div class="h-1.5" :style="{ backgroundColor: currentAccent }"></div>
                        <div class="p-5">
                            <h3 class="font-semibold text-gray-900 group-hover:text-maiic-700">{{ r.title }}</h3>
                            <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">{{ r.subtitle }}</p>
                            <span class="inline-flex items-center text-sm font-medium mt-3" :style="{ color: currentAccent }">
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
