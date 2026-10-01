<template>
  <app-layout>
    <template #header>
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div><div class="mb-1 text-xs text-gray-500"><Link :href="route('eir-data.index',{tab:'schedules'})" class="hover:text-maiic-700">Schedule Review</Link> / {{ contract.contract_id }}</div><h2 class="text-xl font-semibold text-gray-800">Loan Schedule {{ contract.contract_id }}<span v-if="contract.customer_name" class="font-normal text-gray-500"> · {{ contract.customer_name }}</span></h2><div class="mt-1 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-purple-100 px-2 py-0.5 font-semibold text-purple-800">Rate {{ pct(contract.contractual_rate) }}</span><span v-if="contract.spread_over_prime !== null && contract.spread_over_prime !== undefined" class="rounded-full px-2 py-0.5 font-semibold" :class="Number(contract.spread_drift_flag) ? 'bg-red-100 text-red-800' : 'bg-sky-100 text-sky-800'">Spread over prime {{ Number(contract.spread_over_prime).toFixed(2) }} pp ({{ String(contract.spread_source || '').toLowerCase() }}{{ Number(contract.spread_drift_flag) ? ', drifts: held for review' : '' }})</span></div><p class="mt-1 text-sm text-gray-600">Generated original schedule and E-Banker's own schedule (Extract B or the EMI chart) as evidence</p></div>
        <Link :href="route('eir-data.index',{tab:'schedules'})" class="secondary-btn">Back to review</Link>
      </div>
    </template>

    <div class="mx-auto max-w-7xl space-y-5">
      <div class="grid gap-3 md:grid-cols-4">
        <div class="card"><div class="metric">{{ label(contract.schedule_approval_status) }}</div><div class="caption">Approval status</div></div>
        <div class="card"><div class="metric">{{ label(comparison.status) }}</div><div class="caption">Comparison</div></div>
        <div class="card"><div class="metric">{{ money(comparison.cash_variance) }}</div><div class="caption">Cash variance before recalculation (MWK)</div></div>
        <div class="card"><div class="metric">{{ comparison.compared_rows ?? 0 }} / {{ comparison.recalculated_rows ?? 0 }}</div><div class="caption">Instalments compared / recalculated by E-Banker</div></div>
      </div>

      <section v-if="(comparison.rows || []).length" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b p-4">
          <h3 class="font-semibold text-gray-900">Instalment by instalment</h3>
          <p class="mt-1 text-xs text-gray-500">Version 1 is the promise at origination. E-Banker regenerates its schedule at every rate reset and when arrears are spread over the instalments left, so only the instalments before its first recalculation<span v-if="comparison.recalculated_from"> ({{ comparison.recalculated_from }})</span> are held to version 1. Each instalment is compared on its total cash, because E-Banker repays interest it added to the balance as principal.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead><tr>
              <th class="th">#</th><th class="th">Due date (generated / E-Banker)</th><th class="th text-right">Generated</th><th class="th text-right">E-Banker</th><th class="th text-right">Difference</th><th class="th">Compared?</th><th class="th">Why</th>
            </tr></thead>
            <tbody>
              <tr v-for="r in comparison.rows" :key="r.number" :class="r.segment==='COMPARED' ? '' : 'bg-gray-50'">
                <td class="td">{{ r.number }}</td>
                <td class="td whitespace-nowrap">{{ r.generated_due_date || '—' }}<span v-if="r.ebanker_due_date && r.ebanker_due_date!==r.generated_due_date" class="text-gray-500"> / {{ r.ebanker_due_date }}</span></td>
                <td class="td text-right">{{ money(r.generated_total) }}</td>
                <td class="td text-right">{{ money(r.ebanker_total) }}</td>
                <td class="td text-right" :class="r.segment==='COMPARED' && Math.abs(r.difference||0)>1 ? 'font-semibold text-red-700' : ''">{{ money(r.difference) }}</td>
                <td class="td"><span :class="segmentClass(r.segment)">{{ segmentLabel(r.segment) }}</span></td>
                <td class="td text-xs">{{ r.causes.join('; ') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <schedule-table title="Generated version-1 schedule" subtitle="Created from approved Extract A contract terms; this becomes usable by EIR only after approval." :rows="generated" :totals="generatedTotals" :start-balance="startBalance" />
      <schedule-table title="E-Banker schedule (Extract B or EMI chart)" :subtitle="`Forward-looking validation evidence from ${comparison.cutoff_date || 'an unavailable cutoff'}; it does not replace version 1. Balances marked printed are E-Banker's own.`" :rows="remaining" :totals="remainingTotals" />
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import ScheduleTable from './ScheduleTable.vue'


export default { components:{AppLayout,Link,ScheduleTable}, props:{contract:Object,generated:Array,remaining:Array,generatedTotals:Object,remainingTotals:Object,comparison:Object},
  computed:{
    // What the generated instalments start from: the sanctioned amount when
    // the instalment is sized on the sanction, otherwise the amount drawn.
    startBalance(){ const c=this.contract; const v=c.schedule_instalment_basis==='SANCTION'?c.approved_amount:c.drawn_amount; return v===null||v===undefined?null:Number(v) },
  },
  methods:{
    segmentLabel(s){ return {COMPARED:'Compared',RECALCULATED:'E-Banker recalculated',ONLY_GENERATED:'Generated only',ONLY_EBANKER:'E-Banker only'}[s] || s },
    segmentClass(s){ return 'rounded px-2 py-0.5 text-[11px] font-semibold '+(s==='COMPARED'?'bg-emerald-100 text-emerald-800':s==='RECALCULATED'?'bg-amber-100 text-amber-800':'bg-gray-200 text-gray-700') },
    pct(v){ if(v===null||v===undefined) return '—'; const n=Number(v); return (n<=1?n*100:n).toFixed(2)+'%' },
    money(v){return v===null||v===undefined?'—':Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}, label(v){return String(v||'Not available').replaceAll('_',' ')} } }
</script>

<style>
.card{@apply rounded-lg border border-gray-200 bg-white p-4 shadow-sm}.metric{@apply text-lg font-bold text-gray-900}.caption{@apply mt-1 text-xs font-medium uppercase tracking-wide text-gray-500}.secondary-btn{@apply inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50}.th{@apply whitespace-nowrap bg-maiic-700 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white}.td{@apply border-t border-gray-100 px-4 py-3 text-sm text-gray-700}
</style>
