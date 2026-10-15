import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI/ForecastWeighted')

p='Index.vue'; s=load(p)
a=s.index('      <template #header>')
b=s.index('    <div v-if="showForecastForm"')
s=s[:a]+'''      <template #header>
        <div>
          <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
            <span>IFRS 9 Model Setup</span><span>/</span><span>Forward-Looking Model</span><span>/</span><span class="font-medium text-maiic-700">Weighted Forecast</span>
          </div>
          <h2 class="text-xl font-semibold text-gray-800">Weighted Forecast</h2>
          <p class="mt-1 text-sm text-gray-600">Each macro variable's forecast weighted across the scenarios of a profile</p>
        </div>
      </template>
      <template #actions>
        <button type="button" class="primary-btn" @click="showForecastForm = true">Calculate forecast</button>
      </template>

      <div class="w-full space-y-4">
        <div class="maiic-panel">
          <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <h3 class="font-semibold text-gray-900">Weighted forecasts</h3>
              <p class="text-xs text-gray-500">{{ forecasts.total ?? forecasts.data.length }} value(s). The filters narrow this page.</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
              <label class="block"><span class="maiic-flabel">Macro variable</span>
                <select v-model="filters.macro_stat_id" class="maiic-select !w-56">
                  <option value="">All variables</option>
                  <option v-for="stat in macroVariable" :key="stat.id" :value="stat.id">{{ stat.statistic_name }}</option>
                </select>
              </label>
              <label class="block"><span class="maiic-flabel">From</span><input v-model="filters.start_period" type="month" class="maiic-input !w-40" /></label>
              <label class="block"><span class="maiic-flabel">To</span><input v-model="filters.end_period" type="month" class="maiic-input !w-40" /></label>
            </div>
          </div>
          <div class="maiic-table-wrap overflow-x-auto">
            <table class="maiic-table">
              <thead>
                <tr><th>Period</th><th>Scenario profile</th><th>Macro variable</th><th class="num">Weighted value</th><th class="text-right">Actions</th></tr>
              </thead>
              <tbody>
                <tr v-for="forecast in filteredForecasts" :key="forecast.id">
                  <td class="whitespace-nowrap">{{ forecast.reporting_period?.period ? new Date(forecast.reporting_period.period).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) : '-' }}</td>
                  <td>{{ forecast.scenario_profile?.name || '-' }}</td>
                  <td>{{ forecast.macro_statistic?.statistic_name || '-' }}</td>
                  <td class="num">{{ forecast.weighted_value }}</td>
                  <td>
                    <div class="flex justify-end gap-1.5">
                      <button type="button" class="maiic-action maiic-action-neutral" title="Re-run this forecast" @click="rerunForecast(forecast)"><font-awesome-icon icon="calculator" /></button>
                      <button type="button" class="maiic-action maiic-action-delete" title="Delete this forecast" @click="deleteForecast(forecast.id)"><font-awesome-icon icon="trash" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!filteredForecasts.length">
                  <td colspan="5" class="maiic-empty">
                    <template v-if="forecasts.data.length">No forecasts on this page match the filters.</template>
                    <template v-else>No weighted forecasts yet. Use <strong>Calculate forecast</strong> at the top right.</template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="forecasts.links && forecasts.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
            <Pagination :links="forecasts.links" />
          </div>
        </div>
      </div>

'''+s[b:]
s=rep(s,"import '@fortawesome/fontawesome-free/css/all.min.css';\n","")
s=rep(s,"  forecasts: Array,","  forecasts: Object,")
s=rep(s,'''  if(await confirmDialog({ title: 'Are you sure you want to rerun this forecast?' })){''','''  if(await confirmDialog({ title: 'Re-run this forecast?', message: 'The weighted value is recalculated from the current scenario forecasts.', confirmLabel: 'Re-run' })){''')
s=rep(s,'''  if (await confirmDialog({ title: 'Are you sure you want to delete this forecast?', confirmLabel: 'Delete', tone: 'danger' })) {''','''  if (await confirmDialog({ title: 'Delete this forecast?', message: 'The weighted forecast is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {''')
save(p,s)
print('ok')
