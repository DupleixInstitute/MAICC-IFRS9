import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI/CreditLossData')

p='Index.vue'; s=load(p)
a=s.index('    <template #header>')
b=s.index('        <!-- Portfolios -->')
s=s[:a]+'''    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>IFRS 9 Model Setup</span><span>/</span><span>Forward-Looking Model</span><span>/</span><span class="font-medium text-maiic-700">Credit Loss Data</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">Credit Loss Data</h2>
        <p class="mt-1 text-sm text-gray-600">The historical loss measures (default rates, losses and similar) the forward-looking model is fitted to</p>
      </div>
    </template>
    <template #actions>
      <Link :href="route('credit-loss-data.importView')" class="secondary-btn">Import CSV</Link>
      <Link :href="route('credit-loss-data.create')" class="primary-btn">Add record</Link>
    </template>

    <div class="w-full space-y-4">
        <KpiRow :cards="[
            { label: 'Records', value: Number(totalRecords || 0) },
            { label: 'Portfolios', value: portfolios.length },
            { label: 'Periods', value: uniquePeriods.length },
            { label: 'Metrics', value: definitions.length },
        ]" />

        <div class="maiic-filterbar !mb-0">
          <div class="flex flex-wrap items-end gap-2">
            <label class="block"><span class="maiic-flabel">Period</span><input v-model="form.period" type="month" class="maiic-input !w-44" /></label>
            <label class="block"><span class="maiic-flabel">Portfolio</span>
              <select v-model="form.portfolio_id" class="maiic-select !w-56">
                <option value="">All portfolios</option>
                <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
              </select>
            </label>
            <label class="block"><span class="maiic-flabel">Metric</span>
              <select v-model="form.definition_id" class="maiic-select !w-56">
                <option value="">All metrics</option>
                <option v-for="definition in definitions" :key="definition.id" :value="definition.id">{{ definition.name }}</option>
              </select>
            </label>
            <button type="button" class="primary-btn" @click="applyFilters">Apply</button>
            <button type="button" class="secondary-btn" @click="resetFilters">Reset</button>
          </div>
        </div>

'''+s[b:]
s=rep(s,'''<div v-for="portfolio in portfolios" :key="portfolio.id" class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">{{ portfolio.name }}</h3>
                <span class="text-sm text-gray-500">''','''<div v-for="portfolio in portfolios" :key="portfolio.id" class="maiic-panel">
            <div class="px-5 py-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-900">{{ portfolio.name }}</h3>
                <span class="text-xs text-gray-500">''')
s=rx(s,r'<thead>\s*<tr>\s*<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>.*?</tr>\s*</thead>','''<thead>
                        <tr><th>Period</th><th>Metric</th><th class="num">Value</th><th>Source</th><th class="text-right">Actions</th></tr>
                    </thead>''')
s=rep(s,'''<div class="flex space-x-2">
                                    <button @click="editRecord(record)" class="text-maiic-600 hover:text-maiic-900"><i class="fas fa-pen"></i></button>
                                    <button @click="deleteRecord(record)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                </div>''','''<div class="flex justify-end gap-1.5">
                                    <button type="button" class="maiic-action maiic-action-edit" title="Edit record" @click="editRecord(record)"><font-awesome-icon icon="pen" /></button>
                                    <button type="button" class="maiic-action maiic-action-delete" title="Delete record" @click="deleteRecord(record)"><font-awesome-icon icon="trash" /></button>
                                </div>''')
s=rx(s,r'<!-- Pagination -->\s*<div class="mt-4 flex flex-wrap justify-center">.*?</template>\s*</div>','''<div v-if="portfolioData[portfolio.id].links && portfolioData[portfolio.id].links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <Pagination :links="portfolioData[portfolio.id].links" />
                </div>''')
s=rep(s,'''            <div v-else class="p-6 text-center text-gray-500">
                No data available for this portfolio
            </div>''','''            <div v-else class="maiic-empty">
                No credit loss data for this portfolio yet. Use <strong>Import CSV</strong> or <strong>Add record</strong> at the top right.
            </div>''')
s=rep(s,'''    </div>
</div>


  </app-layout>''','''    </div>
  </app-layout>''')
s=rep(s,"import '@fortawesome/fontawesome-free/css/all.css';\n","import KpiRow from '@/Components/Maiic/KpiRow.vue'\n")
s=rep(s,"components: { AppLayout, Link },","components: { AppLayout, Link, KpiRow },")
s=s.replace("await confirmDialog({ title: 'Are you sure you want to delete this record?', confirmLabel: 'Delete', tone: 'danger' })","await confirmDialog({ title: 'Delete this record?', message: 'The credit loss figure is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })")
save(p,s)
print('ok')
