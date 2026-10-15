import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/TransitionMatrix')

p='Cummulative.vue'; s=load(p)
a=s.index('        <template #header>')
b=s.index('        <div \n            v-if="periodsModalVisible"') if '        <div \n            v-if="periodsModalVisible"' in s else s.index('v-if="periodsModalVisible"')
# walk back to the start of that <div
b=s.rfind('<div', 0, b)
b=s.rfind('\n', 0, b)+1
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>PD Model</span><span>/</span><span class="font-medium text-maiic-700">Cumulative Probability</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Cumulative Probability</h2>
                <p class="mt-1 text-sm text-gray-600">Monthly matrices combined over many periods into the cumulative probability of default</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="showReportModal = true">Get report</button>
            <Link :href="route('transition-matrix-cummulative.create')" class="primary-btn">Create matrix</Link>
        </template>

        <div class="w-full space-y-4">
            <div class="maiic-panel">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Cumulative matrices</h3>
                        <p class="text-xs text-gray-500">{{ cumMatrix.total ?? (cumMatrix.data || []).length }} matrix run(s). A closed matrix is locked for use in the ECL.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="block"><span class="maiic-flabel">Search</span><input v-model="search" type="text" class="maiic-input !w-56" placeholder="Segment or period" /></label>
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
                                <th class="num">Records</th>
                                <th class="num">Transition balance</th>
                                <th>Last period</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="matrix in cumMatrix.data" :key="matrix.id">
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
                                <td class="whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] || matrix.calculation_source }}</td>
                                <td class="whitespace-nowrap">{{ formatDate(matrix.start_period) }} to {{ formatDate(matrix.end_period) }}<div class="text-xs text-gray-500">{{ matrix.periods_count ?? '-' }} transition period(s)</div></td>
                                <td class="num">{{ matrix.records_counted ?? '-' }}</td>
                                <td class="num whitespace-nowrap">{{ formatAmount(matrix.transition_balance_cummulated) }}</td>
                                <td class="whitespace-nowrap">{{ formatDate(matrix.last_reporting_period) }}</td>
                                <td><span class="maiic-badge" :class="matrix.status === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ matrix.status === 'closed' ? 'Closed' : 'Draft' }}</span></td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-view" title="View the matrix" @click="openModal('view', matrix)"><font-awesome-icon icon="table" /></button>
                                        <button type="button" class="maiic-action maiic-action-neutral" title="Show the periods combined" @click="showPeriods(matrix.periods_list)"><font-awesome-icon icon="calendar" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-edit" title="Edit the matrix" @click="openModal('edit', matrix)"><font-awesome-icon icon="pen" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-neutral" title="Re-run the calculation" @click="reRunMatrix(matrix.id)"><font-awesome-icon icon="calculator" /></button>
                                        <button type="button" class="maiic-action" :class="matrix.status === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :title="matrix.status === 'closed' ? 'Unlock this PD' : 'Lock this PD'" @click="lockPD(matrix.id)"><font-awesome-icon :icon="matrix.status === 'closed' ? 'lock-open' : 'lock'" /></button>
                                        <button v-if="matrix.status === 'closed'" type="button" class="maiic-action maiic-action-neutral" title="Update the loan book" @click="openLoanBookModal(matrix)"><font-awesome-icon icon="book" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!(cumMatrix.data || []).length">
                                <td colspan="9" class="maiic-empty">No cumulative matrices yet. Use <strong>Create matrix</strong> at the top right to build one from the monthly matrices.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="cumMatrix.links && cumMatrix.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <pagination :links="cumMatrix.links" />
                </div>
            </div>
        </div>

'''+s[b:]
s=rep(s,"            alert('Could not parse periods JSON.')","            notice('Periods not readable', 'The list of periods for this matrix could not be read.', 'warning')")
s=rep(s,"            alert('Periods data is not an array.')","            notice('No periods recorded', 'This matrix has no list of combined periods.', 'warning')")
s=rep(s,'''                    alert("Please select a reporting period.");''','''                    await notice('Choose a reporting period', 'Pick the month whose loan book should be updated.', 'warning');''')
s=rep(s,'''                    alert('Error updating loan book: ' + (error.response?.data?.message || error.message));''','''                    notice('The loan book was not updated', error.response?.data?.message || error.message, 'danger');''')
s=rep(s,'''                const confirmed = confirm('Are you sure you want to re-run this calculation?');
                if (!confirmed) return;''','''                if (!(await confirmDialog({ title: 'Re-run this calculation?', message: 'The draft matrix is recalculated from the monthly matrices.', confirmLabel: 'Re-run' }))) return;''')
s=rep(s,'''                alert('Matrix re-run completed successfully.');''','''                router.reload({ only: ['cumMatrix'] });
                notice('Matrix re-run', 'The calculation finished.');''')
s=rep(s,'''                alert('Error while re-running matrix: ' + (error.response?.data?.message || error.message));''','''                notice('The re-run failed', error.response?.data?.message || error.message, 'danger');''')
s=rep(s,'''        const lockPD = (matrixId) => {
            if (confirm('Are you sure you want to change the lock status?')) {''','''        const lockPD = async (matrixId) => {
            if (await confirmDialog({ title: 'Change the lock on this PD?', message: 'A closed (locked) matrix is the one the ECL uses; a draft can still be edited.', confirmLabel: 'Change lock' })) {''')
s=rep(s,'''                        onSuccess: () => { router.reload({ only: ['matrices'] }) },''','''                        onSuccess: () => { router.reload({ only: ['cumMatrix'] }) },''')
s=rep(s,'''                alert('Please select a file first.');''','''                notice('Choose a file first', 'Pick the supporting document to attach.', 'warning');''')
s=rep(s,'''                    alert(' File attached successfully');
                    showUploadModal.value = false;
                    router.reload({ only: ['matrices'] });''','''                    showUploadModal.value = false;
                    router.reload({ only: ['cumMatrix'] });''')
s=rep(s,'''                    alert(' Upload failed');''','''                    notice('Upload failed', Object.values(errors || {})[0] || 'The file could not be attached.', 'danger');''')
s=rep(s,'''                window.location.href = `'/transition-matrix-cumulative/${id}/download-file'`;''','''                window.location.href = `/transition-matrix-cumulative/${id}/download-file`;''')
s=rep(s,'''        const formatDate = (date) => {
            return new Date(date).toLocaleDateString(''','''        const formatAmount = (value) => {
            if (value === null || value === undefined || value === '') return '-'
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
        }

        const formatDate = (date) => {
            if (!date) return '-'
            return new Date(date).toLocaleDateString(''')
s=rep(s,"import { Link, router } from '@inertiajs/vue3'","import { Link, router } from '@inertiajs/vue3'\nimport { confirmDialog } from '@/Components/confirmDialog'\nimport { notice } from '@/Components/Maiic/notice'")
# expose formatAmount from setup
m=re.search(r'return \{\s*\n(\s*)', s[s.index('setup('):])
idx=s.index('setup(')+m.end()
s=s[:idx]+'formatAmount,\n'+m.group(1)+s[idx:]
save(p,s)
print('ok')
