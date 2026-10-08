<template>
  <app-layout title="RBM Return (provisional)" description="The classification and provisioning return of the Reserve Bank's DFI directive (section 17), filled from the system in the directive's own order; re-laid out line for line when MAIIC Risk's prescribed form arrives">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2">
        <select v-model="form.period" class="maiic-select"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
        <label class="flex items-center gap-1 text-xs"><input v-model="form.mega_farm" type="checkbox"/> include Mega Farm</label>
        <button type="button" @click="go" class="secondary-btn text-sm">View</button>
        <a v-if="ret" :href="route('rbm-return.export', { period, mega_farm: megaFarm ? 1 : 0 })" class="primary-btn text-sm">Download CSV</a>
      </form>
    </template>
    <div class="space-y-6">
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
        <p v-for="(n, i) in (ret ? ret.notes : [])" :key="i" :class="i ? 'mt-1' : 'font-semibold'">{{ n }}</p>
      </div>
      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
      <template v-if="ret">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="maiic-kpi" style="--accent:#4f46e5"><div class="maiic-kpi-label">Facilities</div><div class="maiic-kpi-value">{{ ret.loans }}</div></div>
          <div class="maiic-kpi" style="--accent:#4f46e5"><div class="maiic-kpi-label">Total balance</div><div class="maiic-kpi-value">{{ fmt(ret.total_balance) }}</div></div>
          <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">NPL ratio (substandard and below)</div><div class="maiic-kpi-value">{{ ret.npl_ratio != null ? (ret.npl_ratio * 100).toFixed(2) + '%' : '-' }}</div></div>
          <div class="maiic-kpi" style="--accent:#4f46e5"><div class="maiic-kpi-label">Provision: the higher of the two</div><div class="maiic-kpi-value">{{ fmt(ret.sections.B_totals.higher_of_the_two) }}</div><div class="text-xs text-gray-500">minimum {{ fmt(ret.sections.B_totals.minimum_provision) }} · IFRS 9 {{ fmt(ret.sections.B_totals.ifrs9_allowance) }}</div></div>
        </div>
        <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">A. Classification of facilities (sections 9 to 11)</h3><p class="text-xs text-gray-500">Short-term: 12 months or less (pass to 30 days; special mention to 90; substandard to 180; doubtful to 360; loss beyond). Medium and long term: to 30; 180; 360; 720; beyond.</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Class</th><th class="num">Short-term accounts</th><th class="num">Short-term balance</th><th class="num">Medium/long accounts</th><th class="num">Medium/long balance</th><th class="num">Total accounts</th><th class="num">Total balance</th><th class="num">IFRS 9 allowance</th></tr></thead>
            <tbody><tr v-for="a in ret.sections.A_classification" :key="a.class"><td>{{ a.class }}</td><td class="num">{{ a.short_term.accounts }}</td><td class="num">{{ fmt(a.short_term.balance) }}</td><td class="num">{{ a.medium_long_term.accounts }}</td><td class="num">{{ fmt(a.medium_long_term.balance) }}</td><td class="num">{{ a.total.accounts }}</td><td class="num">{{ fmt(a.total.balance) }}</td><td class="num">{{ fmt(a.total.ifrs9_allowance) }}</td></tr></tbody>
            <tfoot><tr><th>Total</th><th></th><th></th><th></th><th></th><th class="num">{{ ret.loans }}</th><th class="num">{{ fmt(ret.total_balance) }}</th><th class="num">{{ fmt(ret.sections.B_totals.ifrs9_allowance) }}</th></tr></tfoot></table></div></div>
        <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">B. Provisions (section 12)</h3></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Class</th><th class="num">Balance</th><th class="num">Minimum rate</th><th class="num">Minimum provision</th><th class="num">IFRS 9 allowance</th><th class="num">Shortfall</th></tr></thead>
            <tbody><tr v-for="b in ret.sections.B_provisions" :key="b.class"><td>{{ b.class }}</td><td class="num">{{ fmt(b.balance) }}</td><td class="num">{{ (b.minimum_rate * 100).toFixed(0) }}%</td><td class="num">{{ fmt(b.minimum_provision) }}</td><td class="num">{{ fmt(b.ifrs9_allowance) }}</td><td class="num" :class="b.shortfall > 0 ? 'text-red-700' : ''">{{ fmt(b.shortfall) }}</td></tr></tbody>
            <tfoot><tr><th>Totals</th><th></th><th></th><th class="num">{{ fmt(ret.sections.B_totals.minimum_provision) }}</th><th class="num">{{ fmt(ret.sections.B_totals.ifrs9_allowance) }}</th><th class="num">higher of the two: {{ fmt(ret.sections.B_totals.higher_of_the_two) }}</th></tr></tfoot></table></div></div>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">C. Interest in suspense (section 13)</h3>
            <div v-for="(v, k) in ret.sections.C_interest_in_suspense" :key="k" class="flex justify-between border-b border-gray-100 py-1 dark:border-slate-700"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v }}</span></div></div>
          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">D. Restructured facilities (section 15)</h3><p class="text-gray-500">{{ ret.sections.D_restructured.note }}</p></div>
          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">E. Security against classified facilities</h3>
            <div v-for="(v, k) in ret.sections.E_security" :key="k" class="flex justify-between border-b border-gray-100 py-1 dark:border-slate-700" v-if="k !== 'note'"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v }}</span></div>
            <p class="mt-2 text-xs text-gray-500">{{ ret.sections.E_security.note }}</p></div>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { period: String, return: Object, error: String, megaFarm: Boolean, periods: Array },
  data() { return { form: { period: this.period, mega_farm: this.megaFarm } } },
  computed: { ret() { return this.return } },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    go() { router.get(route('rbm-return.index'), { period: this.form.period, mega_farm: this.form.mega_farm ? 1 : 0 }) },
  },
}
</script>
