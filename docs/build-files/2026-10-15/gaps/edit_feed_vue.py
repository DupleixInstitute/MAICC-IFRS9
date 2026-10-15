import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')
p = 'resources/js/Pages/Eir/Feed/Index.vue'
s = open(p, encoding='utf-8').read()
reps = [
("""              <p class="text-sm text-gray-500">The loan book rows built for each period, by method. A flag names a difference from the source, it never hides one. Lock a period once its month-end is signed off: nothing then restates it.</p>
            </div>""",
"""              <p class="text-sm text-gray-500">The loan book rows built for each period, by method. A flag names a difference from the source, it never hides one. Lock a period once its month-end is signed off: nothing then restates it. Unlocking is requested by one person and approved by another.</p>
            </div>
            <!-- unlocks requested by one person, awaiting a second (maker-checker) -->
            <div v-if="unlockRequests.length" class="border-b border-amber-200 bg-amber-50 px-5 py-3">
              <div class="text-sm font-semibold text-amber-800">Unlocks awaiting approval ({{ unlockRequests.length }})</div>
              <ul class="mt-2 divide-y divide-amber-200">
                <li v-for="r in unlockRequests" :key="r.id" class="flex flex-wrap items-start justify-between gap-3 py-2 text-sm">
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900">{{ r.period }}</div>
                    <div class="text-gray-700">{{ r.reason }}</div>
                    <div class="text-xs text-gray-500">Requested by {{ r.requested_by_name || '-' }}, {{ r.requested_at }}</div>
                  </div>
                  <div v-if="canGovern" class="flex items-center gap-1.5">
                    <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-view" :title="'Approve and unlock ' + r.period" @click="approveUnlock(r)"><font-awesome-icon icon="check"/></button>
                    <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeUnlock(r, 'reject')"><font-awesome-icon icon="times"/></button>
                    <button v-else type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeUnlock(r, 'withdraw')"><font-awesome-icon icon="ban"/></button>
                  </div>
                </li>
              </ul>
            </div>"""),
("""                    <td v-if="canGovern" class="text-right">
                      <button type="button" class="maiic-action maiic-action-neutral" :title="(locks.includes(p.reporting_period) ? 'Unlock ' : 'Lock ') + p.reporting_period" @click="openLock(p.reporting_period)">""",
"""                    <td v-if="canGovern" class="text-right">
                      <span v-if="unlockRequested(p.reporting_period)" class="text-xs text-amber-700">Unlock requested</span>
                      <button v-else type="button" class="maiic-action maiic-action-neutral" :title="(locks.includes(p.reporting_period) ? 'Request the unlock of ' : 'Lock ') + p.reporting_period" @click="openLock(p.reporting_period)">"""),
("""        <h3 class="text-lg font-semibold text-gray-900">{{ lockForm.unlock ? 'Unlock' : 'Lock' }} {{ lockForm.period }}</h3>""",
 """        <h3 class="text-lg font-semibold text-gray-900">{{ lockForm.unlock ? 'Request the unlock of' : 'Lock' }} {{ lockForm.period }}</h3>"""),
("""        <p class="mt-2 text-sm text-gray-600" v-else>Unlocking lets the period be restated. Give the approval for the restatement as the reason.</p>""",
 """        <p class="mt-2 text-sm text-gray-600" v-else>Unlocking lets the period be restated, so it is not one person's act: this records the request with its reason, and the period stays locked until a different person with EIR governance approves it. Give the approval for the restatement as the reason.</p>"""),
("""{{ locking ? 'Saving...' : (lockForm.unlock ? 'Unlock the period' : 'Lock the period') }}""",
 """{{ locking ? 'Saving...' : (lockForm.unlock ? 'Request the unlock' : 'Lock the period') }}"""),
("""locks: Array, lastLedgerDate: String, canGovern: Boolean, me: Number },""",
 """locks: Array, unlockRequests: { type: Array, default: () => [] }, lastLedgerDate: String, canGovern: Boolean, me: Number },"""),
("""    async approve(id) {""",
 """    unlockRequested(period) { return this.unlockRequests.some(r => r.period === period) },
    async approveUnlock(r) {
      if (!(await confirmDialog({ title: 'Unlock ' + r.period + '?', message: 'Requested by ' + (r.requested_by_name || 'another user') + ': ' + r.reason + '. Once unlocked, every engine may restate the period.', confirmLabel: 'Approve and unlock', tone: 'danger' }))) return
      router.post(route('eir-feed.unlock.approve', r.id), {}, { preserveScroll: true })
    },
    async closeUnlock(r, action) {
      const words = { reject: 'Reject', withdraw: 'Withdraw' }
      const reason = await promptReason({ title: words[action] + ' the unlock of ' + r.period, message: 'Give the reason; it is kept with the request and in the audit trail.', confirmLabel: words[action] })
      if (!reason) return
      router.post(route('eir-feed.unlock.close', r.id), { action, reason }, { preserveScroll: true })
    },
    async approve(id) {"""),
("""import { confirmDialog } from '@/Components/confirmDialog'""",
 """import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'"""),
]
for a, b in reps:
    assert a in s, a[:70]
    s = s.replace(a, b, 1)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
