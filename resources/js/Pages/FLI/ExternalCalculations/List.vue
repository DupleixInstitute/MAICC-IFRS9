<template>
  <app-layout title="Calculation History" description="Every month's forward-looking record, what it ran under and who signed it off, and every approval behind it">
    <div class="space-y-4">
      <KpiRow :cards="kpis" />

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Month by month</h3><p class="text-xs text-gray-500">A month recorded again keeps its earlier record, marked superseded.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Month</th><th>Model</th><th>Scenario set</th><th class="num">Loans</th><th class="num">ECL before FLI</th><th class="num">FLI effect</th><th class="num">ECL booked</th><th class="num">Weighted ties</th><th class="num">Movement</th><th>Status</th><th>Signed off</th></tr></thead>
          <tbody>
            <tr v-for="s in pagedSnaps" :key="s.id" :class="s.status === 'SUPERSEDED' ? 'text-gray-400' : ''">
              <td class="whitespace-nowrap font-semibold"><Link :href="route('fli.impact', { period: s.reporting_period })" class="text-maiic-700 underline dark:text-maiic-300">{{ monthLabel(s.reporting_period) }}</Link></td>
              <td class="text-xs">{{ s.model_version ? 'v' + s.model_version + ': ' + s.statistic_code + ' to ' + s.proxy_code + ', lag ' + s.lag_months : '-' }}</td>
              <td class="text-xs">{{ s.set_name ? s.set_name + ', v' + s.set_version : '-' }}</td>
              <td class="num">{{ s.loans }}</td>
              <td class="num">{{ short(s.pre_fli_ecl) }}</td>
              <td class="num">{{ short(Number(s.model_effect) + Number(s.overlay_effect)) }}</td>
              <td class="num font-semibold">{{ short(s.booked_ecl) }}</td>
              <td class="num"><span class="maiic-badge" :class="s.reconciles ? 'maiic-badge-green' : 'maiic-badge-red'">{{ s.reconciles ? 'Yes' : 'No' }}</span></td>
              <td class="num" :class="s.needs_commentary ? 'font-semibold text-red-700' : ''" :title="s.commentary || ''">{{ s.movement_pct === null ? '-' : (Number(s.movement_pct) * 100).toFixed(1) + '%' }}</td>
              <td><span class="maiic-badge" :class="badge(s.status)">{{ statusLabel(s.status) }}</span></td>
              <td class="text-xs">{{ s.signer || s.approver_label || '-' }}<div v-if="s.signed_off_at" class="text-gray-400">{{ String(s.signed_off_at).slice(0, 10) }}</div></td>
            </tr>
            <tr v-if="!snapshots.length"><td colspan="11" class="maiic-empty">No month has been recorded yet. Record one on the Overview.</td></tr>
          </tbody></table></div>
        <RowPager v-model="page" :total="snapshots.length" class="border-t border-gray-100" />
      </div>

      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div><h3 class="font-semibold text-gray-900">Approvals and runs</h3><p class="text-xs text-gray-500">{{ filtered.length }} of the latest {{ events.length }} entries in the audit log.</p></div>
          <select v-model="action" class="maiic-input !w-64" aria-label="Filter by action"><option value="">Every action</option><option v-for="a in actions" :key="a" :value="a">{{ a }}</option></select>
        </div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>When</th><th>What</th><th>Month</th><th>Record</th><th>By</th></tr></thead>
          <tbody>
            <tr v-for="e in pagedEvents" :key="e.id"><td class="whitespace-nowrap">{{ String(e.at).slice(0, 16) }}</td><td>{{ e.action }}</td><td>{{ e.period ? monthLabel(e.period) : '-' }}</td><td class="text-xs">{{ e.entity }}{{ e.entity_id ? ' ' + e.entity_id : '' }}</td><td class="text-xs">{{ e.by || e.label || '-' }}</td></tr>
            <tr v-if="!filtered.length"><td colspan="5" class="maiic-empty">Nothing logged.</td></tr>
          </tbody></table></div>
        <RowPager v-model="eventPage" :total="filtered.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Link } from '@inertiajs/vue3'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, KpiRow, RowPager, Link },
  props: { snapshots: Array, events: Array, actions: Array },
  data() { return { page: 1, eventPage: 1, action: '' } },
  computed: {
    current() { return this.snapshots.filter(s => s.status !== 'SUPERSEDED') },
    pagedSnaps() { return this.snapshots.slice((this.page - 1) * 15, this.page * 15) },
    filtered() { return this.action ? this.events.filter(e => e.action === this.action) : this.events },
    pagedEvents() { return this.filtered.slice((this.eventPage - 1) * 15, this.eventPage * 15) },
    kpis() {
      const c = this.current
      return [
        { label: 'Months recorded', value: c.length, accent: '#0284c7' },
        { label: 'Signed off', value: c.filter(s => s.status === 'SIGNED_OFF').length, accent: '#15803d' },
        { label: 'Waiting for sign-off', value: c.filter(s => s.status === 'RECORDED').length, accent: '#d97706' },
        { label: 'Not reconciling', value: c.filter(s => !s.reconciles).length, accent: '#dc2626' },
      ]
    },
  },
  watch: { action() { this.eventPage = 1 } },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    badge(s) { return { SIGNED_OFF: 'maiic-badge-green', RECORDED: 'maiic-badge-gold', SUPERSEDED: 'maiic-badge-grey' }[s] || 'maiic-badge-grey' },
    statusLabel(s) { return { SIGNED_OFF: 'Signed off', RECORDED: 'Recorded', SUPERSEDED: 'Superseded' }[s] || s },
    short(v) { if (v === null || v === undefined || isNaN(v)) return '-'; const n = Number(v); const a = Math.abs(n); const s = a >= 1e9 ? (a / 1e9).toFixed(2) + 'bn' : a >= 1e6 ? (a / 1e6).toFixed(1) + 'm' : Math.round(a).toLocaleString(); return (n < 0 ? '(' : '') + s + (n < 0 ? ')' : '') },
  },
}
</script>
