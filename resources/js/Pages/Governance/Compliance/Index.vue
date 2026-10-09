<template>
  <app-layout title="Compliance Audits" description="One workbook per standard or directive, each section checked against what the system does, with reviewer sign-off">
    <template #actions>
      <button v-if="canGovern" type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50" @click="reload">
        <font-awesome-icon icon="history"/> Reload from the workbooks
      </button>
    </template>

    <div class="space-y-5">
      <div v-if="audits.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="maiic-kpi" style="--accent:#15803d"><div class="maiic-kpi-label">Workbooks</div><div class="maiic-kpi-value text-xl">{{ audits.length }}</div></div>
        <div class="maiic-kpi" style="--accent:#0e7490"><div class="maiic-kpi-label">Sections checked</div><div class="maiic-kpi-value text-xl">{{ total('rows') }}</div></div>
        <div class="maiic-kpi" style="--accent:#15803d"><div class="maiic-kpi-label">Done</div><div class="maiic-kpi-value text-xl">{{ status('Done') }}</div></div>
        <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Outstanding</div><div class="maiic-kpi-value text-xl">{{ status('Outstanding') }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">Open findings</div><div class="maiic-kpi-value text-xl">{{ total('findings') }}</div></div>
      </div>

      <div class="maiic-panel">
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead>
              <tr><th>Workbook</th><th>Reviewer</th><th>Loaded</th><th class="num">Sections</th><th>Status</th><th class="num">Open findings</th><th>Downloads</th><th class="num">Open</th></tr>
            </thead>
            <tbody>
              <tr v-for="a in audits" :key="a.id" class="align-top">
                <td>
                  <Link :href="route('compliance-audits.show', a.id)" class="font-semibold text-maiic-800 hover:underline">{{ a.short }}</Link>
                  <div class="text-xs text-gray-500">{{ a.title }}</div>
                </td>
                <td class="whitespace-nowrap">{{ a.reviewer || '-' }}</td>
                <td class="whitespace-nowrap text-xs text-gray-600">{{ a.loaded_at || '-' }}</td>
                <td class="num">{{ a.rows }}</td>
                <td><div class="flex flex-wrap gap-1"><span v-for="(n, s) in a.counts" :key="s" class="maiic-badge" :class="badge(s)">{{ s }} {{ n }}</span></div></td>
                <td class="num"><span class="maiic-badge" :class="a.findings ? 'maiic-badge-gold' : 'maiic-badge-grey'">{{ a.findings }}</span></td>
                <td>
                  <div class="flex gap-1">
                    <a v-for="f in a.files" :key="f" :href="route('compliance-audits.download', [a.id, f])" class="maiic-action maiic-action-neutral text-[10px] font-bold uppercase" :title="'Download the ' + f.toUpperCase() + ' file'">{{ f }}</a>
                    <span v-if="!a.files.length" class="text-xs text-gray-400">None</span>
                  </div>
                </td>
                <td class="num">
                  <Link :href="route('compliance-audits.show', a.id)" class="maiic-action maiic-action-view" title="Open the workbook"><font-awesome-icon icon="eye"/></Link>
                </td>
              </tr>
              <tr v-if="!audits.length">
                <td colspan="8" class="px-6 py-10 text-center">
                  <div class="text-base font-bold text-gray-800">No compliance workbooks loaded yet</div>
                  <p class="mx-auto mt-1 max-w-lg text-sm text-gray-500">Each workbook lists a standard's sections, what the system does about each one, where to see it and who signed it off. Ask the system administrator to build the workbooks, then press Reload from the workbooks.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: { audits: Array, statuses: Array, canGovern: Boolean },
  methods: {
    total(key) { return this.audits.reduce((t, a) => t + Number(a[key] || 0), 0) },
    status(s) { return this.audits.reduce((t, a) => t + Number((a.counts || {})[s] || 0), 0) },
    badge(s) { return s === 'Done' ? 'maiic-badge-green' : (s === 'Outstanding' ? 'maiic-badge-red' : (s === 'Partially done' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    reload() { router.post(route('compliance-audits.reload'), {}, { preserveScroll: true }) },
  },
}
</script>
