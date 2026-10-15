p='resources/js/Pages/Collateral/Import.vue'
s=open(p,encoding='utf-8').read()
a=s.index('<script>')
tpl='''<template>
  <app-layout title="Import Collateral Register" description="Upload the collateral register for a month-end: one row per item of collateral with its values">
    <template #actions>
      <a :href="route('collateral.register.sample')" class="secondary-btn" title="A CSV with the exact column headers the standard collateral import reads">Download sample CSV</a>
      <Link :href="route('collateral.register.index')" class="secondary-btn">Back to the register</Link>
    </template>

    <div class="space-y-5">
      <form @submit.prevent="submit" class="maiic-panel grid gap-6 p-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="maiic-flabel" for="period">Period</label>
              <input id="period" type="month" v-model="form.period" required class="maiic-input">
              <p v-if="form.errors.period" class="mt-1 text-xs text-red-600">{{ form.errors.period }}</p>
              <p v-else class="mt-1 text-xs text-gray-500">The month-end the register is for.</p>
            </div>
            <div>
              <span class="maiic-flabel">File layout</span>
              <div class="flex gap-2">
                <label v-for="opt in layouts" :key="opt.key" class="flex flex-1 cursor-pointer items-center gap-2 rounded-lg border-2 px-3 py-2 text-sm transition"
                       :class="importType === opt.key ? 'border-maiic-500 bg-maiic-50 font-semibold text-maiic-800' : 'border-gray-200 text-gray-700 hover:border-gray-300'" :title="opt.help">
                  <input type="radio" v-model="importType" :value="opt.key" class="h-4 w-4 border-gray-300 text-maiic-600"/> {{ opt.label }}
                </label>
              </div>
            </div>
          </div>

          <div>
            <span class="maiic-flabel">File</span>
            <label class="flex h-28 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition hover:border-maiic-300 hover:bg-maiic-50">
              <svg class="h-8 w-8 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
              <span class="mt-2 text-sm font-semibold text-gray-600">{{ fileName || 'Choose a CSV file' }}</span>
              <input type="file" class="hidden" accept=".csv,.txt" @change="handleFileSelect"/>
            </label>
            <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
          </div>

          <div v-if="importType === 'custom' && headers.length > 0" class="space-y-3">
            <div class="maiic-section-title !mt-0">Match the columns of the file</div>
            <div class="maiic-table-wrap rounded-lg border border-gray-200">
              <table class="maiic-table">
                <thead><tr><th v-for="(header, index) in headers" :key="index">{{ header }}</th></tr></thead>
                <tbody>
                  <tr v-for="(row, rowIndex) in sampleData.slice(1, 4)" :key="rowIndex">
                    <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="max-w-xs truncate text-xs">{{ cell }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
              <div v-for="(header, index) in headers" :key="index" class="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2">
                <span class="w-40 truncate text-sm font-semibold text-gray-700" :title="header">{{ header }}</span>
                <select v-model="mapping[header]" class="maiic-select flex-1">
                  <option value="">Ignore this column</option>
                  <option v-for="field in availableFields" :key="field" :value="field">{{ field }}</option>
                </select>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
            <Link :href="route('collateral.register.index')" class="secondary-btn">Cancel</Link>
            <button type="submit" class="primary-btn" :disabled="form.processing || !selectedFile">{{ form.processing ? 'Uploading...' : 'Start import' }}</button>
          </div>
        </div>

        <aside class="rounded-lg border border-maiic-100 bg-maiic-50/60 p-4 text-sm text-gray-700">
          <div class="mb-2 font-semibold text-maiic-800">How to import the register</div>
          <ol class="list-decimal space-y-1.5 pl-5">
            <li>Choose the month-end the register is for.</li>
            <li>For the standard layout, download the sample CSV (top right) and keep its column headers. To use another file as it is, pick Match columns.</li>
            <li>collateral_type must be a code listed on the Collateral Types tab. Dates may be day/month/year or year-month-day.</li>
            <li>A row without a customer_id or a known collateral type is set aside in the failed rows file.</li>
            <li>After the import, run the allocation on the Collateral Allocation tab.</li>
          </ol>
        </aside>
      </form>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 text-sm font-bold text-gray-900">Recent imports</div>
        <ImportHistoryTable :imports="recentImports"/>
      </div>
    </div>
  </app-layout>
</template>

'''
s=tpl+s[a:]
old="""import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, Link },
"""
assert old in s
s=s.replace(old,"""import ImportHistoryTable from '@/Components/Data/ImportHistoryTable.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, Link, ImportHistoryTable },

    props: {
        recentImports: { type: Array, default: () => [] },
    },
""")
old="""            importType: 'legacy',
            form:"""
assert old in s
s=s.replace(old,"""            importType: 'legacy',
            layouts: [
                { key: 'legacy', label: 'Standard CSV', help: 'The columns of the sample CSV.' },
                { key: 'custom', label: 'Match columns', help: 'Match each column of your file to a register field.' },
            ],
            form:""")
a=s.index('        downloadTemplate(type) {')
b=s.index('    }\n}\n</script>')
s=s[:a].rstrip().rstrip(',')+'\n'+s[b:]
old="""            if (newVal === 'legacy') {
                this.headers = []; // Clear UI mapping for legacy type
                this.form.mapping = {};
            }"""
assert old in s
s=s.replace(old,"""            if (this.headers.length) this.setupMapping();""")
old="""            const formData = new FormData()
            formData.append('file', this.selectedFile)
            formData.append('period', this.form.period)
            formData.append('import_type', this.importType)
            formData.append('mapping', JSON.stringify(this.mapping))

"""
assert old in s
s=s.replace(old,"")
old="""            })).post('/collateral/register/import', {
                forceFormData: true,
                onSuccess: () => {
                    // optional success handling
                },
            });"""
assert old in s
s=s.replace(old,"""            })).post(route('collateral.register.import.store'), { forceFormData: true });""")
open(p,'w',encoding='utf-8').write(s)
