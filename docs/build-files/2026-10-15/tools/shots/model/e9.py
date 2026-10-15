import os, re
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

HOW = '''<details class="group mt-2 text-xs text-gray-600">
            <summary class="cursor-pointer select-none font-semibold text-maiic-700 hover:underline">How this works</summary>
            <p class="mt-1 max-w-4xl">%s</p>
          </details>'''

# ---- Drawdowns
p='Drawdowns.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''        <div class="flex flex-wrap gap-2">
          <Link :href="route('eir-data.index')" class="secondary-btn">EIR Data</Link>
          <Link :href="route('eir-intake.index', { type: 'disbursements' })" class="primary-btn">Import drawdowns</Link>
        </div>''','''        <div class="flex flex-wrap gap-2">
          <Link :href="route('eir-intake.index', { type: 'disbursements' })" class="primary-btn">Import drawdowns</Link>
        </div>''')
a=s.index('      <div class="maiic-filterbar">'); b=s.index('      <KpiRow :cards="cards" />')
s=s[:a]+s[b:]
a=s.index('      <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">'); b=s.index('      <div class="maiic-panel">')
s=s[:a]+s[b:]
s=rep(s,'''        <div class="border-b border-gray-200 p-4">
          <h3 class="font-semibold text-gray-900">Facilities, largest undrawn commitment first</h3>
          <p class="mt-1 text-xs text-gray-500">
            Choose a facility to see each tranche with its date and reference. <strong>Source</strong> says where the amount drawn came from.
          </p>
        </div>''','''        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">Facilities, largest undrawn commitment first</h3>
            <p class="mt-1 text-xs text-gray-500">Choose a facility to see each tranche. <strong>Source</strong> says where the amount drawn came from.</p>
            ''' + (HOW % '''The undrawn commitment on a facility is its approved amount less what has been drawn. Where drawdown rows have been loaded, what has been drawn is the sum of the
              tranches up to the date chosen, so the figure can be struck at any date. Where no rows have been loaded, the figures fall back to the monthly Loan Book Report and the
              row says so: that source carries no dates, so a month with a tranche in it cannot be reconciled from it. Where neither source states an approved amount the commitment
              is shown as not known rather than as nil, because an empty table is not evidence that a facility is fully drawn.''') + '''
          </div>
          <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <label class="text-xs font-semibold text-gray-500" for="dd-asof" title="Only drawdowns on or before this date are counted">Drawn up to</label>
            <input id="dd-asof" v-model="form.as_of" type="date" class="maiic-input !w-40" />
            <input v-model="form.search" type="text" class="maiic-input !w-56" placeholder="Facility number" aria-label="Facility" />
            <button type="submit" class="secondary-btn">Apply</button>
            <button v-if="form.search || form.as_of !== asOf" type="button" class="secondary-btn" @click="reset">Clear</button>
          </form>
        </div>''')
open(p,'w',encoding='utf-8').write(s)

# ---- Reference rates
p='ReferenceRates.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''          <Link :href="route('eir-data.index')" class="secondary-btn">EIR Data</Link>
''','''          <select v-if="indexes.length > 1" v-model="form.index" class="maiic-select !w-32" title="Index" aria-label="Index" @change="apply">
            <option v-for="i in indexes" :key="i" :value="i">{{ i }}</option>
          </select>
''')
a=s.index('      <div v-if="indexes.length > 1" class="maiic-filterbar">'); b=s.index('      <KpiRow :cards="cards" />')
s=s[:a]+s[b:]
a=s.index('      <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">'); b=s.index('      <div class="maiic-panel">')
s=s[:a]+s[b:]
s=rep(s,'''          <p class="mt-1 text-xs text-gray-500">
            <strong>Change</strong> is the move from the previous row in percentage points. A row with no change is a review that left the rate where it was:
            it is kept as delivered but it is not a rate change. <strong>As delivered</strong> and <strong>How it was read</strong> show what the file said and
            whether the day and month had to be swapped back.
          </p>''','''          <p class="mt-1 text-xs text-gray-500"><strong>Change</strong> is the move from the previous row in percentage points; a grey row left the rate where it was.</p>
          ''' + (HOW % '''The spread added to the prime rate (margin) on each loan is derived from this series: the loan-book rate at each month end minus the
              {{ index }} rate in force that day. A loan whose spread moves by more than {{ tolerancePp.toFixed(2) }} percentage points across months is
              held for review rather than given a spread. Dates must be written year first (yyyy-mm-dd); a file with any other date shape is refused as a whole.
              A row with no change is a review that left the rate where it was: it is kept as delivered but it is not a rate change.
              <strong>As delivered</strong> and <strong>How it was read</strong> show what the file said and whether the day and month had to be swapped back.'''))
open(p,'w',encoding='utf-8').write(s)

# ---- Accounting rules: notice box to a caption
p='AccountingRules.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''    <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 text-sm text-maiic-900">Rules make suggestions only. Every imported line remains pending until classified and independently reviewed.</div>
''','')
s=rep(s,'''The lowest priority number is tried first.</p>''','''The lowest priority number is tried first. Rules only suggest: every imported line stays pending until it is classified and reviewed by someone else.</p>''')
open(p,'w',encoding='utf-8').write(s)

# ---- Governance: notice box to a one-line caption under the figures
p='Governance.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''      <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 text-sm text-maiic-900">
        A change takes effect from its effective date forward only, and only once a second person approves it. A month already run keeps the settings it was run under. Nothing here is written in code: if a setting has no approved value, the calculation that needs it stops and says so.
      </div>
''','')
s=rep(s,'''      <KpiRow :cards="cards" />
''','''      <div>
        <KpiRow :cards="cards" />
        <p class="mt-2 text-xs text-gray-500">A change applies from its effective date forward, once a second person approves it. A month already run keeps its settings; a setting with no approved value stops the calculation that needs it.</p>
      </div>
''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
