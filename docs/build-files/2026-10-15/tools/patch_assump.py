import sys
T = r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/'
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Macro/Index.vue'
s = open(p, encoding='utf-8').read()
a = s.index('      <!-- Scenario assumptions -->')
b = s.index('      <!-- Import / export -->')
s = s[:a] + open(T + 'assump_tpl.txt', encoding='utf-8').read() + s[b:]

def rep(x, y):
    global s
    if x not in s:
        sys.exit('NOT FOUND ' + x[:60])
    s = s.replace(x, y, 1)

def pick_set(sets):
    return None

rep("return { tab: viewFromUrl(), sel: this.selected, aPeriod: this.assumptions?.period,",
    "return { tab: viewFromUrl(), sel: this.selected, aPeriod: this.assumptions?.period, aSetId: this.defaultSetId(this.assumptions), aScenarioId: null,")
rep("    current() { return this.series.find(s => s.id === this.sel) },",
    """    current() { return this.series.find(s => s.id === this.sel) },
    aSet() { return (this.assumptions?.sets || []).find(s => s.id === this.aSetId) || null },
    aScenario() { const sc = this.aSet ? this.aSet.scenarios : []; return sc.find(x => x.id === this.aScenarioId) || sc.find(x => !x.is_base) || sc[0] || null },
    glanceCodes() {
      if (!this.aSet) return []
      const codes = new Set()
      this.aSet.scenarios.forEach(sc => (sc.shocks || []).forEach(sh => { if (Number(sh.year_offset) === 0) codes.add(sh.statistic_code) }))
      return [...codes].sort((a, b) => this.varName(a).localeCompare(this.varName(b)))
    },""")
rep("  watch: { assumptions() { this.edits = {} } },",
    "  watch: { assumptions(v) { this.edits = {}; if (!(v.sets || []).some(s => s.id === this.aSetId)) this.aSetId = this.defaultSetId(v) }, aSetId() { this.aScenarioId = null } },")
rep("    kindLabel(k) {",
    """    defaultSetId(a) { const sets = (a && a.sets) || []; const pick = sets.find(s => s.status === 'APPROVED' || s.status === 'LOCKED') || sets[0]; return pick ? pick.id : null },
    statusLabel(st) { return { DRAFT: 'draft', PROPOSED: 'proposed', APPROVED: 'approved', LOCKED: 'locked', SUPERSEDED: 'superseded' }[st] || st },
    varName(code) { const v = (this.assumptions?.variables || []).find(x => x.statistic_code === code); return v ? v.statistic_name : code },
    moved(sc, code) { const b = (this.aSet.base[code] || [])[0]; const v = (sc.path[code] || [])[0]; return v !== null && v !== undefined && b !== null && b !== undefined && Math.abs(v - b) > 1e-9 },
    async discard(set) { if (await confirmDialog({ title: `Discard draft version ${set.version}?`, message: 'The draft, its scenarios and their shocks are removed. The audit log keeps what was there. An approved set is not affected.', confirmLabel: 'Discard', tone: 'danger' })) router.post(route('scenario-sets.discard', set.id), {}, { preserveScroll: true, onSuccess: () => this.goAssumptions() }) },
    kindLabel(k) {""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
