<template>
  <app-layout title="Auditor pack">
    <template #header>
      <BackToReports tab="exports"/>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Auditor pack</h2>
      <p class="mt-0.5 text-sm text-gray-500">One zip per period: the compliance workbooks, the EIR book, the baselines and the ECL by stage, with a SHA-256 for every file.</p>
    </template>
    <template #actions>
      <form v-if="canExport" @submit.prevent="build" class="flex items-center gap-2">
        <select v-model="form.period" class="maiic-select w-36" aria-label="Period"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option><option v-if="form.period && !periods.includes(form.period)" :value="form.period">{{ form.period }}</option></select>
        <input v-if="!periods.length" v-model="form.period" type="month" class="maiic-input w-40"/>
        <button type="submit" :disabled="building || !form.period" class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-50">{{ building ? 'Building...' : 'Build the pack for ' + (form.period || '...') }}</button>
      </form>
    </template>

    <div class="space-y-5">
      <KpiRow :items="[
        { label: 'Packs on file', value: packs.length },
        { label: 'Latest period packed', value: packs.length ? packs[0].period : '-' },
        { label: 'Last build from this screen', value: lastBuild ? (lastBuild.period + ' at ' + lastBuild.at) : 'None', tone: 'amber' },
      ]"/>

      <details v-if="lastBuild && lastBuild.result" class="maiic-panel px-4 py-3 text-sm">
        <summary class="cursor-pointer select-none font-semibold text-maiic-800">What the last build said</summary>
        <pre class="mt-2 whitespace-pre-wrap rounded bg-gray-50 p-3 font-mono text-xs">{{ lastBuild.result.output }}</pre>
      </details>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-4 py-3"><h3 class="font-semibold text-gray-900">The packs</h3><p class="text-xs text-gray-500">One zip per period, replaced whole when built again. The counts are read from the manifest inside each zip.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th>File</th><th>Built</th><th class="num">Files in manifest</th><th class="num">With SHA-256</th><th class="num">Size</th><th>Contents</th><th v-if="canExport"></th></tr></thead>
          <tbody><tr v-for="p in packs" :key="p.file">
            <td class="font-bold">{{ p.period || '?' }}</td>
            <td class="font-mono text-xs">{{ p.file }}</td>
            <td class="text-xs">{{ p.built_at || p.modified_at }}<span v-if="!p.manifest_ok" class="maiic-badge maiic-badge-red ml-1">no manifest</span></td>
            <td class="num">{{ p.files ?? '-' }}</td>
            <td class="num">{{ p.with_sha256 ?? '-' }}</td>
            <td class="num">{{ size(p.bytes) }}</td>
            <td class="text-xs text-gray-500">{{ p.names.join(', ') }}</td>
            <td v-if="canExport" class="whitespace-nowrap"><a :href="route('auditor-pack.download', p.file)" class="maiic-action maiic-action-view" :title="'Download ' + p.file"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg></a></td>
          </tr>
          <tr v-if="!packs.length"><td colspan="8" class="maiic-empty">No pack has been built: choose a period and build one.</td></tr></tbody></table></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import BackToReports from './Partials/BackToReports.vue'
import KpiRow from './Partials/KpiRow.vue'

export default {
  components: { AppLayout, BackToReports, KpiRow },
  props: { packs: Array, periods: Array, lastBuild: Object, canExport: Boolean },
  data() { return { form: { period: this.periods && this.periods.length ? this.periods[0] : '' }, building: false } },
  methods: {
    size(b) { return b == null ? '' : (b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : (b / 1024).toFixed(0) + ' KB') },
    build() {
      if (!this.form.period) return
      this.building = true
      router.post(route('auditor-pack.build'), { period: this.form.period }, { preserveScroll: true, onFinish: () => { this.building = false } })
    },
  },
}
</script>
