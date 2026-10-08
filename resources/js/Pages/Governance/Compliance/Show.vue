<template>
  <app-layout :title="audit.short" :description="audit.title">
    <template #actions><a v-for="f in ['xlsx', 'pdf', 'md']" :key="f" :href="route('compliance-audits.download', [audit.id, f])" class="secondary-btn text-xs uppercase">{{ f }}</a></template>
    <div class="space-y-6">
      <p class="text-sm text-gray-600 dark:text-slate-300">{{ audit.basis }}</p>
      <div class="flex flex-wrap gap-2"><span v-for="(n, s) in counts" :key="s" class="maiic-badge" :class="badge(s)">{{ s }}: {{ n }}</span></div>
      <div class="maiic-panel">
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Ref</th><th>Section</th><th>What it requires</th><th>Status</th><th>What the engine does</th><th>Where to see it</th><th>Setting</th><th>Test</th><th>Sign-off</th><th v-if="canGovern"></th></tr></thead>
            <tbody>
              <template v-for="(r, i) in rows" :key="r.id">
                <tr v-if="i === 0 || rows[i - 1].part !== r.part" class="total"><td colspan="10">{{ r.part }}</td></tr>
                <tr class="align-top text-xs">
                  <td class="font-mono">{{ r.reference }}</td>
                  <td class="font-semibold">{{ r.section_name }}</td>
                  <td>{{ r.requirement }}</td>
                  <td><span class="maiic-badge" :class="badge(r.status)">{{ r.status }}</span><div v-if="r.proposed_status" class="mt-1 text-amber-700">proposed: {{ r.proposed_status }}</div></td>
                  <td>{{ r.engine_comment }}<div v-if="r.compliance_comment" class="mt-1 italic text-gray-500">{{ r.compliance_comment }}</div></td>
                  <td><div v-for="(l, k) in r.links" :key="k"><Link :href="l.href" class="text-sky-700 hover:underline dark:text-sky-300">{{ l.label }}</Link></div><span v-if="!r.links.length">{{ r.where_to_see }}</span></td>
                  <td class="font-mono">{{ r.governance_setting }}</td>
                  <td class="font-mono">{{ r.test }}</td>
                  <td><span v-if="r.signer">{{ r.signer }} {{ (r.signed_at || '').slice(0, 10) }}</span><span v-if="r.approver"><br>approved {{ r.approver }} {{ (r.approved_at || '').slice(0, 10) }}</span></td>
                  <td v-if="canGovern" class="whitespace-nowrap">
                    <select v-model="edit[r.id]" class="maiic-select text-xs"><option v-for="s in statuses" :key="s" :value="s">{{ s }}</option></select>
                    <button @click="sign(r)" class="secondary-btn mt-1 text-xs">Sign</button>
                    <button v-if="r.proposed_status" @click="approve(r)" class="primary-btn mt-1 text-xs">Approve</button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="maiic-panel" v-if="findings.length">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">Findings</h3></div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>No.</th><th>Ref</th><th>Finding</th><th>What was found</th><th>Impact</th><th>Recommended action</th><th>Owner</th><th>Status</th></tr></thead>
            <tbody><tr v-for="f in findings" :key="f.id" class="align-top text-xs"><td>{{ f.number }}</td><td>{{ f.reference }}</td><td class="font-semibold">{{ f.finding }}</td><td>{{ f.what_was_found }}</td><td>{{ f.impact }}</td><td>{{ f.recommended_action }}</td><td>{{ f.owner }}</td><td>{{ f.status }}</td></tr></tbody>
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
  props: { audit: Object, rows: Array, counts: Object, statuses: Array, findings: Array, canGovern: Boolean },
  data() {
    const edit = {}
    this.rows.forEach(r => { edit[r.id] = r.proposed_status || r.status })
    return { edit }
  },
  methods: {
    badge(s) { return s === 'Done' ? 'maiic-badge-green' : (s === 'Outstanding' ? 'maiic-badge-red' : (s === 'Partially done' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    sign(r) { const note = prompt('Note for the audit log (optional)') || ''; router.post(route('compliance-audits.sign', r.id), { status: this.edit[r.id], note }, { preserveScroll: true }) },
    approve(r) { router.post(route('compliance-audits.approve', r.id), {}, { preserveScroll: true }) },
  },
}
</script>
