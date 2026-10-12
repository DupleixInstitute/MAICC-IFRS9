<template>
    <app-layout title="SICR Trigger Alerts" description="Qualitative warning signs raised on loans, approved by a second person, and the stage each one moves the loan to at the month-end">
        <template #actions>
            <form class="flex items-center gap-2" @submit.prevent="applyFilter">
                <select v-model="filter.show" class="maiic-select !w-48" aria-label="Which triggers" title="Which triggers to list" @change="applyFilter">
                    <option value="current">Current and waiting</option>
                    <option value="proposed">Awaiting approval</option>
                    <option value="closed">Closed, ended or rejected</option>
                    <option value="all">All triggers</option>
                </select>
                <input v-model="filter.search" type="search" class="maiic-input !w-52" placeholder="Contract, customer or name" aria-label="Search"/>
                <button type="submit" class="secondary-btn">Search</button>
            </form>
            <button type="button" class="primary-btn" @click="openRaise">Raise a trigger</button>
        </template>

        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'In force today', value: summary.in_force },
                { label: 'Pointing to Stage 2', value: summary.stage2, accent: '#d97706' },
                { label: 'Pointing to Stage 3', value: summary.stage3, accent: '#dc2626' },
                { label: 'Awaiting approval', value: summary.proposed, accent: '#d97706' },
                { label: 'Closed or rejected', value: summary.closed, accent: '#6b7280' },
            ]"/>
            <HowSicrWorks/>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Loan</th>
                                <th>Item and stage it points to</th>
                                <th>Reason and evidence</th>
                                <th>Dates and people</th>
                                <th>Status, stage at {{ latestPeriod || 'the latest month' }}</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in triggers.data" :key="t.id" class="align-top">
                                <td class="whitespace-nowrap">
                                    <template v-if="t.scope === 'customer'">
                                        <div class="font-semibold text-gray-900">All loans of {{ t.customer_name || ('customer ' + t.customer_id) }}</div>
                                        <div class="text-xs text-gray-500">Customer {{ t.customer_id }}</div>
                                    </template>
                                    <template v-else>
                                        <div class="font-mono text-sm font-semibold text-gray-900">{{ t.account_number }}</div>
                                        <div class="max-w-[13rem] truncate text-xs text-gray-500" :title="t.customer_name">{{ t.customer_name || '-' }}</div>
                                    </template>
                                    <div class="mt-0.5 text-[11px] text-gray-400">Trigger {{ t.id }}</div>
                                </td>
                                <td class="min-w-[11rem] max-w-[14rem]">
                                    <div class="text-sm font-medium text-gray-900">{{ t.item_name }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-xs text-gray-500"><span class="maiic-badge" :class="Number(t.stage_implied) === 3 ? 'maiic-badge-red' : 'maiic-badge-gold'">Stage {{ t.stage_implied }}</span>{{ t.group_name }}</div>
                                </td>
                                <td class="min-w-[15rem] max-w-sm text-xs">
                                    <span v-if="t.is_demo" class="maiic-badge maiic-badge-grey mb-1 !text-[10px]" title="Seeded to show how the register works">Demo trigger, not a MAIIC assessment</span>
                                    <div class="text-gray-800">{{ t.reason }}</div>
                                    <div v-if="t.evidence" class="mt-0.5 text-gray-500">Evidence: {{ t.evidence }}</div>
                                    <div v-if="t.rejected_reason" class="mt-0.5 text-red-700">Rejected: {{ t.rejected_reason }}</div>
                                    <div v-if="t.closed_reason" class="mt-0.5 text-gray-600">Closed: {{ t.closed_reason }}</div>
                                </td>
                                <td class="min-w-[11rem] max-w-[14rem] text-xs">
                                    <div class="font-medium text-gray-800">From {{ date(t.effective_from) }} {{ t.effective_to ? 'to ' + date(t.effective_to) : 'until closed' }}</div>
                                    <div>Raised by {{ t.raised_by || '-' }}, {{ date(t.created_at) }}</div>
                                    <div v-if="t.approved_by_name || t.approver_label" class="text-gray-600">Approved by {{ t.approved_by_name || t.approver_label }}, {{ date(t.approved_at) }}</div>
                                    <div v-if="t.rejected_by_name" class="text-gray-600">Rejected by {{ t.rejected_by_name }}, {{ date(t.rejected_at) }}</div>
                                    <div v-if="t.closed_by_name" class="text-gray-600">Closed by {{ t.closed_by_name }}, {{ date(t.closed_at) }}</div>
                                </td>
                                <td class="min-w-[11rem] text-xs">
                                    <span class="maiic-badge" :class="statusBadge(t.display_status)">{{ t.display_status }}</span>
                                    <div v-for="l in t.loans" :key="l.contract_id" class="mt-1">
                                        <span v-if="t.scope === 'customer'" class="font-mono text-gray-500">{{ l.contract_id }}: </span>
                                        <span>Days past due: Stage {{ l.pre || '-' }}</span><br>
                                        <span v-if="l.post && String(l.post) !== String(l.pre)" class="font-semibold text-amber-800">After triggers: Stage {{ l.post }}</span>
                                        <span v-else-if="l.post">Used: Stage {{ l.post }}</span>
                                        <div class="text-gray-500">ECL {{ money(l.ecl) }}</div>
                                    </div>
                                    <div v-if="!t.loans || !t.loans.length" class="mt-1 text-gray-400">Not in the {{ latestPeriod }} book</div>
                                </td>
                                <td class="num whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5">
                                        <button v-if="t.status === 'PROPOSED'" type="button" class="maiic-action maiic-action-view" :disabled="Number(t.triggered_by) === userId" :title="Number(t.triggered_by) === userId ? 'You raised it: a second person approves' : 'Approve (second person)'" @click="approve(t)"><font-awesome-icon icon="check" /></button>
                                        <button v-if="t.status === 'PROPOSED'" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="reject(t)"><font-awesome-icon icon="times-circle" /></button>
                                        <button v-if="t.status === 'APPROVED'" type="button" class="maiic-action maiic-action-edit" title="Close: the condition has passed" @click="openClose(t)"><font-awesome-icon icon="calendar" /></button>
                                        <span v-if="t.status !== 'PROPOSED' && t.status !== 'APPROVED'" class="text-xs text-gray-400">-</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="triggers.data.length === 0">
                                <td colspan="6" class="maiic-empty">
                                    {{ filter.search ? 'No trigger matches the search.' : 'No triggers in this list.' }}
                                    Use <strong>Raise a trigger</strong> at the top right when a borrower shows a warning sign from the SICR item list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="triggers.links" class="px-4 pb-4"><Pagination :links="triggers.links"/></div>
            </div>
        </div>

        <!-- Raise a trigger -->
        <jet-modal :show="showRaise" @close="showRaise = false" max-width="3xl">
            <form class="p-6" @submit.prevent="raise">
                <h3 class="text-lg font-semibold text-gray-900">Raise a SICR trigger</h3>
                <p class="mt-1 text-sm text-gray-500">A second person approves it. It changes the loan's stage when the month is next staged, and the ECL when that is re-run.</p>
                <div v-if="errors.trigger" class="mt-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    <font-awesome-icon icon="exclamation-circle" class="mt-0.5"/><span>{{ errors.trigger }}</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="md:col-span-2"><span class="maiic-flabel">Warning sign (item)</span>
                        <select v-model="form.item_id" class="maiic-select w-full" required>
                            <option :value="null" disabled>Choose an item</option>
                            <optgroup v-for="(list, group) in itemsByGroup" :key="group" :label="group">
                                <option v-for="i in list" :key="i.id" :value="i.id">{{ i.name }} (Stage {{ i.stage_implied }})</option>
                            </optgroup>
                        </select>
                        <span v-if="errors.item_id" class="mt-1 block text-xs text-red-600">{{ errors.item_id }}</span>
                        <span v-if="chosenItem" class="mt-1 block text-xs text-gray-500">{{ chosenItem.description }} <span class="font-semibold" :class="Number(chosenItem.stage_implied) === 3 ? 'text-red-700' : 'text-amber-700'">Moves the loan to Stage {{ chosenItem.stage_implied }}.</span></span>
                    </label>
                    <div class="md:col-span-2 flex gap-6 text-sm">
                        <label class="inline-flex items-center gap-2"><input v-model="form.scope" type="radio" value="contract" class="text-maiic-600"/> One loan</label>
                        <label class="inline-flex items-center gap-2"><input v-model="form.scope" type="radio" value="customer" class="text-maiic-600"/> Every loan of a customer</label>
                    </div>
                    <label v-if="form.scope === 'contract'" class="md:col-span-2"><span class="maiic-flabel">Loan ({{ latestPeriod }} book)</span>
                        <input v-model="form.contract_id" list="sicr-loans" class="maiic-input w-full" required placeholder="Type a contract number or customer name"/>
                        <datalist id="sicr-loans"><option v-for="l in loans" :key="l.contract_id" :value="l.contract_id">{{ l.customer_name }} (Stage {{ l.stage || '-' }})</option></datalist>
                        <span v-if="errors.contract_id" class="mt-1 block text-xs text-red-600">{{ errors.contract_id }}</span></label>
                    <label v-else class="md:col-span-2"><span class="maiic-flabel">Customer</span>
                        <select v-model="form.customer_id" class="maiic-select w-full" required>
                            <option :value="null" disabled>Choose a customer</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.loans }} loan{{ c.loans === 1 ? '' : 's' }})</option>
                        </select>
                        <span v-if="errors.customer_id" class="mt-1 block text-xs text-red-600">{{ errors.customer_id }}</span></label>
                    <label><span class="maiic-flabel">Applies from</span>
                        <input v-model="form.effective_from" type="date" class="maiic-input w-full" required/>
                        <span v-if="errors.effective_from" class="mt-1 block text-xs text-red-600">{{ errors.effective_from }}</span></label>
                    <label><span class="maiic-flabel">Applies until (optional)</span>
                        <input v-model="form.effective_to" type="date" class="maiic-input w-full"/>
                        <span class="mt-1 block text-xs text-gray-500">Leave blank to keep it until it is closed.</span>
                        <span v-if="errors.effective_to" class="mt-1 block text-xs text-red-600">{{ errors.effective_to }}</span></label>
                    <label class="md:col-span-2"><span class="maiic-flabel">Reason: what happened to the borrower</span>
                        <textarea v-model="form.reason" rows="2" class="maiic-input w-full" required></textarea>
                        <span v-if="errors.reason" class="mt-1 block text-xs text-red-600">{{ errors.reason }}</span></label>
                    <label class="md:col-span-2"><span class="maiic-flabel">Evidence: the document, its date and where it is filed{{ chosenItem && chosenItem.evidence_required ? '' : ' (optional)' }}</span>
                        <textarea v-model="form.evidence" rows="2" class="maiic-input w-full" :required="!!(chosenItem && chosenItem.evidence_required)"></textarea>
                        <span v-if="errors.evidence" class="mt-1 block text-xs text-red-600">{{ errors.evidence }}</span></label>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="showRaise = false">Cancel</button>
                    <button type="submit" class="primary-btn" :disabled="processing || !form.item_id">{{ processing ? 'Saving...' : 'Raise trigger' }}</button>
                </div>
            </form>
        </jet-modal>

        <!-- Close a trigger -->
        <jet-modal :show="closing !== null" @close="closing = null" max-width="lg">
            <form v-if="closing" class="p-6" @submit.prevent="close">
                <h3 class="text-lg font-semibold text-gray-900">Close trigger {{ closing.id }}?</h3>
                <p class="mt-1 text-sm text-gray-500">The condition has passed. Month-ends after the closing date no longer see the trigger; the loan then stays at its stage for the governed cure period before it moves down. Months already covered keep it.</p>
                <div class="mt-4 grid gap-4">
                    <label><span class="maiic-flabel">Closed on</span><input v-model="closeForm.closed_on" type="date" class="maiic-input w-full" :min="closing.effective_from" required/></label>
                    <label><span class="maiic-flabel">What changed</span><textarea v-model="closeForm.reason" rows="2" class="maiic-input w-full" required></textarea></label>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" @click="closing = null">Cancel</button>
                    <button type="submit" class="primary-btn" :disabled="!closeForm.reason">Close trigger</button>
                </div>
            </form>
        </jet-modal>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import HowSicrWorks from './Partials/HowSicrWorks.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason'

