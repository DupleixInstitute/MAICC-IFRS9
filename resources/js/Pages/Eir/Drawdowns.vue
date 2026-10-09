<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">Drawdowns</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">Drawdowns</h2>
          <p class="mt-1 text-sm text-gray-600">Every tranche paid out on a facility, and the commitment still undrawn</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a :href="route('eir-intake.sample', { type: 'disbursements' })" class="secondary-btn" title="The import's columns with worked example rows">Download sample CSV</a>
          <Link :href="route('eir-intake.index', { type: 'disbursements' })" class="primary-btn">Import drawdowns</Link>
        </div>
      </div>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <div class="maiic-panel">
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">Facilities, largest undrawn commitment first</h3>
            <p class="mt-1 text-xs text-gray-500">Choose a facility to see each tranche. <strong>Source</strong> says where the amount drawn came from.</p>
            <details class="group mt-2 text-xs text-gray-600">
            <summary class="cursor-pointer select-none font-semibold text-maiic-700 hover:underline">How this works</summary>
            <p class="mt-1 max-w-4xl">The undrawn commitment on a facility is its approved amount less what has been drawn. Where drawdown rows have been loaded, what has been drawn is the sum of the
              tranches up to the date chosen, so the figure can be struck at any date. Where no rows have been loaded, the figures fall back to the monthly Loan Book Report and the
              row says so: that source carries no dates, so a month with a tranche in it cannot be reconciled from it. Where neither source states an approved amount the commitment
              is shown as not known rather than as nil, because an empty table is not evidence that a facility is fully drawn.</p>
          </details>
          </div>
          <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <label class="text-xs font-semibold text-gray-500" for="dd-asof" title="Only drawdowns on or before this date are counted">Drawn up to</label>
            <input id="dd-asof" v-model="form.as_of" type="date" class="maiic-input !w-40" />
            <input v-model="form.search" type="text" class="maiic-input !w-56" placeholder="Facility number" aria-label="Facility" />
            <button type="submit" class="secondary-btn">Apply</button>
            <button v-if="form.search || form.as_of !== asOf" type="button" class="secondary-btn" @click="reset">Clear</button>
          </form>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr>
                <th class="th">Facility</th>
                <th class="th">Customer</th>
                <th class="th text-right">Approved</th>
                <th class="th text-right">Drawn</th>
                <th class="th text-right">Undrawn commitment</th>
                <th class="th text-center">Tranches</th>
                <th class="th">Source</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="f in pagedFacilities" :key="f.contract_id">
                <tr :class="open === f.contract_id ? 'bg-maiic-50' : ''">
                  <td class="td">
                    <button type="button" class="font-semibold text-maiic-700 underline" @click="toggle(f.contract_id)">{{ f.contract_id }}</button>
                  </td>
                  <td class="td text-sm">{{ f.customer || '-' }}</td>
                  <td class="td text-right tabular-nums">{{ f.approved === null ? 'not known' : money(f.approved) }}</td>
                  <td class="td text-right tabular-nums">{{ f.drawn === null ? 'not known' : money(f.drawn) }}</td>
                  <td class="td text-right font-semibold tabular-nums" :class="f.undrawn === null ? 'text-amber-700' : 'text-gray-900'">
                    {{ f.undrawn === null ? 'not known' : money(f.undrawn) }}
                  </td>
                  <td class="td text-center tabular-nums">{{ f.tranche_count }}</td>
                  <td class="td text-xs">
                    <span :class="f.drawn_source === 'DRAWDOWN_ROWS' ? 'rounded bg-emerald-100 px-1.5 py-0.5 text-emerald-800' : 'rounded bg-amber-100 px-1.5 py-0.5 text-amber-800'">
                      {{ sourceLabels[f.drawn_source] || f.drawn_source }}
                    </span>
                    <span v-if="f.loan_book_period && f.drawn_source !== 'DRAWDOWN_ROWS'" class="ml-1 text-gray-500">{{ f.loan_book_period }}</span>
                  </td>
                </tr>
                <tr v-if="open === f.contract_id">
                  <td colspan="7" class="bg-gray-50 px-4 py-3">
                    <div v-if="f.tranches.length" class="overflow-x-auto">
                      <table class="min-w-full">
                        <thead>
                          <tr>
                            <th class="th">Tranche</th>
                            <th class="th">Date drawn</th>
                            <th class="th text-right">Amount</th>
                            <th class="th">Reference</th>
                            <th class="th">Sub-account</th>
                            <th class="th">Loaded</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="t in f.tranches" :key="t.id">
                            <td class="td tabular-nums">{{ t.tranche_no === null ? '-' : t.tranche_no }}</td>
                            <td class="td tabular-nums">{{ t.disbursement_date }}</td>
                            <td class="td text-right tabular-nums">{{ money(t.amount) }}</td>
                            <td class="td text-xs">{{ t.reference || '-' }}</td>
                            <td class="td text-xs">{{ t.sub_account_no || '-' }}</td>
                            <td class="td text-xs text-gray-500">{{ t.loaded_at || '-' }}</td>
                          </tr>
                        </tbody>
                        <tfoot>
                          <tr class="border-t border-gray-300 font-semibold">
                            <td class="td" colspan="2">Total drawn</td>
                            <td class="td text-right tabular-nums">{{ money(f.drawn) }}</td>
                            <td class="td" colspan="3"></td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                    <p v-else class="text-sm text-gray-600">
                      No drawdown rows are loaded for this facility.
                      <Link :href="route('eir-intake.index', { type: 'disbursements' })" class="font-semibold text-maiic-700 underline">Import the drawdown file</Link>
                      to date each tranche; until then the amount drawn comes from the loan book.
                    </p>
                  </td>
                </tr>
              </template>
              <tr v-if="facilities.length" class="border-t-2 border-gray-300 bg-gray-50 font-semibold">
                <td class="td" colspan="2">Total, {{ number(totals.facilities) }} facilities</td>
                <td class="td text-right tabular-nums">{{ money(totals.approved) }}</td>
                <td class="td text-right tabular-nums">{{ money(totals.drawn) }}</td>
                <td class="td text-right tabular-nums text-maiic-700">{{ money(totals.undrawn) }}</td>
                <td class="td text-center tabular-nums">{{ number(totals.tranches) }}</td>
                <td class="td"></td>
              </tr>
              <tr v-if="!facilities.length">
                <td colspan="7" class="p-10 text-center text-sm text-gray-500">
                  No facilities to show.
                  <Link :href="route('eir-intake.index', { type: 'disbursements' })" class="font-semibold text-maiic-700 underline">Import the drawdown file</Link>
                  or load the contract master first.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="facilities.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

