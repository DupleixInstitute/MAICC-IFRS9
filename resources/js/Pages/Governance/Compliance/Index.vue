<template>
  <app-layout title="Compliance Audits" description="One workbook per standard or directive: every section on its own row, what the system does about it, where to see it, the setting that governs it, the test that proves it, and a place for the reviewer to sign (spec v4 section 12)">
    <template #actions><button v-if="canGovern" @click="reload" class="secondary-btn text-sm">Reload from the workbooks</button></template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <div v-for="a in audits" :key="a.id" class="maiic-panel p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-base font-bold"><Link :href="route('compliance-audits.show', a.id)" class="hover:underline">{{ a.short }}</Link></h3>
            <p class="text-xs text-gray-500">{{ a.title }}</p>
            <p class="mt-1 text-xs text-gray-500">Reviewer: {{ a.reviewer }} · loaded {{ a.loaded_at }} · {{ a.rows }} rows · {{ a.findings }} open findings</p>
          </div>
          <div class="flex gap-1"><a v-for="f in a.files" :key="f" :href="route('compliance-audits.download', [a.id, f])" class="secondary-btn text-xs uppercase">{{ f }}</a></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2"><span v-for="(n, s) in a.counts" :key="s" class="maiic-badge" :class="badge(s)">{{ s }}: {{ n }}</span></div>
      </div>
      <div v-if="!audits.length" class="maiic-panel p-6 text-sm text-gray-500">No workbook loaded: build them with tools/compliance/build_audit.py, then reload.</div>
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
    badge(s) { return s === 'Done' ? 'maiic-badge-green' : (s === 'Outstanding' ? 'maiic-badge-red' : (s === 'Partially done' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    reload() { router.post(route('compliance-audits.reload'), {}, { preserveScroll: true }) },
  },
}
</script>
