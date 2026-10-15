import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/StageingRules')

p='Items.vue'; s=load(p)
a=s.index('        <template #header>'); b=s.index('            <!-- Items Table -->')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">SICR Alert Items</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">SICR Alert Items</h2>
                <p class="mt-1 text-sm text-gray-600">The individual risk signals inside each SICR group</p>
            </div>
        </template>
        <template #actions>
            <select v-model="filters.group_id" class="maiic-select !w-48" title="Filter by group" aria-label="Filter by group" @change="applyFilter">
                <option :value="null">All groups</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal">Add item</button>
        </template>

        <div class="w-full space-y-4">
'''+s[b:]
s=rx(s,r'<div class="px-6 py-4 border-b border-gray-200 bg-gray-50">\s*<h3 class="text-lg font-semibold text-gray-900 flex items-center">.*?</h3>\s*</div>',
         '''<div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">SICR alert items<span v-if="filters.group_id" class="font-normal text-gray-500"> in {{ groups.find(g => g.id === filters.group_id)?.name }}</span></h3>
                        <p class="text-xs text-gray-500">{{ items.total ?? items.data.length }} item(s). Click a status to switch it on or off.</p>
                    </div>
                    <button v-if="filters.group_id" type="button" class="secondary-btn" @click="clearFilters">Clear filter</button>
                </div>''')
s=rep(s,'<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">','<div class="maiic-panel">')
s=rx(s,r'<th>\s*<div class="flex items-center space-x-1">.*?<span>Group</span>.*?</div>\s*</th>','<th>Group</th>')
s=rep(s,'''<th>
                                    Item Name
                                </th>''','<th>Item name</th>')
s=rx(s,r'<div class="flex justify-end space-x-2">\s*<button\s*@click="edit\(item\)".*?Delete\s*</button>\s*</div>',
         '''<div class="flex justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit item" @click="edit(item)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete item" @click="destroy(item.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>''')
s=rep(s,"'Get started by creating your first item above.'","'Use Add item at the top right to create the first one.'")
s=rx(s,r"destroy\(id\)\s*\{\s*if \(confirm\('Are you sure you want to delete this item\?'\)\)\s*\{",
     "async destroy(id) {\n            if (await confirmDialog({ title: 'Delete this item?', message: 'The SICR alert item is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {")
s=rep(s,"import Pagination from '@/Jetstream/Pagination.vue'","import Pagination from '@/Jetstream/Pagination.vue'\nimport { confirmDialog } from '@/Components/confirmDialog'")
save(p,s)
print('ok')
