import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/StageingRules')

p='Triggers.vue'; s=load(p)
a=s.index('        <template #header>'); b=s.index('            <!-- Triggers History Table -->')
s=s[:a]+'''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">SICR Trigger Alerts</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">SICR Trigger Alerts</h2>
                <p class="mt-1 text-sm text-gray-600">Raise an alert when a loan shows a significant increase in credit risk, and track it until it is removed</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="primary-btn" @click="openModal">Trigger alert</button>
        </template>

        <div class="w-full space-y-4">
'''+s[b:]
s=rx(s,r'<div class="px-6 py-4 border-b border-gray-200 bg-gray-50">\s*<h3 class="text-lg font-semibold text-gray-900 flex items-center">.*?Trigger History\s*</h3>\s*</div>',
         '''<div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">Trigger history</h3>
                    <p class="text-xs text-gray-500">{{ triggers.total ?? triggers.data.length }} alert(s), newest first</p>
                </div>''')
s=rep(s,'<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">','<div class="maiic-panel">')
s=rx(s,r'<th>\s*<div class="flex items-center space-x-1">.*?<span>Timestamp</span>.*?</div>\s*</th>','<th>Raised</th>')
s=rx(s,r'<th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-900 uppercase tracking-wider bg-gray-100">.*?Actions\s*</div>\s*</th>','<th class="text-center">Actions</th>')
s=rep(s,'<td class="px-6 py-4 whitespace-nowrap bg-gray-50">','<td class="px-6 py-4 whitespace-nowrap">')
s=rx(s,r'<!-- Update Loan Book Button -->.*?<!-- Placeholder for removed alerts -->\s*<div v-if="trigger.removal_date".*?</span>\s*</div>',
     '''<button v-if="!trigger.removal_date" type="button" class="maiic-action maiic-action-edit" title="Update the loan book for this alert" @click="openUpdateModal(trigger)"><font-awesome-icon icon="book" /></button>
                                        <button v-if="!trigger.removal_date" type="button" class="maiic-action maiic-action-delete" title="Remove this alert" @click="removeAlert(trigger)"><font-awesome-icon icon="times-circle" /></button>
                                        <span v-if="trigger.removal_date" class="text-xs text-gray-500">Removed {{ formatDate(trigger.removal_date) }}</span>''')
s=rep(s,'''        removeAlert(trigger) {
            if (!confirm('Are you sure you want to remove this alert? This action cannot be undone.')) {''','''        async removeAlert(trigger) {
            if (!(await confirmDialog({ title: 'Remove this alert?', message: 'The SICR alert is closed from today. This cannot be undone.', confirmLabel: 'Remove alert', tone: 'danger' }))) {''')
m=re.search(r"import Pagination from '@/Jetstream/Pagination.vue'", s)
if m:
    s=rep(s,"import Pagination from '@/Jetstream/Pagination.vue'","import Pagination from '@/Jetstream/Pagination.vue'\nimport { confirmDialog } from '@/Components/confirmDialog'")
else:
    s=rep(s,"import AppLayout from '@/Layouts/AppLayout.vue'","import AppLayout from '@/Layouts/AppLayout.vue'\nimport { confirmDialog } from '@/Components/confirmDialog'")
save(p,s)
print('ok')
