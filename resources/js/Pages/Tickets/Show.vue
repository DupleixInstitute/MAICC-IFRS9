<template>
    <app-layout>
        <template #header>
            <div class="flex flex-wrap items-center gap-2">
                <inertia-link :href="route('tickets.index')" class="text-gray-400 hover:text-gray-600">Support Tickets</inertia-link>
                <span class="text-gray-300">/</span>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    <span class="font-mono text-maiic-700">{{ ticket.reference_display }}</span>
                    <span class="text-gray-800"> — {{ ticket.title }}</span>
                </h2>
            </div>
        </template>

        <div class="mx-auto grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- MAIN -->
            <div class="space-y-6 lg:col-span-2">
                <!-- description -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">Description</h3>
                    <p v-if="ticket.description" class="whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ ticket.description }}</p>
                    <p v-else class="text-sm italic text-gray-400">No description provided.</p>

                    <div v-if="ticket.resolution" class="mt-5 rounded-md bg-maiic-50 p-4">
                        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-maiic-700">Resolution</h4>
                        <p class="whitespace-pre-line text-sm text-maiic-900">{{ ticket.resolution }}</p>
                    </div>
                </div>

                <!-- activity timeline -->
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-5 text-sm font-semibold uppercase tracking-wide text-gray-400">Activity</h3>
                    <ol class="relative border-l border-gray-200">
                        <li v-for="u in ticket.updates" :key="u.id" class="mb-6 ml-6 last:mb-0">
                            <span :class="['absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full ring-4 ring-white', u.is_system ? 'bg-gray-300' : 'bg-maiic-500']"></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-800">{{ u.user ? u.user.name : 'System' }}</span>
                                <span v-if="u.old_status || u.new_status" class="inline-flex items-center gap-1 text-xs text-gray-500">
                                    <span v-if="u.old_status" :class="['rounded px-1.5 py-0.5', statusClass(u.old_status)]">{{ statusLabel(u.old_status) }}</span>
                                    <span v-if="u.old_status">→</span>
                                    <span v-if="u.new_status" :class="['rounded px-1.5 py-0.5', statusClass(u.new_status)]">{{ statusLabel(u.new_status) }}</span>
                                </span>
                                <span class="text-xs text-gray-400">{{ formatDateTime(u.created_at) }}</span>
                            </div>
                            <p v-if="u.body" class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ u.body }}</p>
                        </li>
                        <li v-if="ticket.updates.length === 0" class="ml-6 text-sm text-gray-400">No activity yet.</li>
                    </ol>
                </div>

                <!-- add update -->
                <div v-if="can('tickets.update')" class="rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400">Add an update</h3>
                    <form @submit.prevent="submitUpdate" class="space-y-3">
                        <textarea v-model="updateForm.body" rows="3" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500" placeholder="Progress note, comment or resolution detail…"></textarea>
                        <div v-if="updateForm.errors.body" class="text-sm text-red-600">{{ updateForm.errors.body }}</div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <span>Change status to</span>
                                <select v-model="updateForm.new_status" class="rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                    <option :value="null">— keep {{ statusLabel(ticket.status) }} —</option>
                                    <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </label>
                            <button type="submit" :disabled="updateForm.processing" :class="{ 'opacity-50': updateForm.processing }" class="rounded-md bg-maiic-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-800">Post update</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SIDEBAR: details -->
            <div class="space-y-4">
                <div class="rounded-lg bg-white p-6 shadow">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-400">Details</h3>
                        <button v-if="can('tickets.update')" @click="editing = !editing" class="text-sm font-medium text-maiic-700 hover:text-maiic-800">
                            {{ editing ? 'Cancel' : 'Edit' }}
                        </button>
                    </div>

                    <!-- read-only -->
                    <dl v-if="!editing" class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd><span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass(ticket.status)]">{{ ticket.status_label }}</span></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Category</dt><dd class="font-medium text-gray-800">{{ ticket.category_label }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Priority</dt><dd><span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', priorityClass(ticket.priority)]">{{ ticket.priority_label }}</span></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Assignee</dt><dd class="font-medium text-gray-800">{{ ticket.assignee ? ticket.assignee.name : '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Requested by</dt><dd class="font-medium text-gray-800">{{ ticket.requested_by || '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Source</dt><dd class="font-medium text-gray-800">{{ ticket.source || '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Due</dt><dd class="font-medium text-gray-800">{{ ticket.due_date ? formatDate(ticket.due_date) : '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Created</dt><dd class="text-gray-600">{{ formatDate(ticket.created_at) }}</dd></div>
                        <div v-if="ticket.resolved_at" class="flex justify-between gap-4"><dt class="text-gray-500">Resolved</dt><dd class="text-gray-600">{{ formatDate(ticket.resolved_at) }}</dd></div>
                    </dl>

                    <!-- edit form -->
                    <form v-else @submit.prevent="submitDetails" class="space-y-3 text-sm">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Title</label>
                            <input v-model="editForm.title" type="text" required class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Description</label>
                            <textarea v-model="editForm.description" rows="3" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"></textarea>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                            <select v-model="editForm.status" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Category</label>
                                <select v-model="editForm.category" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Priority</label>
                                <select v-model="editForm.priority" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                    <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Assignee</label>
                            <select v-model="editForm.assigned_to" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <option :value="null">— Unassigned —</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Requested by</label>
                                <input v-model="editForm.requested_by" type="text" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-600">Source</label>
                                <input v-model="editForm.source" type="text" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Due date</label>
                            <input v-model="editForm.due_date" type="date" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Resolution</label>
                            <textarea v-model="editForm.resolution" rows="2" class="block w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"></textarea>
                        </div>
                        <button type="submit" :disabled="editForm.processing" :class="{ 'opacity-50': editForm.processing }" class="w-full rounded-md bg-maiic-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-800">Save changes</button>
                    </form>
                </div>

                <button v-if="can('tickets.destroy')" @click="confirmingDeletion = true" class="w-full rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                    Delete ticket
                </button>
            </div>
        </div>

        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>Delete {{ ticket.reference_display }}</template>
            <template #content>Are you sure you want to permanently delete this ticket and its activity trail?</template>
            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">Nevermind</jet-secondary-button>
                <jet-danger-button class="ml-2" @click.native="destroy">Delete Ticket</jet-danger-button>
            </template>
        </jet-confirmation-modal>

        <teleport to="head"><title>{{ ticket.reference_display }} — {{ ticket.title }}</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'

export default {
    components: { AppLayout, JetConfirmationModal, JetDangerButton, JetSecondaryButton },
    props: {
        ticket: Object,
        statuses: Object,
        categories: Object,
        priorities: Object,
        users: Array,
    },
    data() {
        return {
            editing: false,
            confirmingDeletion: false,
            editForm: this.$inertia.form({
                title: this.ticket.title,
                description: this.ticket.description,
                category: this.ticket.category,
                priority: this.ticket.priority,
                status: this.ticket.status,
                requested_by: this.ticket.requested_by,
                source: this.ticket.source,
                assigned_to: this.ticket.assigned_to,
                resolution: this.ticket.resolution,
                due_date: this.ticket.due_date ? this.ticket.due_date.substring(0, 10) : null,
            }),
            updateForm: this.$inertia.form({
                body: '',
                new_status: null,
            }),
        }
    },
    methods: {
        statusLabel(key) { return this.statuses[key] || key },
        submitDetails() {
            this.editForm.put(this.route('tickets.update', this.ticket.id), {
                onSuccess: () => { this.editing = false },
            })
        },
        submitUpdate() {
            this.updateForm.post(this.route('tickets.updates.store', this.ticket.id), {
                onSuccess: () => this.updateForm.reset(),
            })
        },
        destroy() {
            this.$inertia.delete(this.route('tickets.destroy', this.ticket.id))
            this.confirmingDeletion = false
        },
        formatDate(value) {
            if (!value) return '—'
            return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        },
        formatDateTime(value) {
            if (!value) return ''
            return new Date(value).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        statusClass(status) {
            return {
                open: 'bg-blue-100 text-blue-700',
                in_progress: 'bg-amber-100 text-amber-700',
                on_hold: 'bg-slate-100 text-slate-600',
                resolved: 'bg-maiic-100 text-maiic-700',
                closed: 'bg-gray-100 text-gray-500',
            }[status] || 'bg-gray-100 text-gray-600'
        },
        priorityClass(priority) {
            return {
                low: 'bg-gray-100 text-gray-600',
                medium: 'bg-blue-100 text-blue-700',
                high: 'bg-amber-100 text-amber-700',
                critical: 'bg-red-100 text-red-700',
            }[priority] || 'bg-gray-100 text-gray-600'
        },
    },
}
</script>
