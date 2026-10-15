import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI')

p='Adjustments.vue'; s=load(p)
s=rep(s,'''<app-layout title="FLI Adjustments" description="The chain's fits for the period, the one approved for the route, the transmission methods with their preconditions checked against the data, and the route's result on every loan (spec v4 sections 14.6 to 14.8)">''',
'''<app-layout title="Regression (FLI Adjustments)" description="The fitted macro relationships for a period, the one approved for use, and what it does to each loan's PD">''')
s=rep(s,'''<form @submit.prevent="go" class="flex items-center gap-2"><input v-model="form.period" type="month" class="maiic-input"/>''','''<form @submit.prevent="go" class="flex items-center gap-2"><input v-model="form.period" type="month" class="maiic-input !w-44" aria-label="Period"/>''')
s=rep(s,'''<div class="grid grid-cols-1 gap-4 md:grid-cols-4">''','''<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">''')
s=s.replace('<div class="maiic-kpi" style="--accent:#0284c7">','<div class="maiic-kpi !py-3" style="--accent:#0284c7">')
s=rep(s,'''<div class="maiic-kpi-label">Average PD, pre → post</div><div class="maiic-kpi-value">{{ loans && loans.n ? pct(loans.pre) + ' → ' + pct(loans.post) : '-' }}</div>''','''<div class="maiic-kpi-label">Average PD, before and after</div><div class="text-xl font-bold">{{ loans && loans.n ? pct(loans.pre) + ' to ' + pct(loans.post) : '-' }}</div>''')
s=rep(s,'''<div class="maiic-kpi-label">Loans with a post-FLI PD</div><div class="maiic-kpi-value">''','''<div class="maiic-kpi-label">Loans with a post-FLI PD</div><div class="text-xl font-bold">''')
s=rep(s,'''{{ approved ? ('#' + approved.id + ' ' + approved.statistic_code + ' → ' + approved.proxy_code + ' (lag ' + approved.lag_months + ')') : 'none: the PD holds (overlay at zero)' }}''','''{{ approved ? ('#' + approved.id + ' ' + approved.statistic_code + ' to ' + approved.proxy_code + ' (lag ' + approved.lag_months + ')') : 'None: the PD holds (overlay at zero)' }}''')
s=rep(s,'''{{ loans.adjusted }} adjusted · route {{ loans.route }} · fit {{ loans.fit }} · set {{ loans.set_id }}''','''{{ loans.adjusted }} adjusted, route {{ loans.route }}, fit {{ loans.fit }}, set {{ loans.set_id }}''')
# last run: one-line caption
s=rx(s,r'<div v-if="lastRun" class="maiic-panel p-5 text-sm">.*?</div>\s*\n\s*<div class="maiic-panel">',
'''<p v-if="lastRun" class="-mt-3 text-xs text-gray-500">
        Last run for {{ period }}: route {{ lastRun.route }}, method {{ lastRun.method }}, weighting {{ lastRun.weighting }}<span v-if="lastRun.fit_relationship">, fit {{ lastRun.fit }} {{ lastRun.fit_relationship }}</span>; {{ lastRun.loans }} loans, {{ lastRun.adjusted }} adjusted, {{ lastRun.held }} held.<span v-if="lastRun.note" class="text-amber-700"> {{ lastRun.note }}</span>
        <span class="ml-1">Adjustment per scenario: <span v-for="(a, n) in lastRun.scenarios" :key="n" class="mr-2">{{ n }} {{ a }}</span></span>
      </p>

      <div class="maiic-panel">''')
