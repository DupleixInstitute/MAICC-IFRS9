<template>
    <app-layout title="SICR Alert Items" description="The warning signs a credit officer can raise on a loan, and the stage each one moves the loan to">
        <template #actions>
            <select v-model="filter.group_id" class="maiic-select !w-56" title="Filter by group" aria-label="Filter by group" @change="applyFilter">
                <option :value="null">All groups</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <select v-model="filter.stage" class="maiic-select !w-48" title="Filter by the stage implied" aria-label="Filter by stage" @change="applyFilter">
                <option :value="null">Stage 2 and Stage 3</option>
                <option value="2">Stage 2 items only</option>
                <option value="3">Stage 3 items only</option>
            </select>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal()">Add item</button>
        </template>

        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'Items', value: counts.all },
                { label: 'Stage 2: significant increase', value: counts.stage2, accent: '#d97706' },
                { label: 'Stage 3: credit-impaired', value: counts.stage3, accent: '#dc2626' },
                { label: 'Switched off', value: counts.inactive, accent: '#6b7280', hint: 'No new trigger can be raised on an item that is off' },
            ]"/>
            <HowSicrWorks/>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Item and what it means</th>
                                <th>Moves the loan to</th>
                                <th>Evidence</th>
                                <th>IFRS 9 basis</th>
                                <th>Status</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items.data" :key="item.id" class="align-top">
                                <td class="whitespace-nowrap text-sm text-gray-600">{{ item.group?.name }}</td>
                                <td class="min-w-[26rem] max-w-2xl">
                                    <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                    <div v-if="item.description" class="mt-0.5 text-xs text-gray-500">{{ item.description }}</div>
                                </td>
                                <td class="whitespace-nowrap"><span class="maiic-badge" :class="Number(item.stage_implied) === 3 ? 'maiic-badge-red' : 'maiic-badge-gold'">{{ stageLabel(item.stage_implied) }}</span></td>
                                <td class="whitespace-nowrap text-xs">{{ item.evidence_required ? 'Required' : 'Not required' }}</td>
                                <td class="whitespace-nowrap text-xs text-gray-600">{{ item.standard_reference || '-' }}</td>
                                <td class="whitespace-nowrap">
                                    <button type="button" class="maiic-badge" :class="item.active ? 'maiic-badge-green' : 'maiic-badge-grey'" :title="item.active ? 'Switch off: no new triggers on this item' : 'Switch on'" @click="toggle(item)">{{ item.active ? 'On' : 'Off' }}</button>
                                </td>
                                <td class="num whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit item" @click="openModal(item)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete item" @click="destroy(item)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="7" class="maiic-empty">{{ filter.group_id || filter.stage ? 'No items match the filter.' : 'No SICR items yet. Use Add item at the top right, or import a CSV.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="items.links" class="px-4 pb-4"><Pagination :links="items.links"/></div>
            </div>
        </div>

        <!-- Add / edit -->
        <jet-modal :show="showModal" @close="closeModal" max-width="2xl">
            <form class="p-6" @submit.prevent="save">
                <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit SICR item' : 'Add a SICR item' }}</h3>
                <p class="mt-1 text-sm text-gray-500">A warning sign a credit officer can raise on a loan. A changed stage applies to triggers raised from now on; triggers already raised keep the stage they were approved with.</p>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label><span class="maiic-flabel">Group</span>
                        <select v-model="form.group_id" class="maiic-select w-full" required>
                            <option :value="null" disabled>Choose a group</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                        <span v-if="errors.group_id" class="mt-1 block text-xs text-red-600">{{ errors.group_id }}</span></label>
                    <label><span class="maiic-flabel">Moves the loan to</span>
                        <select v-model.number="form.stage_implied" class="maiic-select w-full" required>
                            <option :value="2">Stage 2: significant increase in credit risk</option>
                            <option :value="3">Stage 3: credit-impaired</option>
                        </select>
                        <span v-if="errors.stage_implied" class="mt-1 block text-xs text-red-600">{{ errors.stage_implied }}</span></label>
                    <label class="md:col-span-2"><span class="maiic-flabel">Item name</span>
                        <input v-model="form.name" type="text" class="maiic-input w-full" required maxlength="199"/>
                        <span v-if="errors.name" class="mt-1 block text-xs text-red-600">{{ errors.name }}</span></label>
                    <label class="md:col-span-2"><span class="maiic-flabel">What it means, in plain words</span>
                        <textarea v-model="form.description" rows="3" class="maiic-input w-full"></textarea>
                        <span v-if="errors.description" class="mt-1 block text-xs text-red-600">{{ errors.description }}</span></label>
                    <label><span class="maiic-flabel">IFRS 9 basis (optional)</span>
                        <input v-model="form.standard_reference" type="text" class="maiic-input w-full" maxlength="120" placeholder="e.g. IFRS 9 B5.5.17(g)"/></label>
                    <div class="flex flex-col justify-end gap-2 pb-1 text-sm">
                        <label class="inline-flex items-center gap-2"><input v-model="form.evidence_required" type="checkbox" class="rounded border-gray-300 text-maiic-600"/> Evidence must be recorded when it is raised</label>
                        <label class="inline-flex items-center gap-2"><input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-maiic-600"/> On (can be raised)</label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="closeModal">Cancel</button>
                    <button type="submit" class="primary-btn" :disabled="processing || !form.name || !form.group_id">{{ processing ? 'Saving...' : (editingId ? 'Save changes' : 'Add item') }}</button>
                </div>
            </form>
        </jet-modal>

        <!-- Bulk import: how to, sample CSV, upload -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Import SICR items from a CSV file</h3>
                <p class="mt-1 text-sm text-gray-500">Add or update many items at once.</p>
                <div class="mt-5 grid gap-5 md:grid-cols-5">
                    <div class="md:col-span-3">
                        <FileDrop :key="importKey" :error="importError" :disabled="processing" @file="onFile"/>
                    </div>
                    <ImportHowTo class="md:col-span-2" title="How to import" :sample-url="route('import-samples.show', 'sicr-items')">
                        <li>Download the sample CSV. group, name and active are required; stage, description, evidence_required and standard_reference are optional.</li>
                        <li>group is the SICR group name; a group that does not exist yet is created.</li>
                        <li>stage is 2 (significant increase) or 3 (credit-impaired); left blank it is 2.</li>
                        <li>active and evidence_required are true or false (1 / 0 and yes / no also work).</li>
                        <li>An item with the same group and name is updated, not duplicated. Rows without a group or name are skipped.</li>
                    </ImportHowTo>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="primary-btn" :disabled="processing || !csvFile" @click="uploadCsv">{{ processing ? 'Importing...' : 'Start import' }}</button>
                </div>
            </div>
        </jet-modal>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import FileDrop from '@/Components/Data/FileDrop.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import Pagination from '@/Components/Pagination.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import HowSicrWorks from './Partials/HowSicrWorks.vue'
