<template>
  <app-layout title="Auditor Pack" description="The auditor's pack for a period: the compliance workbooks, the EIR as at the period end, the baselines and the ECL by stage, zipped with a manifest that carries a SHA-256 for every file, so the auditor can prove what was handed over (spec v4 section 12.5)">
    <template #actions>
      <form v-if="canExport" @submit.prevent="build" class="flex items-center gap-2">
        <select v-model="form.period" class="maiic-select text-sm"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option><option v-if="form.period && !periods.includes(form.period)" :value="form.period">{{ form.period }}</option></select>
        <input v-if="!periods.length" v-model="form.period" type="month" class="maiic-input text-sm"/>
        <button type="submit" :disabled="building || !form.period" class="primary-btn text-sm">{{ building ? 'Building…' : 'Build the pack for ' + (form.period || '…') }}</button>
      </form>
    </template>

    <div class="space-y-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Packs on file</div><div class="maiic-kpi-value">{{ packs.length }}</div></div>
        <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Latest period packed</div><div class="maiic-kpi-value">{{ packs.length ? packs[0].period : '-' }}</div></div>
        <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Last build from this screen</div><div class="text-sm font-bold">{{ lastBuild ? (lastBuild.period + ' at ' + lastBuild.at) : 'none' }}</div></div>
      </div>

      <div v-if="lastBuild && lastBuild.result" class="maiic-panel p-5 text-sm">
        <h3 class="text-base font-bold">What the last build said</h3>
        <pre class="mt-2 whitespace-pre-wrap rounded bg-gray-50 p-3 font-mono text-xs dark:bg-slate-900/60">{{ lastBuild.result.output }}</pre>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The packs</h3><p class="text-sm text-gray-500">One zip per period, replaced whole when built again. The manifest inside names every file with its size and SHA-256; the counts here are read from it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th>File</th><th>Built</th><th class="num">Files in manifest</th><th class="num">With SHA-256</th><th class="num">Size</th><th>Contents</th><th v-if="canExport"></th></tr></thead>
          <tbody><tr v-for="p in packs" :key="p.file">
            <td class="font-bold">{{ p.period || '?' }}</td>
            <td class="font-mono text-xs">{{ p.file }}</td>
            <td class="text-xs">{{ p.built_at || p.modified_at }}<span v-if="!p.manifest_ok" class="maiic-badge maiic-badge-red ml-1">no manifest</span></td>
            <td class="num">{{ p.files ?? '-' }}</td>
            <td class="num">{{ p.with_sha256 ?? '-' }}</td>
            <td class="num">{{ size(p.bytes) }}</td>
            <td class="text-xs text-gray-500">{{ p.names.join(', ') }}</td>
            <td v-if="canExport" class="whitespace-nowrap"><a :href="route('auditor-pack.download', p.file)" class="secondary-btn text-xs">Download</a></td>
          </tr>
          <tr v-if="!packs.length"><td colspan="8" class="maiic-empty">No pack has been built: choose a period and build one.</td></tr></tbody></table></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
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
