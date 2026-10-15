import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='ScheduleTable.vue'; s=open(p,encoding='utf-8').read()
s=s.replace("'\u2014'", "'-'")
open(p,'w',encoding='utf-8').write(s)

p='ScheduleShow.vue'; s=open(p,encoding='utf-8').read()
s=s.replace("'\u2014'", "'-'").replace("|| '\u2014' }}", "|| '-' }}")
s=rep(s,'''          <!-- View as at: the EIR computation for this loan at any date, the same service as the Report Hub's (spec v4 section 6.11) -->''','''          <!-- View as at: the EIR computation for this loan at any date, the same service as the Report Hub's -->''')
s=rep(s,'''      <div class="grid gap-3 md:grid-cols-4">
        <div class="card"><div class="metric">{{ label(contract.schedule_approval_status) }}</div><div class="caption">Approval status</div></div>
        <div class="card"><div class="metric">{{ label(comparison.status) }}</div><div class="caption">Comparison</div></div>
        <div class="card"><div class="metric">{{ money(comparison.cash_variance) }}</div><div class="caption">Cash variance before recalculation (MWK)</div></div>
        <div class="card"><div class="metric">{{ comparison.compared_rows ?? 0 }} / {{ comparison.recalculated_rows ?? 0 }}</div><div class="caption">Instalments compared / recalculated by E-Banker</div></div>
      </div>

      <section v-if="(comparison.rows || []).length" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">''','''      <KpiRow :cards="cards" />

      <PageTabs v-model="tab" :tabs="tabs" />

      <section v-if="tab === 'comparison'" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">''')
s=rep(s,'''              <tr v-for="r in comparison.rows" :key="r.number" :class="r.segment==='COMPARED' ? '' : 'bg-gray-50'">''','''              <tr v-for="r in pagedComparison" :key="r.number" :class="r.segment==='COMPARED' ? '' : 'bg-gray-50'">''')
s=rep(s,'''            </tbody>
          </table>
        </div>
      </section>

      <schedule-table title="Generated version-1 schedule"''','''              <tr v-if="!(comparison.rows || []).length"><td colspan="7" class="p-10 text-center text-sm text-gray-500">No instalments to compare yet. Generate the version-1 schedule from Schedule Review first.</td></tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="(comparison.rows || []).length" class="border-t border-gray-100" />
      </section>

      <schedule-table v-if="tab === 'generated'" title="Generated version-1 schedule"''')
s=rep(s,'''      <schedule-table title="E-Banker schedule''','''      <schedule-table v-if="tab === 'ebanker'" title="E-Banker schedule''')
s=rep(s,'''import ScheduleTable from './ScheduleTable.vue'
''','''import ScheduleTable from './ScheduleTable.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
''')
s=rep(s,'''components:{AppLayout,Link,ScheduleTable},''','''components:{AppLayout,Link,ScheduleTable,KpiRow,PageTabs,RowPager},''')
s=rep(s,'''  data(){ return { asAt: new Date(Date.now() - 86400000).toISOString().slice(0, 10) } },
  computed:{''','''  data(){ return { asAt: new Date(Date.now() - 86400000).toISOString().slice(0, 10), tab: (this.comparison?.rows || []).length ? 'comparison' : 'generated', page: 1 } },
  computed:{
    tabs(){ return [
      { key: 'comparison', label: 'Instalment comparison', count: (this.comparison.rows || []).length },
      { key: 'generated', label: 'Generated version 1', count: (this.generated || []).length },
      { key: 'ebanker', label: 'E-Banker schedule', count: (this.remaining || []).length },
    ] },
    pagedComparison(){ return (this.comparison.rows || []).slice((this.page - 1) * 15, this.page * 15) },
    cards(){ const c=this.comparison||{}; return [
      { label: 'Approval status', value: this.label(this.contract.schedule_approval_status), accent: this.contract.schedule_approval_status === 'APPROVED' ? '#15803d' : '#d97706' },
      { label: 'Comparison', value: this.label(c.status), accent: c.status === 'WITHIN_TOLERANCE' ? '#15803d' : '#d97706' },
      { label: 'Cash variance (MWK)', value: this.money(c.cash_variance), sub: 'before E-Banker recalculated' },
      { label: 'Instalments compared', value: `${c.compared_rows ?? 0} / ${c.recalculated_rows ?? 0}`, sub: 'compared / recalculated by E-Banker' },
    ] },''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
