import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/TransitionMatrix')

p='Index.vue'; s=load(p)
a=s.index('        <template #header>')
b=s.index('        <div v-if="showModal" class="fixed inset-0')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>PD Model</span><span>/</span><span class="font-medium text-maiic-700">Monthly Probability</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly Probability</h2>
                <p class="mt-1 text-sm text-gray-600">Monthly transition matrices: how loans move between stages, and the probability of default they give</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="showReportModal = true">Get report</button>
            <Link :href="route('transition-matrices.create')" class="primary-btn">Create matrix</Link>
        </template>

        <div class="w-full space-y-4">
            <div class="maiic-panel">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Transition matrices</h3>
                        <p class="text-xs text-gray-500">{{ matrices.total ?? matrices.data.length }} matrix run(s), newest first. A closed matrix is locked for use in the ECL.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="block"><span class="maiic-flabel">Search</span><input v-model="search" type="text" class="maiic-input !w-56" placeholder="Segment, period or comment" /></label>
                        <label class="block"><span class="maiic-flabel">From</span><input v-model="startDate" type="date" class="maiic-input !w-40" /></label>
                        <label class="block"><span class="maiic-flabel">To</span><input v-model="endDate" type="date" class="maiic-input !w-40" /></label>
                    </div>
                </div>

                <div class="maiic-table-wrap overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Matrix</th>
                                <th>Segment</th>
                                <th>Source</th>
                                <th>Periods</th>
                                <th class="num">Years</th>
                                <th class="num">Records</th>
                                <th class="num">Transition balance</th>
                                <th>Status</th>
                                <th>Last calculated</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="matrix in matrices.data" :key="matrix.id">
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-gray-900">#{{ matrix.id }}</div>
                                    <div class="text-xs text-gray-500">Profile {{ matrix.transition_profile_id }}, run {{ matrix.run_no ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="maiic-badge maiic-badge-grey">{{ matrix.pd_calculation_level ? matrix.pd_calculation_level.charAt(0).toUpperCase() + matrix.pd_calculation_level.slice(1) : '-' }}</span>
                                    <div class="mt-0.5 text-sm">
                                        <template v-if="matrix.pd_calculation_level === 'portfolio'">{{ matrix.portfolio ? matrix.portfolio.name : 'Portfolio ' + matrix.pd_calculation_id }}</template>
                                        <template v-else-if="matrix.pd_calculation_level === 'sector'">{{ matrix.sector ? matrix.sector.code + ' - ' + matrix.sector.name : 'Sector ' + matrix.pd_calculation_code }}</template>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] || matrix.calculation_source }}<div v-if="matrix.pd_start_stage_total_type" class="text-xs text-gray-500">Payments: {{ matrix.pd_start_stage_total_type }}</div></td>
                                <td class="whitespace-nowrap">{{ matrix.start_reporting_period }} to {{ matrix.end_reporting_period }}<div class="text-xs text-gray-500">{{ matrix.reporting_periods_count ?? '-' }} reporting period(s)</div></td>
                                <td class="num">{{ matrix.transition_years }}</td>
                                <td class="num whitespace-nowrap">{{ matrix.records_count_transitioned ?? '-' }}<div class="text-xs text-gray-500">{{ matrix.records_count_updated ?? 0 }} updated</div></td>
                                <td class="num whitespace-nowrap">{{ formatCurrency(matrix.transition_balance) }}<div v-if="matrix.updated_balance" class="text-xs text-gray-500">{{ formatCurrency(matrix.updated_balance) }} updated</div></td>
                                <td>
                                    <span class="maiic-badge" :class="matrix.status === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'" :title="matrix.comments || null">{{ matrix.status === 'closed' ? 'Closed' : 'Draft' }}</span>
                                </td>
                                <td class="whitespace-nowrap">{{ formatDate(matrix.last_calculation_date) }}</td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-view" title="View the matrix" @click="openModal('view', matrix)"><font-awesome-icon icon="table" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-edit" title="Edit the matrix" @click="openModal('edit', matrix)"><font-awesome-icon icon="pen" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-neutral" title="Re-run the calculation" @click="reRunMatrix(matrix.id)"><font-awesome-icon icon="calculator" /></button>
                                        <button v-if="matrix.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" :title="matrix.has_supporting_document ? 'Download the supporting document' : 'No supporting document attached yet'" @click="downloadFile(matrix.id)"><font-awesome-icon :icon="matrix.has_supporting_document ? 'check-circle' : 'file-download'" /></button>
                                        <button v-if="matrix.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" title="Attach a supporting document" @click="openUploadModal(matrix.id)"><font-awesome-icon icon="paperclip" /></button>
                                        <button type="button" class="maiic-action" :class="matrix.status === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :title="matrix.status === 'closed' ? 'Unlock this PD' : 'Lock this PD'" @click="lockPD(matrix.id)"><font-awesome-icon :icon="matrix.status === 'closed' ? 'lock-open' : 'lock'" /></button>
                                        <button v-if="matrix.status === 'closed'" type="button" class="maiic-action maiic-action-neutral" title="Update the loan book" @click="openLoanBookModal(matrix)"><font-awesome-icon icon="book" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="matrices.data.length === 0">
                                <td colspan="10" class="maiic-empty">No transition matrices yet. Use <strong>Create matrix</strong> at the top right to build the first one.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="matrices.links && matrices.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <pagination :links="matrices.links" />
                </div>
            </div>
        </div>

