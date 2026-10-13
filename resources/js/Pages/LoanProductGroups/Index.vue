<template>
    <app-layout title="Product Groups" description="The loan product groups that loans are reported and staged under">
        <template #actions>
            <input v-model="form.search" type="text" class="maiic-input w-56" placeholder="Search product groups" aria-label="Search product groups"/>
            <button v-if="can('loans.products.create') && (unlisted || []).length" type="button" class="secondary-btn" @click="sync">Add the {{ unlisted.length }} from the loan book</button>
            <Link v-if="can('loans.products.create')" :href="route('groups.create')" class="primary-btn">New product group</Link>
        </template>

        <p v-if="(unlisted || []).length" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">The loan book of {{ latestPeriod }} uses {{ unlisted.length }} product group(s) not listed here: {{ unlisted.join(', ') }}.</p>
        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="num">Loans in {{ latestPeriod }}</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="group in (groups?.data || [])" :key="group.id">
                        <td class="font-semibold text-gray-900">{{ group.name }}</td>
                        <td>{{ group.description || '-' }}</td>
                        <td class="num">{{ (loansByGroup || {})[group.name] || 0 }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="route('groups.edit', group.id)" :deletable="true" @delete="destroy(group)"/>
                        </td>
                    </tr>
                    <tr v-if="!(groups?.data || []).length">
                        <td class="maiic-empty" colspan="4">{{ form.search ? 'No product group matches the search.' : 'No product group yet. Use New product group (top right) to add one.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <pagination :links="groups.links"/>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'
import debounce from 'lodash/debounce'

export default {
    components: { AppLayout, RowActions, Pagination, Link },
    props: {
        groups: Object,
        filters: Object,
        loansByGroup: Object,
        latestPeriod: String,
        unlisted: Array,
    },
    data() {
        return {
            form: { search: this.filters.search || null },
        }
    },
    watch: {
        form: {
            handler: debounce(function () {
                this.$inertia.get(this.route('groups.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 400),
            deep: true,
        },
    },
    methods: {
        sync() { this.$inertia.post(this.route('groups.sync'), {}, { preserveScroll: true }) },
        async destroy(group) {
            if (!(await confirmDialog({
                title: 'Delete the ' + group.name + ' product group?',
                message: 'The product group is removed from the list. This cannot be undone.',
                confirmLabel: 'Delete',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('groups.destroy', group.id), { preserveScroll: true })
        },
    },
}
</script>