const today = () => new Date().toISOString().slice(0, 10)
const blank = () => ({ item_id: null, scope: 'contract', contract_id: '', customer_id: null, effective_from: today(), effective_to: '', reason: '', evidence: '' })

export default {
    props: { triggers: Object, filters: Object, latestPeriod: String, summary: Object, items: Array, loans: Array, userId: Number },
    components: { AppLayout, JetModal, Pagination, KpiRow, HowSicrWorks },
    data() {
        return {
            filter: { show: this.filters?.show || 'current', search: this.filters?.search || '' },
            showRaise: false, processing: false, form: blank(),
            closing: null, closeForm: { closed_on: today(), reason: '' },
        }
    },
    computed: {
        errors() { return this.$page.props.errors || {} },
        itemsByGroup() {
            const out = {}
            for (const i of this.items) (out[i.group_name] = out[i.group_name] || []).push(i)
            return out
        },
        chosenItem() { return this.items.find(i => Number(i.id) === Number(this.form.item_id)) || null },
        customers() {
            const by = {}
            for (const l of this.loans) {
                const k = String(l.customer_id)
                if (!by[k]) by[k] = { id: k, name: l.customer_name || ('Customer ' + k), loans: 0 }
                by[k].loans++
            }
            return Object.values(by).sort((a, b) => a.name.localeCompare(b.name))
        },
    },
    methods: {
        date(v) { return v ? String(v).slice(0, 10) : '-' },
        money(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
        statusBadge(s) {
            return { 'In force': 'maiic-badge-solid-green', 'Awaiting approval': 'maiic-badge-gold', 'Approved, not started': 'maiic-badge-green', Rejected: 'maiic-badge-red' }[s] || 'maiic-badge-grey'
        },
        applyFilter() {
            const q = { show: this.filter.show }
            if (this.filter.search) q.search = this.filter.search
            this.$inertia.get(this.route('sicr-triggers.index'), q, { preserveState: true, replace: true })
        },
        openRaise() { this.form = blank(); this.showRaise = true },
        raise() {
            this.processing = true
            const payload = { ...this.form }
            if (payload.scope === 'contract') payload.customer_id = null; else payload.contract_id = null
            this.$inertia.post(this.route('sicr-triggers.store'), payload, {
                preserveScroll: true,
                onFinish: () => { this.processing = false },
                onSuccess: () => { if (!Object.keys(this.$page.props.errors || {}).length) this.showRaise = false },
            })
        },
        async approve(t) {
            if (!(await confirmDialog({ title: `Approve trigger ${t.id}?`, message: `It moves ${t.scope === 'customer' ? 'every loan of ' + (t.customer_name || t.customer_id) : 'loan ' + t.account_number} to at least Stage ${t.stage_implied} from ${this.date(t.effective_from)}, once the month is staged again.`, confirmLabel: 'Approve' }))) return
            this.$inertia.post(this.route('sicr-triggers.approve', t.id), {}, { preserveScroll: true })
        },
        async reject(t) {
            const reason = await promptReason({ title: `Reject trigger ${t.id}?`, message: 'The reason is written to the register and the audit log.', label: 'Why is it rejected?', confirmLabel: 'Reject', tone: 'danger' })
            if (reason) this.$inertia.post(this.route('sicr-triggers.reject', t.id), { reason }, { preserveScroll: true })
        },
        openClose(t) { this.closing = t; this.closeForm = { closed_on: today(), reason: '' } },
        close() {
            const id = this.closing.id
            this.$inertia.post(this.route('sicr-triggers.close', id), this.closeForm, { preserveScroll: true, onSuccess: () => { this.closing = null } })
        },
    },
}
</script>