'''+s[b:]
s=rep(s,'<!-- \u2705 INSERT MODAL HERE -->','')
s=rep(s,'''                alert("Please select a reporting period.")''','''                await notice('Choose a reporting period', 'Pick the month whose loan book should be updated.', 'warning')''')
s=rep(s,'''                alert('Error updating loan book: ' + (error.response?.data?.message || error.message))''','''                notice('The loan book was not updated', error.response?.data?.message || error.message, 'danger')''')
s=rep(s,'''                const confirmed = confirm('Are you sure you want to re-run this calculation?')
                if (!confirmed) return''','''                if (!(await confirmDialog({ title: 'Re-run this calculation?', message: 'The draft matrix is recalculated from the loan books.', confirmLabel: 'Re-run' }))) return''')
s=rep(s,'''                alert('Matrix re-run completed successfully.')''','''                router.reload({ only: ['matrices'] })
                notice('Matrix re-run', 'The calculation finished.')''')
s=rep(s,'''                alert('Error while re-running matrix: ' + (error.response?.data?.message || error.message))''','''                notice('The re-run failed', error.response?.data?.message || error.message, 'danger')''')
s=rep(s,'''        function lockPD(id) {
            if (confirm('Are you sure you want to change the lock status of this Probability Of Default?')) {''','''        async function lockPD(id) {
            if (await confirmDialog({ title: 'Change the lock on this PD?', message: 'A closed (locked) matrix is the one the ECL uses; a draft can still be edited.', confirmLabel: 'Change lock' })) {''')
s=rep(s,'''                    onError: () => { alert('Something went wrong. Please try again.') },''','''                    onError: () => { notice('The lock was not changed', 'Please try again.', 'danger') },''')
s=rep(s,'''                alert('Please select a file first.');''','''                notice('Choose a file first', 'Pick the supporting document to attach.', 'warning');''')
s=rep(s,'''                    alert(' File attached successfully');''','')
s=rep(s,'''                    alert(' Upload failed');''','''                    notice('Upload failed', Object.values(errors || {})[0] || 'The file could not be attached.', 'danger');''')
s=rep(s,'''            if (!value) return 'E0.00';''','''            if (!value) return '0.00';''')
s=rep(s,'''        const formatDate = (date) => {
            return new Date(date).toLocaleDateString(''','''        const formatDate = (date) => {
            if (!date) return '-'
            return new Date(date).toLocaleDateString(''')
s=rep(s,"import { router } from '@inertiajs/vue3'","import { router, Link } from '@inertiajs/vue3'\nimport { confirmDialog } from '@/Components/confirmDialog'\nimport { notice } from '@/Components/Maiic/notice'")
s=rep(s,'''        AppLayout,
        JetInput,''','''        AppLayout,
        Link,
        JetInput,''')
save(p,s)
print('ok')
