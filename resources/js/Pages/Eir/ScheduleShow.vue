<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div><div class="mb-1 text-xs text-gray-500"><Link :href="route('eir-data.index',{tab:'schedules'})" class="hover:text-maiic-700">Schedule Review</Link> / {{ contract.contract_id }}</div><h2 class="text-xl font-semibold text-gray-800">Loan Schedule {{ contract.contract_id }}</h2><p class="mt-1 text-sm text-gray-600">Generated original schedule and Extract B remaining-schedule evidence</p></div>
        <Link :href="route('eir-data.index',{tab:'schedules'})" class="secondary-btn">Back to review</Link>
      </div>
    </template>

    <div class="mx-auto max-w-7xl space-y-5">
      <div class="grid gap-3 md:grid-cols-4">
        <div class="card"><div class="metric">{{ label(contract.schedule_approval_status) }}</div><div class="caption">Approval status</div></div>
        <div class="card"><div class="metric">{{ label(comparison.status) }}</div><div class="caption">Comparison</div></div>
        <div class="card"><div class="metric">{{ money(comparison.principal_variance) }}</div><div class="caption">Principal variance (MWK)</div></div>
        <div class="card"><div class="metric">{{ money(comparison.interest_variance) }}</div><div class="caption">Interest variance (MWK)</div></div>
      </div>

      <schedule-table title="Generated version-1 schedule" subtitle="Created from approved Extract A contract terms; this becomes usable by EIR only after approval." :rows="generated" :totals="generatedTotals" source-column />
      <schedule-table title="Extract B remaining schedule" :subtitle="`Forward-looking validation evidence from ${comparison.cutoff_date || 'an unavailable cutoff'}; it does not replace version 1.`" :rows="remaining" :totals="remainingTotals" source-column />
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const ScheduleTable = {
  props: ['title','subtitle','rows','totals'],
  methods: { money(v){ return v===null||v===undefined?'—':Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) }, date(v){return v?String(v).slice(0,10):'—'} },
  template: `<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"><div class="border-b p-4"><h3 class="font-semibold text-gray-900">{{ title }}</h3><p class="mt-1 text-xs text-gray-500">{{ subtitle }}</p></div><div class="overflow-x-auto"><table class="min-w-full"><thead><tr><th class="th">#</th><th class="th">Due date</th><th class="th">Principal (MWK)</th><th class="th">Interest (MWK)</th><th class="th">Fees (MWK)</th><th class="th">Total (MWK)</th><th class="th">Source</th></tr></thead><tbody><tr v-for="(r,i) in rows" :key="r.id"><td class="td">{{ i+1 }}</td><td class="td">{{ date(r.due_date) }}</td><td class="td">{{ money(r.principal_due) }}</td><td class="td">{{ money(r.interest_due) }}</td><td class="td">{{ money(r.fee_due) }}</td><td class="td font-semibold">{{ money(Number(r.principal_due)+Number(r.interest_due)+Number(r.fee_due)) }}</td><td class="td"><div>{{ r.schedule_source || r.source_system }}</div><div class="text-xs text-gray-500">{{ r.source_reference }}</div></td></tr><tr v-if="!rows.length"><td colspan="7" class="p-8 text-center text-sm text-gray-500">No schedule rows available.</td></tr></tbody><tfoot v-if="rows.length"><tr class="bg-gray-100 font-semibold"><td class="td" colspan="2">Total · {{ totals.rows }} rows</td><td class="td">{{ money(totals.principal) }}</td><td class="td">{{ money(totals.interest) }}</td><td class="td">{{ money(totals.fees) }}</td><td class="td">{{ money(totals.total) }}</td><td class="td"></td></tr></tfoot></table></div></section>`
}

export default { components:{AppLayout,Link,ScheduleTable}, props:{contract:Object,generated:Array,remaining:Array,generatedTotals:Object,remainingTotals:Object,comparison:Object}, methods:{ money(v){return v===null||v===undefined?'—':Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}, label(v){return String(v||'Not available').replaceAll('_',' ')} } }
</script>

<style>
.card{@apply rounded-lg border border-gray-200 bg-white p-4 shadow-sm}.metric{@apply text-lg font-bold text-gray-900}.caption{@apply mt-1 text-xs font-medium uppercase tracking-wide text-gray-500}.secondary-btn{@apply inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50}.th{@apply whitespace-nowrap bg-maiic-700 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white}.td{@apply border-t border-gray-100 px-4 py-3 text-sm text-gray-700}
</style>
