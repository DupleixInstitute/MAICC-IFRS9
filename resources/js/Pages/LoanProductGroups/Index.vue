<template>
    <app-layout title="Product Groups" description="The loan product groups that loans are reported and staged under">
        <template #actions>
            <input v-model="form.search" type="text" class="maiic-input w-56" placeholder="Search product groups" aria-label="Search product groups"/>
            <Link v-if="can('loans.products.create')" :href="route('groups.create')" class="primary-btn">New product group</Link>
        </template>

        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="group in (groups?.data || [])" :key="group.id">
                        <td class="font-semibold text-gray-900">{{ group.name }}</td>
                        <td>{{ group.description || '-' }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="route('groups.edit', group.id)" :deletable="true" @delete="destroy(group)"/>
                        </td>
                    </tr>
                    <tr v-if="!(groups?.data || []).length">
                        <td class="maiic-empty" colspan="3">{{ form.search ? 'No product group matches the search.' : 'No product group yet. Use New product group (top right) to add one.' }}</td>
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
