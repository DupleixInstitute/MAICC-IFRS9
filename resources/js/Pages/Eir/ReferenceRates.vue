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
          <Link :href="route('eir-data.index')" class="secondary-btn">EIR Data</Link>
          <Link :href="route('eir-intake.index', { type: 'reference_rates' })" class="primary-btn">Import reference rates</Link>
        </div>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-5">
      <div v-if="indexes.length > 1" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <form class="flex flex-col gap-3 md:flex-row md:items-end" @submit.prevent="apply">
          <div>
            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Index</label>
            <select v-model="form.index" class="form-input md:w-44" @change="apply">
              <option v-for="i in indexes" :key="i" :value="i">{{ i }}</option>
            </select>
          </div>
          <button type="submit" class="secondary-btn">Apply</button>
        </form>
      </div>

      <!-- Summary tile: the four numbers a reviewer checks the file against -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ summary.current_rate === null ? '-' : pct(summary.current_rate) }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ index }} rate in force</div>
          <div class="mt-1 text-xs text-gray-500">since {{ summary.last_date || '-' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ number(summary.changes) }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">Rate changes</div>
          <div class="mt-1 text-xs text-gray-500">including the opening rate</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ number(summary.rows) }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">Rows loaded</div>
          <div class="mt-1 text-xs text-gray-500">{{ number(summary.rows - summary.changes) }} repeat the previous rate</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ summary.first_date || '-' }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">First date</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold text-gray-900">{{ summary.last_date || '-' }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">Last date</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xl font-bold" :class="summary.repaired_rows ? 'text-amber-700' : 'text-gray-900'">{{ number(summary.repaired_rows) }}</div>
          <div class="mt-1 text-[11px] font-medium uppercase tracking-wide text-gray-500">Repaired rows</div>
          <div class="mt-1 text-xs text-gray-500">day and month swapped back</div>
        </div>
      </div>

      <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p>
          The spread added to the prime rate (margin) on each loan is derived from this series: the loan-book rate at each month end minus the
          {{ index }} rate in force that day. A loan whose spread moves by more than {{ tolerancePp.toFixed(2) }} percentage points across months is
          held for review rather than given a spread. Dates must be written year first (yyyy-mm-dd); a file with any other date shape is refused as a whole.
        </p>
      </div>

      <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-gray-200 p-4">
          <h3 class="font-semibold text-gray-900">{{ index }} series, newest first</h3>
          <p class="mt-1 text-xs text-gray-500">
            <strong>Change</strong> is the move from the previous row in percentage points. A row with no change is a review that left the rate where it was:
            it is kept as delivered but it is not a rate change. <strong>As delivered</strong> and <strong>How it was read</strong> show what the file said and
            whether the day and month had to be swapped back.
          </p>
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
              <tr v-for="r in series" :key="r.id" :class="r.is_change ? '' : 'text-gray-400'">
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
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  index: { type: String, default: 'PLR' },
  indexes: { type: Array, default: () => [] },
  summary: { type: Object, required: true },
  series: { type: Array, default: () => [] },
  tolerancePp: { type: Number, default: 0.15 },
})

const form = reactive({ index: props.index })

const apply = () => router.get(route('eir-reference-rates.index'), { index: form.index },
  { preserveState: true, preserveScroll: true, replace: true })

const number = (v) => Number(v || 0).toLocaleString()
const pct = (v) => Number(v).toFixed(2) + '%'
const signed = (v) => (v > 0 ? '+' : '') + Number(v).toFixed(2) + ' pts'
const isRepaired = (r) => String(r.interpretation || '').toUpperCase().startsWith('REPAIRED')
</script>