s=rep(s,'''<div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The fits of {{ period }}</h3><p class="text-sm text-gray-500">Applied by the guardrail, best first; declined ones with their reason. A reviewer proposes one for the route; a second person approves it.</p></div>''','''<div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Fits for {{ period }}</h3><p class="text-xs text-gray-500">{{ fits.length }} fit(s), best first; declined ones show the reason. A reviewer proposes one for use and a second person approves it.</p></div>''')
s=rep(s,'''<td v-if="canGovern" class="whitespace-nowrap"><button v-if="f.verdict === 'applied' && f.approval_status === 'NONE'" @click="post('fli-adjustments.propose', f.id)" class="secondary-btn text-xs">Propose</button><button v-if="f.approval_status === 'PROPOSED'" @click="post('fli-adjustments.approve', f.id)" class="primary-btn text-xs">Approve</button></td>''','''<td v-if="canGovern" class="whitespace-nowrap"><div class="flex justify-end gap-1.5"><button v-if="f.verdict === 'applied' && f.approval_status === 'NONE'" type="button" @click="post('fli-adjustments.propose', f.id)" class="maiic-action maiic-action-edit" title="Propose this fit for use"><font-awesome-icon icon="share" /></button><button v-if="f.approval_status === 'PROPOSED'" type="button" @click="post('fli-adjustments.approve', f.id)" class="maiic-action maiic-action-view" title="Approve this fit"><font-awesome-icon icon="check" /></button></div></td>''')
s=rep(s,'''<span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict }}</span>''','''<span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict === 'applied' ? 'Applied' : (f.verdict === 'declined' ? 'Declined' : f.verdict) }}</span>''')
s=rep(s,'''<td class="text-xs">{{ f.approval_status }}<span''','''<td class="text-xs"><span class="maiic-badge" :class="f.approval_status === 'APPROVED' ? 'maiic-badge-green' : (f.approval_status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')">{{ approvalLabel(f.approval_status) }}</span><span''')
s=rep(s,'''<tr v-if="!fits.length"><td colspan="12" class="maiic-empty">No fits for this period: run fli:correlate.</td></tr>''','''<tr v-if="!fits.length"><td colspan="12" class="maiic-empty">No fits for this period yet. Run the correlation on the Correlation Finder tab, then come back to propose one.</td></tr>''')
# method cards behind a disclosure, closed by default
s=rep(s,'''      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div v-for="c in cards"''','''      <details class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4">
          <span><span class="font-semibold text-gray-900">How the adjustment reaches the PD</span><span class="ml-2 text-xs text-gray-500">{{ cards.length }} methods; in force: {{ methodInForce }}</span></span>
          <span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span>
        </summary>
      <div class="grid grid-cols-1 gap-4 border-t border-gray-200 p-5 lg:grid-cols-2">
        <div v-for="c in cards"''')
s=rep(s,'''          <p v-if="c.example" class="mt-2 rounded bg-gray-50 p-2 text-xs dark:bg-slate-900/60">Example on {{ c.example.contract_id }} ({{ c.example.customer_name }}, stage {{ c.example.stage }}): {{ c.example.steps.join('; ') }}</p>
        </div>
      </div>''','''          <p v-if="c.example" class="mt-2 rounded bg-gray-50 p-2 text-xs dark:bg-slate-900/60">Example on {{ c.example.contract_id }} ({{ c.example.customer_name }}, stage {{ c.example.stage }}): {{ c.example.steps.join('; ') }}</p>
        </div>
      </div>
      </details>''')
s=rep(s,'''<div v-for="c in cards" :key="c.key" class="maiic-panel p-5 text-sm"''','''<div v-for="c in cards" :key="c.key" class="rounded-xl border border-gray-200 p-5 text-sm"''')
s=rep(s,'''<span v-if="c.in_force" class="maiic-badge maiic-badge-green ml-1">in force</span><span v-if="c.seeded" class="maiic-badge maiic-badge-grey ml-1">seeded</span><span v-if="!c.available" class="maiic-badge maiic-badge-gold ml-1">preconditions not met</span>''','''<span v-if="c.in_force" class="maiic-badge maiic-badge-green ml-1">In force</span><span v-if="c.seeded" class="maiic-badge maiic-badge-grey ml-1">Seeded</span><span v-if="!c.available" class="maiic-badge maiic-badge-gold ml-1">Preconditions not met</span>''')
s=rep(s,'''<p class="mt-1 text-gray-700 dark:text-slate-300">{{ c.what }}</p>''','''<p class="mt-1 text-gray-700 dark:text-slate-300">{{ plain(c.what) }}</p>''')
s=rep(s,'''<div class="space-y-6">''','''<div class="space-y-4">''')
s=rep(s,'''    pct(v) {''','''    approvalLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', NONE: 'Not proposed' }[s] || s },
    // Specification references are for the build team, not the screen.
    plain(t) { return String(t || '').replace(/\\s*\\((?:spec v\\d+ section [\\d.]+[^)]*|O\\d+|decision D\\d+)\\)/g, '').replace(/\\s*of section [\\d.]+/g, '') },
    pct(v) {''')
save(p,s)
print('ok')