import { confirmDialog } from '@/Components/confirmDialog'

const blank = (groupId = null) => ({ group_id: groupId, name: '', description: '', stage_implied: 2, evidence_required: true, standard_reference: '', active: true })

export default {
    props: { groups: Array, items: Object, filters: Object, counts: Object },
    components: { AppLayout, JetModal, FileDrop, ImportHowTo, Pagination, KpiRow, HowSicrWorks },
    data() {
        return {
            form: blank(), processing: false, editingId: null, csvFile: null, showModal: false, showImportModal: false, importError: '', importKey: 0,
            filter: { group_id: this.filters?.group_id ? Number(this.filters.group_id) : null, stage: this.filters?.stage || null },
        }
    },
    computed: {
        errors() { return this.$page.props.errors || {} },
    },
    methods: {
        stageLabel(s) { return Number(s) === 3 ? 'Stage 3, credit-impaired' : 'Stage 2, SICR' },
        applyFilter() {
            const q = {}
            if (this.filter.group_id) q.group_id = this.filter.group_id
            if (this.filter.stage) q.stage = this.filter.stage
            this.$inertia.get(this.route('sicr-items.index'), q, { preserveState: true, replace: true })
        },
        openModal(item = null) {
            this.editingId = item ? item.id : null
            this.form = item
                ? { group_id: item.group_id, name: item.name, description: item.description || '', stage_implied: item.stage_implied || 2, evidence_required: !!item.evidence_required, standard_reference: item.standard_reference || '', active: !!item.active }
                : blank(this.filter.group_id)
            this.showModal = true
        },
        closeModal() { this.showModal = false; this.editingId = null; this.form = blank() },
        openImportModal() { this.showImportModal = true; this.csvFile = null; this.importError = ''; this.importKey++ },
        closeImportModal() { this.showImportModal = false; this.csvFile = null },
        save() {
            this.processing = true
            const url = this.editingId ? this.route('sicr-items.update', this.editingId) : this.route('sicr-items.store')
            this.$inertia[this.editingId ? 'put' : 'post'](url, this.form, {
                preserveScroll: true,
                onFinish: () => { this.processing = false },
                onSuccess: () => this.closeModal(),
            })
        },
        toggle(item) { this.$inertia.post(this.route('sicr-items.toggle', item.id), {}, { preserveScroll: true }) },
        async destroy(item) {
            if (!(await confirmDialog({ title: 'Delete this item?', message: `"${item.name}" is removed from the list. An item with triggers raised on it cannot be deleted; switch it off instead.`, confirmLabel: 'Delete', tone: 'danger' }))) return
            this.$inertia.delete(this.route('sicr-items.destroy', item.id), { preserveScroll: true })
        },
        onFile(file) { this.csvFile = file; this.importError = '' },
        uploadCsv() {
            if (!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-items.import'), data, {
                onFinish: () => { this.processing = false },
                onSuccess: () => this.closeImportModal(),
                onError: (errors) => { this.importError = Object.values(errors || {})[0] || 'The file could not be imported. Check it against the sample CSV.' },
            })
        },
    },
}
</script>
