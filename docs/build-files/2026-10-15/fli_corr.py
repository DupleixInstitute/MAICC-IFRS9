import os
D = os.path.dirname(os.path.abspath(__file__))
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI/Correlation.vue'
s = open(p, encoding='utf-8').read()
new = open(os.path.join(D, 'fli_corr_body.html'), encoding='utf-8').read()
start = s.index('    <div class="space-y-4">')
end = s.index('  </app-layout>')
s = s[:start] + new + s[end:]
def rep(a, b):
    global s
    assert a in s, a
    s = s.replace(a, b)
rep("data() { return { form: { period: this.period }, running: false, page: 1 } },",
    "data() { return { form: { period: this.period }, running: false, page: 1, fitPage: 1, open: {} } },")
rep("    pagedSuggestions() { return this.suggestions.slice((this.page - 1) * 15, this.page * 15) },",
    "    pagedSuggestions() { return this.suggestions.slice((this.page - 1) * 15, this.page * 15) },\n    pagedFits() { return this.fits.slice((this.fitPage - 1) * 15, this.fitPage * 15) },")
rep("    n(v, d) {",
    "    toggle(id) { this.open = { ...this.open, [id]: !this.open[id] } },\n    approvalLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', NONE: 'Not proposed' }[s] || s },\n    n(v, d) {")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
