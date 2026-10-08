<template>
  <app-layout title="Audit Trace" description="Open any contract and see, oldest to newest, every change to its schedule, every reset and modification, every engine run that touched it, and the governance values each of its months was run under (spec v4 section 12.5)">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2">
        <input v-model="form.contract" list="contracts" class="maiic-input" placeholder="contract id"/>
        <datalist id="contracts"><option v-for="s in suggestions" :key="s.id" :value="s.id">{{ s.name }}</option></datalist>
        <button type="submit" class="primary-btn text-sm">Trace</button>
      </form>
    </template>
    <div class="space-y-6">
      <div v-if="facts" class="maiic-panel p-5 text-sm">
        <h3 class="text-base font-bold">{{ facts.contract_id }} <span class="font-normal text-gray-500">{{ facts.customer_name }}</span></h3>
        <p class="text-xs text-gray-500">{{ facts.product_type }} · GL {{ facts.gl }} · originated {{ facts.origination_date }} · EIR {{ facts.eir != null ? (Number(facts.eir) * 100).toFixed(4) + '%' : '-' }} · {{ facts.calculation_status }} · locked {{ facts.locked_at || '-' }} · schedule {{ facts.schedule_status }}</p>
      </div>
      <div v-if="months.length" class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">The loan's months and the values they ran under</h3></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th>Build</th><th>Stage</th><th class="num">DPD</th><th class="num">Carrying</th><th class="num">PD pre → post</th><th class="num">LGD</th><th class="num">ECL</th><th>FLI</th><th>Governance values ({{ months[0].governance_source }})</th></tr></thead>
          <tbody><tr v-for="m in months" :key="m.period" class="text-xs align-top"><td>{{ m.period }}<span v-if="m.locked" class="maiic-badge maiic-badge-grey ml-1">locked</span></td><td>{{ m.build }}</td><td>{{ m.stage }}</td><td class="num">{{ m.dpd }}</td><td class="num">{{ fmt(m.carrying) }}</td><td class="num">{{ pct(m.pd_pre) }} → {{ pct(m.pd_post) }}</td><td class="num">{{ pct(m.lgd) }}</td><td class="num">{{ fmt(m.ecl) }}</td><td>{{ m.fli }}</td>
            <td><div v-for="(v, k) in m.governance" :key="k"><span class="font-mono text-gray-500">{{ k }}</span>: {{ v }}</div></td></tr></tbody></table></div>
      </div>
      <div v-if="events.length" class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">Every event, oldest to newest ({{ events.length }})</h3></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>When</th><th>What</th><th>Detail</th><th>Source</th><th>Who</th></tr></thead>
          <tbody><tr v-for="(e, i) in events" :key="i" class="text-xs align-top"><td class="whitespace-nowrap">{{ e.at }}</td><td class="font-semibold">{{ e.what }}</td><td class="break-all">{{ e.detail }}</td><td class="font-mono">{{ e.source }}</td><td>{{ e.who || '' }}</td></tr></tbody></table></div>
      </div>
      <div v-if="contract && !events.length && !months.length" class="maiic-panel p-6 text-sm text-gray-500">Nothing recorded for {{ contract }}.</div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { contract: String, facts: Object, events: Array, months: Array, suggestions: Array },
  data() { return { form: { contract: this.contract } } },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    pct(v) { return v == null ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    go() { router.get(route('audit-trace.index'), { contract: this.form.contract }) },
  },
}
</script>
