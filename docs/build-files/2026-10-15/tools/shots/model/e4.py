import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Governance.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''      <div>
        <h2 class="font-semibold text-xl text-gray-800">Governance Centre</h2>
        <p class="mt-1 text-sm text-gray-600">Every calculation convention the EIR engine uses, as a setting with options, an effective date, a proposer and an approver</p>
      </div>''','''      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">Governance Centre</span>
        </div>
        <h2 class="font-semibold text-xl text-gray-800">Governance Centre</h2>
        <p class="mt-1 text-sm text-gray-600">Every calculation setting the engines use, with its options, effective date, proposer and approver</p>
      </div>''')
s=rep(s,'''      <div v-if="flashSuccess" class="rounded-md bg-maiic-100 border border-maiic-200 px-4 py-3 text-sm text-maiic-900">{{ flashSuccess }}</div>
      <div v-if="errorMessage && !showModal" class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ errorMessage }}</div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 border-b">
          <h3 class="text-lg font-semibold">Settings in force on {{ asOf }}</h3>
          <p class="text-sm text-gray-500">{{ settings.length }} settings; {{ pendingCount }} awaiting approval</p>
        </div>''','''      <div v-if="errorMessage && !showModal" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <font-awesome-icon icon="exclamation-circle" class="mt-0.5" />
        <div><div class="font-semibold">The change was not saved</div><div>{{ errorMessage }}</div></div>
      </div>

      <KpiRow :cards="cards" />

      <div>
      <PageTabs v-model="area" :tabs="areaTabs" flush />
      <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b">
          <h3 class="font-semibold text-gray-900">{{ currentArea.label }}<span class="font-normal text-gray-500">, in force on {{ asOf }}</span></h3>
          <p class="text-xs text-gray-500">{{ currentArea.description }}</p>
        </div>''')
s=rep(s,'''              <template v-for="s in settings" :key="s.key">''','''              <template v-for="s in visibleSettings" :key="s.key">''')
s=rep(s,'''                    <p class="mt-1 text-xs text-gray-500">{{ s.description }}</p>''','''                    <p class="mt-1 text-xs text-gray-500">{{ plain(s.description) }}</p>''')
s=rep(s,'''                        class="action mt-1 disabled:text-gray-400 disabled:no-underline"''','''                        class="mt-1 inline-flex items-center gap-1 rounded-md bg-maiic-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-maiic-700 disabled:cursor-not-allowed disabled:bg-gray-300"''')
s=rep(s,'''                    <button @click="openPropose(s)" class="action">Propose change</button>
                    <button @click="toggleHistory(s.key)" class="action">{{ expanded === s.key ? 'Hide history' : 'History' }}</button>''','''                    <div class="flex items-center gap-1.5">
                      <button type="button" @click="openPropose(s)" class="maiic-action maiic-action-edit" title="Propose a change"><font-awesome-icon icon="pen" /></button>
                      <button type="button" @click="toggleHistory(s.key)" class="maiic-action" :class="expanded === s.key ? 'maiic-action-view' : 'maiic-action-neutral'" :title="expanded === s.key ? 'Hide history' : 'Show every value and the audit copies'"><font-awesome-icon icon="history" /></button>
                    </div>''')
s=rep(s,'''              <tr v-if="!settings.length"><td colspan="5" class="p-8 text-center text-gray-500">No governance settings are defined.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>''','''              <tr v-if="!visibleSettings.length"><td colspan="5" class="p-10 text-center text-sm text-gray-500">{{ area === 'pending' ? 'Nothing is awaiting approval.' : 'No settings in this area.' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      </div>
    </div>''')
s=rep(s,'''          <p class="mt-1 text-sm font-normal text-gray-500">{{ proposing?.description }}</p>''','''          <p class="mt-1 text-sm font-normal text-gray-500">{{ plain(proposing?.description) }}</p>''')
s=rep(s,'''import JetDialogModal from '@/Jetstream/DialogModal.vue'
''','''import JetDialogModal from '@/Jetstream/DialogModal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'

// Setting areas for the tabs (display grouping only; the engine reads keys).
const AREAS = [
  { key: 'interest', label: 'Interest and rates', description: 'How rates, resets, moratoria and day counts feed the effective interest rate.',
    keys: ['plr_mid_period', 'reset_trigger', 'moratorium_capitalisation', 'rate_source_precedence', 'margin_basis', 'stage3_interest_basis', 'day_count', 'period_rate_basis', 'rate_change_classification', 'modification_threshold', 'partly_drawn_interest_basis'] },
  { key: 'cashflows', label: 'Cash flows and schedules', description: 'Which records and schedules count as the contractual and expected cash flows.',
    keys: ['cash_source', 'manual_policy_handling', 'counter_reset_handling', 'contractual_record', 'expected_cashflow_basis', 'schedule_approval_control'] },
  { key: 'data', label: 'Data sources', description: 'Where the loan book, history and macro data come from, and which source wins.',
    keys: ['history_before_dec_2025', 'ebanker_feed_route', 'loan_book_build_method', 'takeon_history_basis', 'macro_source_precedence'] },
  { key: 'accounting', label: 'Accounting and controls', description: 'Reconciliation tolerance, journals, exports, special balances and maker-checker.',
    keys: ['recon_tolerance', 'trueup_gl_account', 'auditor_export_format', 'fee_reclass_journal', 'historic_materiality_assessment', 'keyman_insurance_treatment', 'nascomex_preference_shares', 'maker_checker_admin_override'] },
  { key: 'staging', label: 'Staging', description: 'Days past due, Stage 3 triggers, rebuttals and cure periods.',
    keys: ['staging_rebuttal', 'dpd_basis', 'stage3_missed_instalments', 'stage_cure_months'] },
  { key: 'fli', label: 'Forward-looking and scenarios', description: 'How the forward-looking adjustment is built and tested, and how scenarios are weighted.',
    keys: ['fli_adjustment_route', 'fli_transmission_method', 'fli_asset_correlation', 'fli_expected_sign_test', 'fli_r2_cutoff', 'fli_min_observations', 'fli_alpha', 'fli_normality_limits', 'scenario_weighting_method', 'scenario_minimum_count', 'scenario_weight_bounds', 'scenario_calibration_note', 'overlay_requires_approved_set'] },
  { key: 'megafarm', label: 'Mega Farm', description: 'Scope and probability of default for the Mega Farm programme.',
    keys: ['mega_farms_scope', 'megafarm_pd_method', 'megafarm_scalar_ceiling'] },
]
const KNOWN = AREAS.flatMap(a => a.keys)
''')
s=rep(s,'''  components: { AppLayout, JetDialogModal },''','''  components: { AppLayout, JetDialogModal, KpiRow, PageTabs },''')
s=rep(s,'''      openCard: null, showModal: false,''','''      area: 'interest', openCard: null, showModal: false,''')
s=rep(s,'''    pendingCount() {''','''    areas() {
      const list = AREAS.map(a => ({ ...a, settings: this.settings.filter(s => a.keys.includes(s.key)) }))
      const other = this.settings.filter(s => !KNOWN.includes(s.key))
      if (other.length) list.push({ key: 'other', label: 'Other', description: 'Settings not yet placed in an area.', settings: other })
      list.push({ key: 'pending', label: 'Awaiting approval', description: 'Every setting with a proposed change that a second person must approve.',
        settings: this.settings.filter(s => s.rows.some(r => r.state === 'PROPOSED')) })
      return list
    },
    areaTabs() {
      return this.areas.map(a => ({ key: a.key, label: a.label, count: a.settings.length }))
    },
    currentArea() {
      return this.areas.find(a => a.key === this.area) || this.areas[0]
    },
    visibleSettings() {
      return this.currentArea.settings
    },
    cards() {
      const noValue = this.settings.filter(s => !s.in_force).length
      const upcoming = this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'UPCOMING').length, 0)
      return [
        { label: 'Settings', value: this.settings.length, sub: 'in the catalogue' },
        { label: 'In force', value: this.settings.length - noValue, sub: `with an approved value on ${this.asOf}`, accent: '#15803d' },
        { label: 'Awaiting approval', value: this.pendingCount, sub: 'proposed changes', accent: this.pendingCount ? '#d97706' : null },
        { label: 'Approved, not yet effective', value: upcoming, sub: 'apply from a later date', accent: '#0284c7' },
        { label: 'No approved value', value: noValue, sub: 'calculations needing them stop', accent: noValue ? '#dc2626' : null, valueClass: noValue ? 'text-red-700' : '' },
      ]
    },
    pendingCount() {''')
s=rep(s,'''    cardFor(option) {''','''    // Specification references are for the build team, not the screen.
    plain(text) {
      return String(text || '')
        .replace(/\\s*\\((?:spec v\\d+ section [\\d.]+[^)]*|O\\d+)\\)/g, '')
        .replace(/\\s*Open choice O\\d+[^.]*\\./g, '')
        .replace(/\\s*Decided ([^(]*?) \\(O\\d+\\):/g, ' Decided $1:')
        .trim()
    },
    cardFor(option) {''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
