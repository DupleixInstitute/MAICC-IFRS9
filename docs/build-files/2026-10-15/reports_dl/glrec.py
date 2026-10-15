exec(open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl/ed.py').read())
p = 'resources/js/Pages/Eir/Reconciliation.vue'
edit(p, [
("""        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">GL Reconciliation</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">EIR to GL Reconciliation</h2>
          <p class="mt-1 text-sm text-gray-600">Why calculated interest income differs from what the ledger posted</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <a v-if="period" :href="downloadUrl('xlsx')" class="secondary-btn">Download Excel</a>
          <a v-if="period" :href="downloadUrl('pdf')" class="secondary-btn">Download PDF</a>""",
"""        <div>
          <BackToReports tab="eir"/>
          <h2 class="text-xl font-semibold text-gray-800">GL reconciliation (EIR)</h2>
          <p class="mt-0.5 text-sm text-gray-500">Why calculated interest income differs from what the ledger posted.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <DownloadButton format="Excel" :href="period ? downloadUrl('xlsx') : ''" :disabled="!period"/>
          <DownloadButton format="PDF" label="Download PDF" :href="period ? downloadUrl('pdf') : ''" :disabled="!period"/>"""),
("""        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-2xl font-bold" :class="card.tone">{{ card.value }}</div>
            <div class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.label }}</div>
          </div>
        </div>""",
"""        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div v-for="card in cards" :key="card.label" class="maiic-kpi min-w-0 px-4 py-3">
            <div class="maiic-kpi-label truncate">{{ card.label }}</div>
            <div class="truncate text-xl font-extrabold tabular-nums" :class="card.tone || 'text-gray-900'">{{ card.value }}</div>
          </div>
        </div>"""),
("""            <h3 class="font-semibold text-gray-900">Variance bridge — {{ period }}</h3>
            <p class="mt-1 text-xs text-gray-500">""",
"""            <h3 class="font-semibold text-gray-900">Variance bridge, {{ period }}</h3>
            <details class="mt-1 text-xs text-gray-500"><summary class="cursor-pointer select-none font-semibold text-maiic-700">How the four effects are worked out</summary>
            <p class="mt-1">"""),
("""              correct measurement difference rather than an error.
            </p>
          </div>""",
"""              correct measurement difference rather than an error.
            </p>
            </details>
          </div>"""),
("""                <span class="font-medium text-gray-900">{{ key }}</span> — {{ reason }}""",
"""                <span class="font-medium text-gray-900">{{ key }}</span>: {{ reason }}"""),
("""            The four effects account for the variance in full &mdash; no unexplained residual.""",
"""            The four effects account for the variance in full: no unexplained residual."""),
])
raw = open(p, encoding='utf-8', newline='').read()
raw = raw.replace("'—'", "'-'")
open(p, 'w', encoding='utf-8', newline='').write(raw)
print('ok')