const props = defineProps({
  asOf: { type: String, required: true },
  search: { type: String, default: '' },
  facilities: { type: Array, default: () => [] },
  totals: { type: Object, required: true },
  sourceLabels: { type: Object, default: () => ({}) },
})

const form = reactive({ as_of: props.asOf, search: props.search })
const open = ref(null)
const page = ref(1)
watch(() => props.facilities, () => { page.value = 1 })
const pagedFacilities = computed(() => props.facilities.slice((page.value - 1) * 15, page.value * 15))
const cards = computed(() => {
  const t = props.totals
  const without = Number(t.from_loan_book || 0) + Number(t.not_known || 0)
  return [
    { label: 'Approved', value: money(t.approved), sub: `${number(t.facilities)} facilities` },
    { label: 'Drawn', value: money(t.drawn), sub: `up to ${props.asOf}` },
    { label: 'Undrawn commitment', value: money(t.undrawn), sub: 'reported separately under IFRS 9', valueClass: 'text-maiic-700' },
    { label: 'Drawdowns loaded', value: Number(t.tranches || 0), sub: `${number(t.from_drawdowns)} facilities with their own rows` },
    { label: 'Without drawdown rows', value: without, sub: `${number(t.from_loan_book)} from the loan book, ${number(t.not_known)} not known`, valueClass: without ? 'text-amber-700' : '', accent: without ? '#d97706' : null },
  ]
})

const toggle = (contractId) => { open.value = open.value === contractId ? null : contractId }

const apply = () => router.get(route('eir-drawdowns.index'), { as_of: form.as_of, search: form.search },
  { preserveState: true, preserveScroll: true, replace: true })

const reset = () => {
  form.search = ''
  form.as_of = props.asOf
  apply()
}

const number = (v) => Number(v || 0).toLocaleString()
const money = (v) => Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>
