import os, re
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Calculations.vue'; s=open(p,encoding='utf-8').read()
a=s.index('      <div\n        v-if="Number(approvalSummary.total || 0) > 0"')
b=s.index('      <div class="maiic-filterbar">')
s=s[:a]+s[b:]
s=rep(s,'''    <template #actions>
      <button v-if="selected.length" type="button" class="primary-btn" @click="calculate">Calculate selected ({{ selected.length }})</button>
    </template>''','''    <template #actions>
      <button v-if="selected.length" type="button" class="secondary-btn" @click="calculate">Calculate selected ({{ selected.length }})</button>
      <button
        v-if="Number(approvalSummary.total || 0) > 0"
        type="button"
        class="primary-btn"
        :disabled="bulkApproving || Number(approvalSummary.eligible || 0) === 0"
        :title="bulkTitle"
        @click="approveAll"
      >
        {{ bulkApproving ? 'Approving...' : `Approve and lock all eligible (${approvalSummary.eligible || 0})` }}
      </button>
    </template>''')
s=rep(s,'''          <span v-if="hasActiveFilters" class="badge-blue">Filters active</span>''','''          <span v-if="hasActiveFilters" class="badge-blue">Filters active</span>
          <span v-if="Number(approvalSummary.total || 0) > 0" class="text-xs text-gray-500">{{ bulkTitle }}</span>''')
s=rep(s,'''    selectable() {''','''    bulkTitle() {
      const a = this.approvalSummary
      let t = `${a.eligible || 0} calculated contract(s) can be approved across all pages.`
      if (a.admin_override) t += ' Administrator override is active.'
      else if (a.own || a.missing_maker) t += ` ${a.own || 0} calculated by you and ${a.missing_maker || 0} without maker evidence will be skipped.`
      return t
    },
    selectable() {''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
