<template>
    <app-layout title="SICR Groups" description="The families of qualitative warning signs used to judge a significant increase in credit risk, and how many items in each point to Stage 2 or Stage 3">
        <template #actions>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal()">Add group</button>
        </template>

        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'Groups', value: totals.groups },
                { label: 'Items', value: totals.items },
                { label: 'Items pointing to Stage 2', value: totals.stage2, accent: '#d97706', hint: 'Significant increase in credit risk' },
                { label: 'Items pointing to Stage 3', value: totals.stage3, accent: '#dc2626', hint: 'Evidence the loan is credit-impaired' },
            ]"/>
            <HowSicrWorks/>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th class="num w-12">Order</th>
                                <th>Group</th>
                                <th>What it covers</th>
                                <th class="num">Items</th>
                                <th class="num">Stage 2</th>
                                <th class="num">Stage 3</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="group in groups.data" :key="group.id">
                                <td class="num text-gray-500">{{ group.sort_order || '-' }}</td>
                                <td class="font-semibold text-gray-900">{{ group.name }}</td>
                                <td class="text-sm text-gray-600">{{ group.description || '-' }}</td>
                                <td class="num">{{ group.items_count }}<span v-if="group.items_count !== group.active_count" class="text-xs text-gray-400"> ({{ group.items_count - group.active_count }} off)</span></td>
                                <td class="num"><span v-if="group.stage2_count" class="maiic-badge maiic-badge-gold">{{ group.stage2_count }}</span><span v-else class="text-gray-400">0</span></td>
                                <td class="num"><span v-if="group.stage3_count" class="maiic-badge maiic-badge-red">{{ group.stage3_count }}</span><span v-else class="text-gray-400">0</span></td>
                                <td class="num whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5">
                                        <Link :href="route('sicr-items.index', { group_id: group.id })" class="maiic-action maiic-action-view" title="See the items in this group"><font-awesome-icon icon="eye" /></Link>
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit group" @click="openModal(group)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" :title="group.items_count ? 'A group with items cannot be deleted' : 'Delete group'" :disabled="group.items_count > 0" @click="destroy(group)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="groups.data.length === 0">
                                <td colspan="7" class="maiic-empty">No SICR groups yet. Use <strong>Add group</strong> at the top right, or import a CSV.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="groups.links" class="px-4 pb-4"><Pagination :links="groups.links"/></div>
            </div>
        </div>

        <!-- Add / edit -->
        <jet-modal :show="showModal" @close="closeModal" max-width="2xl">
            <form class="p-6" @submit.prevent="save">
                <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit SICR group' : 'Add a SICR group' }}</h3>
                <p class="mt-1 text-sm text-gray-500">A group is a family of warning signs, for example forbearance or the borrower's financial condition.</p>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <label class="md:col-span-3"><span class="maiic-flabel">Group name</span>
                        <input v-model="form.name" type="text" class="maiic-input w-full" required maxlength="199"/>
                        <span v-if="errors.name" class="mt-1 block text-xs text-red-600">{{ errors.name }}</span></label>
                    <label><span class="maiic-flabel">Order on screen</span>
                        <input v-model.number="form.sort_order" type="number" min="0" max="999" class="maiic-input w-full"/></label>
                    <label class="md:col-span-4"><span class="maiic-flabel">What it covers</span>
                        <textarea v-model="form.description" rows="3" class="maiic-input w-full"></textarea>
                        <span v-if="errors.description" class="mt-1 block text-xs text-red-600">{{ errors.description }}</span></label>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="closeModal">Cancel</button>
                    <button type="submit" class="primary-btn" :disabled="processing || !form.name">{{ processing ? 'Saving...' : (editingId ? 'Save changes' : 'Add group') }}</button>
                </div>
            </form>
        </jet-modal>

        <!-- Bulk import: how to, sample CSV, upload -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="5xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Import SICR groups from a CSV file</h3>
                <p class="mt-1 text-sm text-gray-500">Add many groups at once. A group that already exists, by name, is left as it is.</p>
                <ImportSpecPanel class="mt-4" spec-key="sicr-groups" :file="csvFile"/>
                <div class="mt-4">
                    <FileDrop :key="importKey" :error="importError" :disabled="processing" @file="onFile"/>
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
import ImportSpecPanel from '@/Components/Imports/ImportSpecPanel.vue'
import Pagination from '@/Components/Pagination.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import HowSicrWorks from './Partials/HowSicrWorks.vue'
import { Link } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

const blank = () => ({ name: '', description: '', sort_order: 0 })

export default {
    props: { groups: Object, totals: Object },
    components: { AppLayout, JetModal, FileDrop, ImportSpecPanel, Pagination, KpiRow, HowSicrWorks, Link },
    data() {
        return { form: blank(), processing: false, editingId: null, csvFile: null, showModal: false, showImportModal: false, importError: '', importKey: 0 }
    },
    computed: {
        errors() { return this.$page.props.errors || {} },
    },
    methods: {
        openModal(group = null) {
            this.editingId = group ? group.id : null
            this.form = group ? { name: group.name, description: group.description || '', sort_order: group.sort_order || 0 } : blank()
            this.showModal = true
        },
        closeModal() { this.showModal = false; this.editingId = null; this.form = blank() },
        openImportModal() { this.showImportModal = true; this.csvFile = null; this.importError = ''; this.importKey++ },
        closeImportModal() { this.showImportModal = false; this.csvFile = null },
        save() {
            this.processing = true
            const url = this.editingId ? this.route('sicr-groups.update', this.editingId) : this.route('sicr-groups.store')
            this.$inertia[this.editingId ? 'put' : 'post'](url, this.form, {
                preserveScroll: true,
                onFinish: () => { this.processing = false },
                onSuccess: () => this.closeModal(),
            })
        },
        async destroy(group) {
            if (group.items_count > 0) return
            if (!(await confirmDialog({ title: 'Delete this group?', message: `The group "${group.name}" is removed. This cannot be undone.`, confirmLabel: 'Delete', tone: 'danger' }))) return
            this.$inertia.delete(this.route('sicr-groups.destroy', group.id), { preserveScroll: true })
        },
        onFile(file) { this.csvFile = file; this.importError = '' },
        uploadCsv() {
            if (!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-groups.import'), data, {
                onFinish: () => { this.processing = false },
                onSuccess: () => { const c = this.$page.props.flash?.import_check; if (!(c && c.key === 'sicr-groups' && !c.ok)) this.closeImportModal() },
                onError: (errors) => { this.importError = Object.values(errors || {})[0] || 'The file could not be imported. Check it against the sample CSV.' },
            })
        },
    },
}
</script>
