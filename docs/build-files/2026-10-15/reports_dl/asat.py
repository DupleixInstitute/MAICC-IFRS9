exec(open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl/ed.py').read())
P = 'inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700'
S = 'inline-flex items-center rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50'
p = 'resources/js/Pages/Eir/AsAt/Index.vue'
edit(p, [
("""  <app-layout title="EIR as at a Date" description="The EIR computation for the book, and for any loan, as at any date you name: the amortised cost, the gross carrying amount, the EIR and contractual interest to the date and the difference, which is the revenue shift (spec v4 section 6.11)">
    <template #actions>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'xlsx' })" class="primary-btn text-sm">Download Excel</a>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'pdf' })" class="secondary-btn text-sm">Download PDF</a>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'csv' })" class="secondary-btn text-sm">Download CSV</a>
    </template>""",
"""  <app-layout title="EIR as at a date">
    <template #header>
      <BackToReports tab="eir"/>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">EIR as at a date</h2>
      <p class="mt-0.5 text-sm text-gray-500">The EIR book at any date: amortised cost, gross carrying amount, EIR and contractual interest to the date and the difference (the revenue shift).</p>
    </template>
    <template #actions>
      <DownloadButton format="CSV" :href="book ? route('eir-as-at.export', { date, format: 'csv' }) : ''" :disabled="!book"/>
      <DownloadButton format="Excel" :href="book ? route('eir-as-at.export', { date, format: 'xlsx' }) : ''" :disabled="!book"/>
      <DownloadButton format="PDF" label="Download PDF" :href="book ? route('eir-as-at.export', { date, format: 'pdf' }) : ''" :disabled="!book"/>
    </template>"""),
("""        <button type="submit" class="primary-btn text-sm">View</button>""", """        <button type="submit" class=\"""" + P + """\">View</button>"""),
("""class="secondary-btn text-xs" :disabled="lastLedgerDate && d > lastLedgerDate">""", """class=\"""" + S + """\" :disabled="lastLedgerDate && d > lastLedgerDate">"""),
("""        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Contracts with a locked EIR</div><div class="maiic-kpi-value">{{ book.total.contracts }}</div></div>
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">EIR interest, year to {{ book.as_at }}</div><div class="maiic-kpi-value">{{ fmt(book.total.eir_ytd) }}</div></div>
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Contractual interest, year to date</div><div class="maiic-kpi-value">{{ fmt(book.total.contractual_ytd) }}</div></div>
          <div class="maiic-kpi" :style="'--accent:' + (book.total.difference >= 0 ? '#16a34a' : '#dc2626')"><div class="maiic-kpi-label">The revenue shift</div><div class="maiic-kpi-value">{{ fmt(book.total.difference) }}</div></div>
        </div>""",
"""        <KpiRow :items="[
          { label: 'Contracts with a locked EIR', value: book.total.contracts },
          { label: 'EIR interest, year to ' + book.as_at, value: fmt(book.total.eir_ytd) },
          { label: 'Contractual interest, year to date', value: fmt(book.total.contractual_ytd), tone: 'amber' },
          { label: 'The revenue shift', value: fmt(book.total.difference), tone: book.total.difference >= 0 ? 'maiic' : 'rose' },
        ]"/>"""),
("""import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },""",
"""import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import BackToReports from '@/Pages/Reports/Partials/BackToReports.vue'
import DownloadButton from '@/Pages/Reports/Partials/DownloadButton.vue'
import KpiRow from '@/Pages/Reports/Partials/KpiRow.vue'

export default {
  components: { AppLayout, BackToReports, DownloadButton, KpiRow },"""),
])
raw = open(p, encoding='utf-8', newline='').read()
raw = raw.replace('class="maiic-kpi" style="--accent:#7c3aed"', 'class="maiic-kpi px-4 py-3"')
open(p, 'w', encoding='utf-8', newline='').write(raw)
print('ok')
