import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/LossGivenDefault')

p='Cummulative.vue'; s=load(p)
a=s.index('            <template #header>')
b=s.index('  <!-- Pagination -->')
b2=s.index('<Pagination :links="lgdCummulatives.links" />', b)+len('<Pagination :links="lgdCummulatives.links" />')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>LGD Model</span><span>/</span><span class="font-medium text-maiic-700">Cumulative LGD</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Cumulative LGD</h2>
                <p class="mt-1 text-sm text-gray-600">Monthly LGD runs combined over many windows, by portfolio or sector</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openReportModal">Get report</button>
            <Link :href="route('lgd-cummulative.create')" class="primary-btn">Calculate LGD</Link>
        </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">Cumulative LGD runs</h3>
            <p class="text-xs text-gray-500">{{ lgdCummulatives.total ?? lgdCummulatives.data.length }} run(s). A closed run is locked for use in the ECL; amounts in MWK.</p>
          </div>
          <form class="flex flex-wrap items-end gap-2" @submit.prevent="applyFilters">
            <label class="block"><span class="maiic-flabel">Level</span>
              <select v-model="filters.lgd_calculation_level" class="maiic-select !w-36">
                <option value="">All</option>
                <option value="portfolio">Portfolio</option>
                <option value="sector">Sector</option>
              </select>
            </label>
            <label class="block"><span class="maiic-flabel">From</span><input v-model="startDate" type="month" class="maiic-input !w-40" /></label>
            <label class="block"><span class="maiic-flabel">To</span><input v-model="endDate" type="month" class="maiic-input !w-40" /></label>
            <button type="submit" class="secondary-btn">Apply</button>
            <button type="button" class="secondary-btn" @click="resetFilters">Reset</button>
          </form>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr>
                <th>Window</th>
                <th>Segment</th>
                <th class="num">LGD %</th>
                <th class="num">Cure rate %</th>
                <th class="num">Recovery rate %</th>
                <th class="num">Stage 3 balance, start / end</th>
                <th>Source</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lgdC in lgdCummulatives.data" :key="lgdC.id">
                <td class="whitespace-nowrap">{{ formatDate(lgdC.start_period) }} to {{ formatDate(lgdC.reporting_period) }}</td>
                <td>
                  <span class="maiic-badge maiic-badge-grey">{{ lgdC.lgd_calculation_level ? lgdC.lgd_calculation_level.charAt(0).toUpperCase() + lgdC.lgd_calculation_level.slice(1) : '-' }}</span>
                  <div class="mt-0.5 text-sm">
                    <template v-if="lgdC.lgd_calculation_level === 'portfolio'">{{ lgdC.portfolio_group ? lgdC.portfolio_group.name : 'Portfolio ' + lgdC.lgd_calculation_id }}</template>
                    <template v-else-if="lgdC.lgd_calculation_level === 'sector'">{{ lgdC.sector ? lgdC.sector.code + ' - ' + lgdC.sector.name : 'Sector ' + lgdC.lgd_calculation_code }}</template>
                  </div>
                </td>
                <td class="num font-bold">{{ round(lgdC.lgd_cummulative * 100, 2) }}</td>
                <td class="num">{{ round(lgdC.cure_rate_cummulative * 100, 2) }}</td>
                <td class="num">{{ round(lgdC.recovery_rate_cummulative * 100, 2) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgdC.start_total_stage3) }}<div class="text-xs text-gray-500">{{ formatCurrency(lgdC.end_total_stage3) }}</div></td>
                <td class="whitespace-nowrap">{{ lgdC.calculation_source === 'manual' ? 'Manual' : 'System' }}</td>
                <td><span class="maiic-badge" :class="lgdC.is_active_or_closed === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ lgdC.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}</span></td>
                <td class="whitespace-nowrap">{{ formatDate(lgdC.created_at) }}<div v-if="lgdC.created_by" class="text-xs text-gray-500">{{ lgdC.created_by }}</div></td>
                <td>
                  <div class="flex flex-wrap justify-end gap-1.5">
                    <button v-if="lgdC.calculation_source === 'system'" type="button" class="maiic-action maiic-action-view" title="Show the periods combined" @click="showPeriods(lgdC.periods_list)"><font-awesome-icon icon="calendar" /></button>
                    <button v-if="lgdC.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" title="Attach a supporting document" @click="openUploadModal(lgdC.id)"><font-awesome-icon icon="paperclip" /></button>
                    <button v-if="lgdC.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" :title="lgdC.has_supporting_document ? 'Download the supporting document' : 'No supporting document attached yet'" @click="downloadFile(lgdC.id)"><font-awesome-icon :icon="lgdC.has_supporting_document ? 'check-circle' : 'file-download'" /></button>
                    <button type="button" class="maiic-action" :class="lgdC.is_active_or_closed === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :disabled="loading === lgdC.id" :title="lgdC.is_active_or_closed === 'closed' ? 'Unlock this LGD' : 'Lock this LGD'" @click="lockLGD(lgdC.id)"><font-awesome-icon :icon="loading === lgdC.id ? 'spinner' : (lgdC.is_active_or_closed === 'closed' ? 'lock-open' : 'lock')" :spin="loading === lgdC.id" /></button>
                    <button v-if="lgdC.is_active_or_closed === 'closed'" type="button" class="maiic-action maiic-action-neutral" :disabled="loading === lgdC.id" title="Update the loan book" @click="openUpdateModal(lgdC)"><font-awesome-icon icon="book" /></button>
                    <button v-if="lgdC.is_active_or_closed === 'active'" type="button" class="maiic-action maiic-action-delete" :disabled="loading === lgdC.id" title="Delete this LGD run" @click="deleteLGD(lgdC.id)"><font-awesome-icon icon="trash" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!lgdCummulatives.data.length">
                <td colspan="10" class="maiic-empty">No cumulative LGD runs yet. Use <strong>Calculate LGD</strong> at the top right to build one from the monthly runs.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lgdCummulatives.links && lgdCummulatives.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
          <Pagination :links="lgdCummulatives.links" />
        </div>
      </div>
    </div>
'''+s[b2:]
s=rep(s,"await confirmDialog({ title: 'Are you sure?' })","await confirmDialog({ title: 'Delete this LGD run?', message: 'The run and its figures are removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })")
s=rep(s,"await confirmDialog({ title: 'Are you sure you want to change the lock status of this LGD?' })","await confirmDialog({ title: 'Change the lock on this LGD?', message: 'A closed (locked) LGD is the one the ECL uses; an active run can still change.', confirmLabel: 'Change lock' })")
s=s.replace("notice(' Upload failed')","notice('Upload failed', 'The file could not be attached.', 'danger')")
s=s.replace("if (!value) return 'E0.00';","if (!value) return '0.00';")
save(p,s)
print('ok')
