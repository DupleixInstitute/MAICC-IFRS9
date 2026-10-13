<template>
  <div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="$emit('close')"
  >
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

    <!-- Modal -->
    <div class="relative flex max-h-[90vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-slate-800">

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 border-b px-6 py-4 dark:border-slate-700">
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Cumulative PD by grade and year</h3>
          <p class="mt-0.5 text-sm text-gray-600 dark:text-slate-300">
            {{ profile.name }}
            <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium" :class="profile.is_active ? 'bg-maiic-100 text-maiic-700 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200'">{{ profile.is_active ? 'Active' : 'Draft' }}</span>
          </p>
          <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">The probability that a borrower of the grade defaults within 1, 2, ... {{ maxTenor }} years.</p>
        </div>
        <button type="button" class="text-xl text-gray-500 hover:text-gray-700 dark:text-slate-400 dark:hover:text-slate-200" @click="$emit('close')">&times;</button>
      </div>

      <!-- Table -->
      <div class="overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-slate-900">
            <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-400">
              <th rowspan="2" class="px-4 py-2 text-left align-bottom font-semibold">Grade</th>
              <th rowspan="2" class="px-3 py-2 text-left align-bottom font-semibold">Broad grade</th>
              <th rowspan="2" class="px-3 py-2 text-right align-bottom font-semibold">PD range</th>
              <th :colspan="maxTenor" class="border-b border-gray-200 px-2 pt-2 pb-1 text-center font-semibold dark:border-slate-700">Cumulative PD within</th>
            </tr>
            <tr class="text-xs text-gray-500 dark:text-slate-400">
              <th v-for="year in maxTenor" :key="year" class="px-1 py-1.5 text-center font-semibold">{{ year }} yr</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr v-for="grade in grades" :key="grade.id" class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30">
              <td class="whitespace-nowrap px-4 py-2.5">
                <div class="flex items-center gap-3">
                  <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="broadTone(grade.broad_grade).badge">{{ grade.grade_code }}</span>
                  <div>
                    <p class="font-medium text-gray-900 dark:text-slate-100">{{ grade.description || grade.grade_name }}</p>
                    <p v-if="grade.sp_rating" class="text-xs text-gray-500 dark:text-slate-400">S&amp;P <span class="font-semibold text-gray-700 dark:text-slate-200">{{ grade.sp_rating }}</span></p>
                  </div>
                </div>
              </td>
              <td class="whitespace-nowrap px-3 py-2.5">
                <span v-if="grade.broad_grade" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="broadTone(grade.broad_grade).pill">{{ grade.broad_grade }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-600 dark:text-slate-300">
                <template v-if="grade.lower_bound !== null && grade.upper_bound !== null">{{ formatPd(grade.lower_bound) }} to {{ formatPd(grade.upper_bound) }}</template>
                <template v-else>-</template>
              </td>
              <td v-for="year in maxTenor" :key="year" class="px-0.5 py-1 text-center">
                <span v-if="grade.pds[year] !== null && grade.pds[year] !== undefined" class="block rounded-md px-1.5 py-1.5 text-xs tabular-nums sm:text-sm" :class="heat(grade.pds[year])">{{ formatPd(grade.pds[year]) }}</span>
                <span v-else class="text-gray-400">-</span>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="grades.length === 0" class="p-12 text-center text-gray-500 dark:text-slate-400">No grades configured for this profile.</div>
      </div>

      <!-- Legend and source -->
      <div class="space-y-2 border-t px-6 py-3 text-xs text-gray-600 dark:border-slate-700 dark:text-slate-300">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-semibold">Cumulative PD:</span>
          <span v-for="b in bands" :key="b.label" class="rounded-md px-2 py-0.5" :class="b.cls">{{ b.label }}</span>
        </div>
        <template v-if="profile.description">
          <p class="text-gray-500 dark:text-slate-400"><span class="font-semibold text-gray-600 dark:text-slate-300">Source:</span> {{ source.text }}</p>
          <p v-if="source.url" class="text-gray-500 dark:text-slate-400">
            <span class="font-semibold text-gray-600 dark:text-slate-300">Link given in the workbook:</span>
            <a :href="source.url" target="_blank" rel="noopener noreferrer" class="ml-1 inline-flex items-center gap-1 break-all text-maiic-700 underline decoration-dotted underline-offset-2 hover:text-maiic-800 dark:text-emerald-300 dark:hover:text-emerald-200">{{ source.label }} <i class="fas fa-external-link-alt text-[10px]"></i></a>
          </p>
          <div v-if="source.leftOut.length" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/20 dark:text-amber-200">
            <p class="font-semibold">Left out of this profile</p>
            <ul class="mt-0.5 list-disc pl-5"><li v-for="(m, i) in source.leftOut" :key="i">{{ m }}</li></ul>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  show: Boolean,
  profile: Object,
  grades: Array,
  maxTenor: Number
})

// the profile's description, as the S&P import writes it: the source text, "Source: <link>" and "Left out: ..."
const source = computed(() => {
  let text = props.profile?.description || ''
  let leftOut = []
  const lo = text.indexOf('Left out:')
  if (lo >= 0) {
    leftOut = text.slice(lo + 9).split(/(?<=\.)\s+(?=Grade )/).map(t => t.trim()).filter(Boolean)
    text = text.slice(0, lo).trim()
  }
  const m = text.match(/Source:\s*(https?:\/\/\S+)/)
  const url = m ? m[1].replace(/[.,;]$/, '') : null
  if (m) text = text.replace(m[0], '').trim()
  let label = url
  try { if (url) { const u = new URL(url); const slug = u.pathname.split('/').filter(Boolean).pop() || ''; label = u.hostname + ' - ' + slug.replace(/-\d+$/, '').replace(/^\d+-/, '').replace(/-/g, ' ') } } catch (e) { /* keep the raw link */ }
  return { text, url, label, leftOut }
})

function formatPd(value) {
  return (Number(value) * 100).toFixed(2) + '%'
}

// colour steps for a cumulative PD; light and dark variants
const bands = [
  { max: 0.005, label: 'under 0.5%', cls: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' },
  { max: 0.02, label: '0.5% to 2%', cls: 'bg-lime-50 text-lime-800 dark:bg-lime-900/30 dark:text-lime-200' },
  { max: 0.10, label: '2% to 10%', cls: 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' },
  { max: 0.30, label: '10% to 30%', cls: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200' },
  { max: 0.9999, label: '30% to under 100%', cls: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' },
  { max: Infinity, label: '100% (default)', cls: 'bg-red-200 font-semibold text-red-900 dark:bg-red-900/70 dark:text-red-100' },
]
function heat(pd) {
  const v = Number(pd)
  return (bands.find(b => v < b.max) || bands[bands.length - 1]).cls
}

// the broad grade's colour: investment grade green, monitoring amber, special monitoring orange, default red
function broadTone(broad) {
  const b = (broad || '').toLowerCase()
  if (b.includes('investment')) return { badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200', pill: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' }
  if (b.includes('special')) return { badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200', pill: 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-200' }
  if (b.includes('monitoring')) return { badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200', pill: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200' }
  if (b.includes('default') || b.includes('impaired')) return { badge: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200', pill: 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' }
  return { badge: 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-200', pill: 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-200' }
}
</script>
