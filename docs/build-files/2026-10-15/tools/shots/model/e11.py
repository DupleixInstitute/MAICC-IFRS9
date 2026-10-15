import os, re
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Calculations.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''    approve(c) {
      if (confirm(`Approve and permanently lock the original EIR for ${c.contract_id}?`)) {
        this.$inertia.post(this.route('eir-calculations.approve', c.id))
      }
    },
    approveAll() {
      const eligible = Number(this.approvalSummary.eligible || 0)
      if (!eligible) return

      const confirmed = confirm(
        `Approve and permanently lock all ${eligible} eligible calculated EIRs across every page? This cannot be undone.`
      )
      if (!confirmed) return
''','''    async approve(c) {
      if (!(await confirmDialog({
        title: `Approve and lock ${c.contract_id}?`,
        message: 'The original EIR is locked permanently. Only an administrator can reopen it.',
        confirmLabel: 'Approve and lock',
      }))) return
      this.$inertia.post(this.route('eir-calculations.approve', c.id))
    },
    async approveAll() {
      const eligible = Number(this.approvalSummary.eligible || 0)
      if (!eligible) return

      if (!(await confirmDialog({
        title: `Approve and lock ${eligible} EIRs?`,
        message: `All ${eligible} eligible calculated EIRs across every page are locked permanently. This cannot be undone.`,
        confirmLabel: 'Approve and lock all',
        tone: 'danger',
      }))) return
''')
s=rep(s,"import KpiRow from '@/Components/Maiic/KpiRow.vue'","import KpiRow from '@/Components/Maiic/KpiRow.vue'\nimport { confirmDialog } from '@/Components/confirmDialog'")
open(p,'w',encoding='utf-8').write(s)
print('ok')
