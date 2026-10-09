<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">Reference Rates</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">Reference Rates</h2>
          <p class="mt-1 text-sm text-gray-600">The {{ index }} series by effective date: the rate every PLR-linked loan reprices from</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <select v-if="indexes.length > 1" v-model="form.index" class="maiic-select !w-32" title="Index" aria-label="Index" @change="apply">
            <option v-for="i in indexes" :key="i" :value="i">{{ i }}</option>
          </select>
          <Link :href="route('eir-intake.index', { type: 'reference_rates' })" class="primary-btn">Import reference rates</Link>
        </div>
      </div>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4">
          <h3 class="font-semibold text-gray-900">{{ index }} series, newest first</h3>
          <p class="mt-1 text-xs text-gray-500"><strong>Change</strong> is the move from the previous row in percentage points; a grey row left the rate where it was.</p>
          <details class="group mt-2 text-xs text-gray-600">
            <summary class="cursor-pointer select-none font-semibold text-maiic-700 hover:underline">How this works</summary>
            <p class="mt-1 max-w-4xl">The spread added to the prime rate (margin) on each loan is derived from this series: the loan-book rate at each month end minus the
              {{ index }} rate in force that day. A loan whose spread moves by more than {{ tolerancePp.toFixed(2) }} percentage points across months is
              held for review rather than given a spread. Dates must be written year first (yyyy-mm-dd); a file with any other date shape is refused as a whole.
              A row with no change is a review that left the rate where it was: it is kept as delivered but it is not a rate change.
              <strong>As delivered</strong> and <strong>How it was read</strong> show what the file said and whether the day and month had to be swapped back.</p>
          </details>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr>
                <th class="th">Effective date</th>
                <th class="th text-right">Rate</th>
                <th class="th text-right">Change</th>
                <th class="th">Source row</th>
                <th class="th">As delivered</th>
                <th class="th">How it was read</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in pagedSeries" :key="r.id" :class="r.is_change ? '' : 'text-gray-400'">
                <td class="td font-semibold tabular-nums" :class="r.is_change ? 'text-gray-900' : ''">{{ r.effective_date }}</td>
                <td class="td text-right tabular-nums" :class="r.is_change ? 'font-semibold text-gray-900' : ''">{{ pct(r.rate) }}</td>
                <td class="td text-right tabular-nums">
                  <span v-if="r.change === null" class="text-xs text-gray-500">opening rate</span>
                  <span v-else-if="!r.is_change" class="text-xs">no change</span>
                  <span v-else :class="r.change > 0 ? 'text-rose-700' : 'text-emerald-700'">{{ signed(r.change) }}</span>
                </td>
                <td class="td text-xs">{{ r.source_row || '-' }}</td>
                <td class="td text-xs font-mono">{{ r.as_delivered || '-' }}</td>
                <td class="td text-xs">
                  <span v-if="r.interpretation" :class="isRepaired(r) ? 'rounded bg-amber-100 px-1.5 py-0.5 text-amber-800' : ''">{{ r.interpretation }}</span>
                  <span v-else>-</span>
                </td>
              </tr>
              <tr v-if="!series.length">
                <td colspan="6" class="p-10 text-center text-sm text-gray-500">
                  No {{ index }} rates are loaded yet.
                  <Link :href="route('eir-intake.index', { type: 'reference_rates' })" class="font-semibold text-maiic-700 underline">Import the reference-rate file</Link>
                  to start the series.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="series.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

const props = defineProps({
  index: { type: String, default: 'PLR' },
  indexes: { type: Array, default: () => [] },
  summary: { type: Object, required: true },
  series: { type: Array, default: () => [] },
  tolerancePp: { type: Number, default: 0.15 },
})

const form = reactive({ index: props.index })
const page = ref(1)
const pagedSeries = computed(() => props.series.slice((page.value - 1) * 15, page.value * 15))
const cards = computed(() => [
  { label: `${props.index} rate in force`, value: props.summary.current_rate === null ? '-' : pct(props.summary.current_rate), sub: `since ${props.summary.last_date || '-'}` },
  { label: 'Rate changes', value: Number(props.summary.changes || 0), sub: 'including the opening rate' },
  { label: 'Rows loaded', value: Number(props.summary.rows || 0), sub: `${number(props.summary.rows - props.summary.changes)} repeat the previous rate` },
  { label: 'First date', value: props.summary.first_date || '-' },
  { label: 'Last date', value: props.summary.last_date || '-' },
  { label: 'Repaired rows', value: Number(props.summary.repaired_rows || 0), sub: 'day and month swapped back', valueClass: props.summary.repaired_rows ? 'text-amber-700' : '', accent: props.summary.repaired_rows ? '#d97706' : null },
])

const apply = () => router.get(route('eir-reference-rates.index'), { index: form.index },
  { preserveState: false, preserveScroll: true, replace: true })

const number = (v) => Number(v || 0).toLocaleString()
const pct = (v) => Number(v).toFixed(2) + '%'
const signed = (v) => (v > 0 ? '+' : '') + Number(v).toFixed(2) + ' pts'
const isRepaired = (r) => String(r.interpretation || '').toUpperCase().startsWith('REPAIRED')
</script>
