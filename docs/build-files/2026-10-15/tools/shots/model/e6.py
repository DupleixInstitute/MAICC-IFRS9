import os, re
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Data.vue'; s=open(p,encoding='utf-8').read()
a=s.index('        <div class="border-b border-gray-200 px-5 pt-4">\n          <nav class="flex gap-6 overflow-x-auto">')
b=s.index('        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 md:flex-row md:items-center md:justify-between">')
s=s[:a]+s[b:]
s=rep(s,'''            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">EIR Data</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">EIR Data</h2>
          <p class="mt-1 text-sm text-gray-600">Review source records, calculated interest, and the GL reconciliation</p>''','''            <span>EIR &amp; Revenue Recognition</span><span>/</span><span>EIR Data</span><span>/</span><span class="font-medium text-maiic-700">{{ currentTab.label }}</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">{{ currentTab.label }}</h2>
          <p class="mt-1 text-sm text-gray-600">{{ currentTab.description }}</p>''')
# The panel's own sub-header repeated the title: keep it as a count line.
s=rep(s,'''            <h3 class="font-semibold text-gray-900">{{ currentTab.label }}</h3>
            <p class="text-xs text-gray-500">{{ currentTab.description }}</p>''','''            <h3 class="font-semibold text-gray-900">{{ currentTab.listTitle }}</h3>
            <p class="text-xs text-gray-500">{{ number(currentTab.count) }} record(s){{ search ? ' matching the search' : '' }}</p>''')
s=rep(s,'''        { key: 'contracts', label: 'Contract Master', count: this.summary.contracts, description: 'Facility terms, source conventions, fee and schedule coverage.' },
        { key: 'cashflows', label: 'Cash Flows', count: this.summary.cashflows, description: 'Contractual principal, interest and fee cash flows by due date.' },
        { key: 'schedules', label: 'Schedule Review', count: this.summary.contracts, description: 'Generate original schedules from Extract A, compare to Extract B remaining rows, then approve.' },
        { key: 'gl', label: 'GL Reconciliation', count: this.summary.gl_postings, description: 'Compare calculated effective-interest revenue with interest posted to the general ledger.' },''','''        { key: 'contracts', label: 'Contract Master', listTitle: 'Contracts', count: this.summary.contracts, description: 'Facility terms, source conventions, fee and schedule coverage for every contract' },
        { key: 'cashflows', label: 'Cash Flows', listTitle: 'Contractual cash flows', count: this.summary.cashflows, description: 'Contractual principal, interest and fee cash flows by due date' },
        { key: 'schedules', label: 'Schedule Review', listTitle: 'Schedules by contract', count: this.summary.contracts, description: 'Generate the original schedule from Extract A, compare it with E-Banker\\'s remaining rows (Extract B), then approve version 1' },
        { key: 'gl', label: 'GL Reconciliation', listTitle: 'GL interest postings', count: this.summary.gl_postings, description: 'Compare calculated effective-interest revenue with interest posted to the general ledger' },''')
s=rep(s,'''      return this.tabs.find(tab => tab.key === this.activeTab)''','''      return this.tabs.find(tab => tab.key === this.activeTab) || this.tabs[0]''')
s=rep(s,'''    <teleport to="head"><title>EIR Data</title></teleport>''','''    <teleport to="head"><title>{{ currentTab.label }}</title></teleport>''')
# the scoped helper styles now live in app.css
s=re.sub(r'\n<style scoped>\n\.form-input\{[^\n]*\n</style>\n', '\n<style scoped>\n.form-input{@apply block rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-maiic-500 focus:outline-none focus:ring-2 focus:ring-maiic-500}\n</style>\n', s)
open(p,'w',encoding='utf-8').write(s)
print('ok')
