import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/StageingRules')

p='Groups.vue'; s=load(p)
a=s.index('        <template #header>'); b=s.index('            <!-- Groups Table -->')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">SICR Groups</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">SICR Groups</h2>
                <p class="mt-1 text-sm text-gray-600">Groups that hold the alert items used to judge a significant increase in credit risk</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal">Add group</button>
        </template>

        <div class="w-full space-y-4">
'''+s[b:]
s=rx(s,r'<div class="px-6 py-4 border-b border-gray-200 bg-gray-50">\s*<h3 class="text-lg font-semibold text-gray-900 flex items-center">.*?Existing SICR Groups\s*</h3>\s*</div>',
         '''<div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">SICR groups</h3>
                    <p class="text-xs text-gray-500">{{ groups.total ?? groups.data.length }} group(s)</p>
                </div>''')
s=rep(s,'<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">','<div class="maiic-panel">')
s=rx(s,r'<th>\s*<div class="flex items-center space-x-1">\s*<span>Group Name</span>.*?</div>\s*</th>','<th>Group name</th>')
s=rx(s,r'<div class="flex justify-end space-x-2">\s*<button\s*@click="edit\(group\)".*?Delete\s*</button>\s*</div>',
         '''<div class="flex justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit group" @click="edit(group)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete group" @click="destroy(group.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>''')
s=rep(s,'<p class="text-sm text-gray-500">Get started by creating your first group above.</p>','<p class="text-sm text-gray-500">Use <strong>Add group</strong> at the top right to create the first one.</p>')
s=rep(s,'''        destroy(id) {
            if (confirm('Are you sure you want to delete this group?')) {''','''        async destroy(id) {
            if (await confirmDialog({ title: 'Delete this group?', message: 'The SICR group is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {''')
s=rep(s,"import Pagination from '@/Jetstream/Pagination.vue'","import Pagination from '@/Jetstream/Pagination.vue'\nimport { confirmDialog } from '@/Components/confirmDialog'")
save(p,s)
print('ok')
