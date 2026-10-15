import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/LossGivenDefault')

p='Index.vue'; s=load(p)
a=s.index('            <template #header>')
b=s.index('  <div v-if="showModal" class="fixed inset-0')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>LGD Model</span><span>/</span><span class="font-medium text-maiic-700">Monthly LGD</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly LGD</h2>
                <p class="mt-1 text-sm text-gray-600">Loss given default from cures and recoveries on Stage 3 loans, one run per window</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openReportModal">Get report</button>
            <Link :href="route('loss-given-default.create')" class="primary-btn">Calculate LGD</Link>
        </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4">
          <h3 class="font-semibold text-gray-900">LGD runs</h3>
          <p class="text-xs text-gray-500">{{ lossGivenDefaults.length }} run(s). A closed run is locked for use in the ECL; amounts in MWK.</p>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr>
                <th>Window</th>
                <th>Portfolio</th>
                <th class="num">LGD %</th>
                <th class="num">Cure rate %</th>
                <th class="num">Recovery rate %</th>
                <th class="num">Recovered</th>
                <th class="num">Stage 3 balance, start / end</th>
                <th>Source</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lgd in pagedRows" :key="lgd.id">
                <td class="whitespace-nowrap">{{ formatDate(lgd.start_period) }} to {{ formatDate(lgd.reporting_period) }}</td>
                <td>{{ lgd.portfolio_group?.name || '-' }}</td>
                <td class="num font-bold" :class="lgd.loss_given_default_percentage >= 0.5 ? 'text-amber-700' : 'text-maiic-700'">{{ pct(lgd.loss_given_default_percentage) }}</td>
                <td class="num">{{ pct(lgd.cure_rate) }}</td>
                <td class="num">{{ pct(lgd.recovery_rate) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgd.recovered_amount) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgd.start_total_stage3) }}<div class="text-xs text-gray-500">{{ formatCurrency(lgd.end_total_stage3) }}</div></td>
                <td class="whitespace-nowrap">{{ lgd.calculation_source === 'manual' ? 'Manual' : 'System' }}<div class="text-xs" :class="lgd.is_discounting ? 'text-maiic-700' : 'text-gray-500'">{{ lgd.is_discounting ? 'Discounted' : 'Not discounted' }}</div></td>
                <td><span class="maiic-badge" :class="lgd.is_active_or_closed === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ lgd.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}</span></td>
                <td class="whitespace-nowrap">{{ formatDate(lgd.created_at) }}<div v-if="lgd.created_by" class="text-xs text-gray-500">{{ lgd.created_by }}</div></td>
                <td>
                  <div class="flex flex-wrap justify-end gap-1.5">
                    <Link v-if="lgd.is_discounting" :href="route('loss-given-default.discounted-payments', lgd.id)" class="maiic-action maiic-action-view" title="View the discounted payments"><font-awesome-icon icon="eye" /></Link>
                    <button v-if="lgd.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-edit" title="Edit this LGD" @click="editLGD(lgd.id)"><font-awesome-icon icon="pen" /></button>
                    <button type="button" class="maiic-action" :class="lgd.is_active_or_closed === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :disabled="loading === lgd.id" :title="lgd.is_active_or_closed === 'closed' ? 'Unlock this LGD' : 'Lock this LGD'" @click="lockLGD(lgd.id)"><font-awesome-icon :icon="loading === lgd.id ? 'spinner' : (lgd.is_active_or_closed === 'closed' ? 'lock-open' : 'lock')" :spin="loading === lgd.id" /></button>
                    <button v-if="lgd.is_active_or_closed === 'closed'" type="button" class="maiic-action maiic-action-neutral" :disabled="loading === lgd.id" title="Update the loan book" @click="openUpdateModal(lgd)"><font-awesome-icon icon="book" /></button>
                    <button v-if="lgd.is_active_or_closed === 'active'" type="button" class="maiic-action maiic-action-delete" :disabled="loading === lgd.id" title="Delete this LGD run" @click="deleteLGD(lgd.id)"><font-awesome-icon icon="trash" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!lossGivenDefaults.length">
                <td colspan="11" class="maiic-empty">No LGD runs yet. Use <strong>Calculate LGD</strong> at the top right to run the first one.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="lossGivenDefaults.length" class="border-t border-gray-100" />
      </div>
    </div>

'''+s[b:]
s=rep(s,'<span v-if="reportLoading">Preparing\u2026</span>','<span v-if="reportLoading">Preparing...</span>')
s=rep(s,"import { ref } from 'vue';","import { ref, computed } from 'vue';")
s=rep(s,"import { router } from '@inertiajs/vue3';","import { router, Link } from '@inertiajs/vue3';\nimport { confirmDialog } from '@/Components/confirmDialog';\nimport { notice } from '@/Components/Maiic/notice';\nimport RowPager from '@/Components/Maiic/RowPager.vue';")
s=rep(s,"import '@fortawesome/fontawesome-free/css/all.css';\n","")
s=rep(s,'''        AppLayout,
        HelpManual,''','''        AppLayout,
        HelpManual,
        Link,
        RowPager,''')
s=rep(s,"    setup() {","    setup(props) {")
s=rep(s,"        const loading = ref(null);","""        const loading = ref(null);
        const page = ref(1);
        const pagedRows = computed(() => props.lossGivenDefaults.slice((page.value - 1) * 15, page.value * 15));
        const pct = (v) => (v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2));""")
s=rep(s,"            if (!value) return 'E0.00';","            if (!value) return '0.00';")
s=rep(s,'''        const formatDate = (dateStr) => {
            const date''','''        const formatDate = (dateStr) => {
            if (!dateStr) return '-';
            const date''')
s=rep(s,'''        const lockLGD = (id) => {
            if (confirm('Are you sure you want to change the lock status of this LGD?')) {''','''        const lockLGD = async (id) => {
            if (await confirmDialog({ title: 'Change the lock on this LGD?', message: 'A closed (locked) LGD is the one the ECL uses; an active run can still change.', confirmLabel: 'Change lock' })) {''')
s=s.replace("onError: () => { alert('Something went wrong. Please try again.'); },","onError: () => { notice('Something went wrong', 'Please try again.', 'danger'); },")
s=rep(s,'''                    alert('Something went wrong. Please try again.');''','''                    notice('Something went wrong', 'Please try again.', 'danger');''')
s=rep(s,'''                alert('Please select a period.');''','''                notice('Choose a period', 'Pick the reporting month whose loan book should be updated.', 'warning');''')
s=rep(s,'''        const deleteLGD = (id) => {
            if (confirm('Are you sure?')) {''','''        const deleteLGD = async (id) => {
            if (await confirmDialog({ title: 'Delete this LGD run?', message: 'The run and its figures are removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {''')
s=rep(s,'''            alert('Please select both start and end periods.');''','''            notice('Choose both periods', 'Pick a start and an end period for the report.', 'warning');''')
s=rep(s,'''            alert('Start period cannot be after end period.');''','''            notice('Check the periods', 'The start period cannot be after the end period.', 'warning');''')
s=rep(s,'''        return {
            formatDate,''','''        return {
            page,
            pagedRows,
            pct,
            formatDate,''')
save(p,s)
print('ok')
