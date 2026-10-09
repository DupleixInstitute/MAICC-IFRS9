<template>
  <app-layout :title="audit.short" :description="audit.title">
    <template #actions>
      <Link :href="route('compliance-audits.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">All workbooks</Link>
      <a v-for="f in ['xlsx', 'pdf', 'md']" :key="f" :href="route('compliance-audits.download', [audit.id, f])" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold uppercase text-gray-700 shadow-sm transition hover:bg-gray-50" :title="'Download the ' + f.toUpperCase() + ' file'"><font-awesome-icon icon="download"/> {{ f }}</a>
    </template>

    <div class="space-y-4">
      <!-- One line: the basis and the status counts -->
      <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
        <span v-if="audit.basis" class="mr-2">{{ audit.basis }}</span>
        <span v-for="(n, s) in counts" :key="s" class="maiic-badge" :class="badge(s)">{{ s }} {{ n }}</span>
      </div>

      <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- One view switch: the Audit section row above stays the page's only tab row -->
        <div class="border-b border-gray-200 px-4 py-3">
          <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1" role="group" aria-label="View">
            <button v-for="t in tabs" :key="t.key" type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold transition"
                    :class="tab === t.key ? 'bg-white text-maiic-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'" :aria-pressed="tab === t.key" @click="tab = t.key">
              {{ t.label }}
              <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
            </button>
          </div>
        </div>

        <div v-if="tab === 'sections'">
          <div class="maiic-table-wrap">
            <table class="maiic-table">
              <thead><tr><th>Ref</th><th>Section</th><th>What it requires</th><th>Status</th><th>What the system does</th><th>Where to see it</th><th>Setting</th><th>Test</th><th>Sign-off</th><th v-if="canGovern">Review</th></tr></thead>
              <tbody>
                <template v-for="(r, i) in pagedRows" :key="r.id">
                  <tr v-if="i === 0 || pagedRows[i - 1].part !== r.part" class="total"><td :colspan="canGovern ? 10 : 9">{{ r.part }}</td></tr>
                  <tr class="align-top text-xs">
                    <td class="font-mono">{{ r.reference }}</td>
                    <td class="font-semibold">{{ r.section_name }}</td>
                    <td>{{ r.requirement }}</td>
                    <td><span class="maiic-badge" :class="badge(r.status)">{{ r.status }}</span><div v-if="r.proposed_status" class="mt-1 text-amber-700">Proposed: {{ r.proposed_status }}</div></td>
                    <td>{{ r.engine_comment }}<div v-if="r.compliance_comment" class="mt-1 italic text-gray-500">{{ r.compliance_comment }}</div></td>
                    <td><div v-for="(l, k) in r.links" :key="k"><Link :href="l.href" class="font-semibold text-maiic-700 hover:underline">{{ l.label }}</Link></div><span v-if="!r.links.length">{{ r.where_to_see }}</span></td>
                    <td class="font-mono">{{ r.governance_setting }}</td>
                    <td class="font-mono">{{ r.test }}</td>
                    <td><span v-if="r.signer">Signed {{ r.signer }} {{ (r.signed_at || '').slice(0, 10) }}</span><span v-if="r.approver"><br>Approved {{ r.approver }} {{ (r.approved_at || '').slice(0, 10) }}</span><span v-if="!r.signer && !r.approver" class="text-gray-400">-</span></td>
                    <td v-if="canGovern" class="whitespace-nowrap">
                      <select v-model="edit[r.id]" class="maiic-select py-1 text-xs" :aria-label="'Status for ' + r.reference"><option v-for="s in statuses" :key="s" :value="s">{{ s }}</option></select>
                      <div class="mt-1 flex gap-1">
                        <button type="button" class="maiic-action maiic-action-edit" title="Sign this status" @click="openSign(r)"><font-awesome-icon icon="pen"/></button>
                        <button v-if="r.proposed_status" type="button" class="maiic-action maiic-action-view" title="Approve the proposed status" @click="approve(r)"><font-awesome-icon icon="check"/></button>
                      </div>
                    </td>
                  </tr>
                </template>
                <tr v-if="!rows.length"><td :colspan="canGovern ? 10 : 9" class="maiic-empty">This workbook has no sections.</td></tr>
              </tbody>
            </table>
          </div>
          <div v-if="rows.length > 15" class="border-t border-gray-100 p-4"><ClientPager v-model="page" :total="rows.length"/></div>
        </div>

        <div v-else class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th class="num">No.</th><th>Ref</th><th>Finding</th><th>What was found</th><th>Impact</th><th>Recommended action</th><th>Owner</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="f in findings" :key="f.id" class="align-top text-xs"><td class="num">{{ f.number }}</td><td>{{ f.reference }}</td><td class="font-semibold">{{ f.finding }}</td><td>{{ f.what_was_found }}</td><td>{{ f.impact }}</td><td>{{ f.recommended_action }}</td><td>{{ f.owner }}</td><td><span class="maiic-badge" :class="f.status === 'Closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ f.status }}</span></td></tr>
              <tr v-if="!findings.length"><td colspan="8" class="maiic-empty">No findings recorded for this workbook.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Sign dialog: replaces the browser prompt -->
    <div v-if="signing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="maiic-panel w-full max-w-md">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
          <h2 class="text-base font-bold text-gray-900">Sign {{ signing.reference }}</h2>
          <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="signing = null">&times;</button>
        </div>
        <div class="space-y-3 p-5 text-sm">
          <p>You are signing the status <span class="maiic-badge" :class="badge(edit[signing.id])">{{ edit[signing.id] }}</span>. A second person then approves it.</p>
          <div>
            <label for="sign-note" class="maiic-flabel">Note for the audit log (optional)</label>
            <textarea id="sign-note" v-model="note" rows="3" maxlength="255" class="maiic-input"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
          <button type="button" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50" @click="signing = null">Cancel</button>
          <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white hover:bg-maiic-700" @click="sign"><font-awesome-icon icon="pen"/> Sign</button>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import ClientPager from '@/Components/ClientPager.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, ClientPager, Link },
  props: { audit: Object, rows: Array, counts: Object, statuses: Array, findings: Array, canGovern: Boolean },
  data() {
    const edit = {}
    this.rows.forEach(r => { edit[r.id] = r.proposed_status || r.status })
    return { edit, tab: 'sections', page: 1, signing: null, note: '' }
  },
  computed: {
    tabs() {
      return [
        { key: 'sections', label: 'Sections', count: this.rows.length },
        { key: 'findings', label: 'Findings', count: this.findings.length },
      ]
    },
    pagedRows() { return this.rows.slice((this.page - 1) * 15, this.page * 15) },
  },
  methods: {
    badge(s) { return s === 'Done' ? 'maiic-badge-green' : (s === 'Outstanding' ? 'maiic-badge-red' : (s === 'Partially done' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    openSign(r) { this.signing = r; this.note = '' },
    sign() {
      const r = this.signing
      router.post(route('compliance-audits.sign', r.id), { status: this.edit[r.id], note: this.note }, { preserveScroll: true, onFinish: () => { this.signing = null } })
    },
    approve(r) { router.post(route('compliance-audits.approve', r.id), {}, { preserveScroll: true }) },
  },
}
</script>
