<template>
  <app-layout title="Credit Loss Data" description="The credit-loss history the forward-looking model is calibrated on: the measures derived from MAIIC's own staged loan books, and external series such as the industry NPL ratio">
    <template #actions>
      <Link :href="route('credit-loss-data.definition')" class="secondary-btn text-sm"><font-awesome-icon icon="plus" class="mr-1" />Measure</Link>
      <Link :href="route('credit-loss-data.importView')" class="secondary-btn text-sm"><font-awesome-icon icon="upload" class="mr-1" />Import CSV</Link>
      <Link :href="route('credit-loss-data.create')" class="primary-btn text-sm"><font-awesome-icon icon="plus" class="mr-1" />Add record</Link>
    </template>

    <div class="w-full space-y-4">
      <KpiRow :cards="[
        { label: 'Months derived from the books', value: derived.length, accent: '#15803d', sub: derived.length ? 'latest ' + derived[0].period : 'run the correlation sweep' },
        { label: 'External series', value: externalSeries.length, accent: '#0284c7', sub: externalSeries.length ? '' : 'none entered yet' },
        { label: 'Records entered here', value: Number(totalRecords || 0), accent: '#d97706' },
        { label: 'Measures defined', value: definitions.length, accent: '#7c3aed' },
      ]" />

      <!-- derived from the books -->
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div><h3 class="font-semibold text-gray-900">Derived from MAIIC's staged loan books</h3><p class="text-xs text-gray-500">Refreshed by the correlation sweep each month-end, never typed in. Book level.</p></div>
          <Link :href="route('fli.overview')" class="secondary-btn !px-3 !py-1 text-xs"><font-awesome-icon icon="sync-alt" class="mr-1" />Refresh from the Overview</Link>
        </div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Month</th><th class="num">NPL ratio (Stage 3 carrying amount)</th><th class="num">Stage 3 share of accounts</th><th class="num">12-month default rate</th></tr></thead>
          <tbody>
            <tr v-for="d in pagedDerived" :key="d.period"><td class="font-semibold">{{ d.period }}</td><td class="num">{{ pct(d.NPL_RATIO) }}</td><td class="num">{{ pct(d.STAGE3_SHARE) }}</td><td class="num">{{ pct(d.DEFAULT_RATE_12M) }}</td></tr>
            <tr v-if="!derived.length"><td colspan="4" class="maiic-empty">Nothing derived yet: run the correlation sweep (Overview, step 3).</td></tr>
          </tbody></table></div>
        <RowPager v-model="derivedPage" :total="derived.length" class="border-t border-gray-100" />
      </div>

      <!-- external series as the model reads them -->
      <div v-if="externalSeries.length" class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">External series the model can use</h3><p class="text-xs text-gray-500">Built from the records below at each correlation sweep; a series with a release date is not read for any month before it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Code</th><th>Series</th><th class="num">Months</th><th>From</th><th>To</th><th>Latest release</th></tr></thead>
          <tbody><tr v-for="e in pagedExternal" :key="e.proxy_code"><td class="font-mono text-xs">{{ e.proxy_code }}</td><td>{{ e.name }}</td><td class="num">{{ e.n }}</td><td>{{ ym(e.first) }}</td><td>{{ ym(e.last) }}</td><td>{{ e.known ? ym(e.known) : 'end of each month' }}</td></tr></tbody>
        </table></div>
        <RowPager v-model="externalPage" :total="externalSeries.length" class="border-t border-gray-100" />
      </div>

      <!-- every record entered; Measure, Import CSV and Add record sit above it -->
      <div data-actions-anchor></div>
      <div class="maiic-panel">
        <div class="flex flex-wrap items-end gap-2 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <h3 class="mr-auto self-center font-semibold text-gray-900">Records entered or imported</h3>
          <label class="block"><span class="maiic-flabel">Month</span><input v-model="form.period" type="month" class="maiic-input !w-40" /></label>
          <label class="block"><span class="maiic-flabel">Portfolio</span>
            <select v-model="form.portfolio_id" class="maiic-select !w-56"><option value="">All</option><option value="external">External or industry</option><option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option></select>
          </label>
          <label class="block"><span class="maiic-flabel">Measure</span>
            <select v-model="form.definition_id" class="maiic-select !w-48"><option value="">All</option><option v-for="d in definitions" :key="d.id" :value="d.id">{{ d.name }}</option></select>
          </label>
          <button type="button" class="primary-btn text-sm" @click="filter">Filter</button>
          <button type="button" class="secondary-btn text-sm" @click="reset">Reset</button>
        </div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Month</th><th>Measure</th><th>Portfolio</th><th class="num">Value</th><th>Published</th><th>Source</th><th>Entered by</th><th></th></tr></thead>
          <tbody>
            <tr v-for="r in rows.data" :key="r.id">
              <td class="whitespace-nowrap">{{ String(r.period).slice(0, 7) }}</td>
              <td>{{ r.definition ? r.definition.name : '-' }} <span class="text-xs text-gray-400">{{ r.definition ? r.definition.code : '' }}</span></td>
              <td>{{ r.portfolio ? r.portfolio.name : 'External or industry' }}</td>
              <td class="num">{{ r.value === null ? '-' : Number(r.value).toLocaleString() }}</td>
              <td class="whitespace-nowrap">{{ r.published_at ? String(r.published_at).slice(0, 10) : '-' }}</td>
              <td class="text-xs">{{ r.source || '-' }}</td>
              <td class="text-xs">{{ r.creator ? r.creator.name : '-' }}</td>
              <td class="num whitespace-nowrap">
                <Link :href="route('credit-loss-data.edit', r.id)" class="maiic-action maiic-action-edit" title="Edit"><font-awesome-icon icon="pen" /></Link>
                <button type="button" class="maiic-action maiic-action-delete ml-1" title="Delete" @click="destroy(r)"><font-awesome-icon icon="trash" /></button>
              </td>
            </tr>
            <tr v-if="!rows.data.length"><td colspan="8" class="maiic-empty">No record. Add one, or import a CSV of an external series (a period column, a value column named after the measure, and optionally published_at).</td></tr>
          </tbody></table></div>
        <div class="px-4 pb-3"><Pagination :links="rows.links" /></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
  components: { AppLayout, KpiRow, RowPager, Pagination, Link },
  props: { rows: Object, derived: Array, externalSeries: Array, totalRecords: Number, portfolios: Array, definitions: Array, filters: Object, canGovern: Boolean },
  data() { return { derivedPage: 1, externalPage: 1, form: { period: this.filters?.period || '', portfolio_id: this.filters?.portfolio_id || '', definition_id: this.filters?.definition_id || '' } } },
  computed: {
    pagedDerived() { return this.derived.slice((this.derivedPage - 1) * 15, this.derivedPage * 15) },
    pagedExternal() { return this.externalSeries.slice((this.externalPage - 1) * 15, this.externalPage * 15) },
  },
  methods: {
    pct(v) { return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    ym(v) { return v ? String(v).slice(0, 4) + '-' + String(v).slice(4, 6) : '-' },
    filter() { router.get(route('credit-loss-data.index'), Object.fromEntries(Object.entries(this.form).filter(([, v]) => v !== '')), { preserveScroll: true }) },
    reset() { this.form = { period: '', portfolio_id: '', definition_id: '' }; router.get(route('credit-loss-data.index')) },
    async destroy(r) {
      if (await confirmDialog({ title: 'Delete this record?', message: `${r.definition ? r.definition.name : 'Record'} for ${String(r.period).slice(0, 7)}. The model stops reading it at the next correlation sweep.`, confirmLabel: 'Delete', tone: 'danger' })) router.delete(route('credit-loss-data.destroy', r.id), { preserveScroll: true })
    },
  },
}
</script>
