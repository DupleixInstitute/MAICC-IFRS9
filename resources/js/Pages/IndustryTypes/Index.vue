<template>
    <app-layout title="Sector Types" description="The economic sectors loans are classified under, matched to the loan book by sector code">
        <template #actions>
            <input v-model="form.search" type="text" class="maiic-input w-56" placeholder="Search code or name" aria-label="Search sector types"/>
            <Link v-if="can('industry_types.create')" :href="route('industry_types.create')" class="primary-btn">New sector type</Link>
        </template>

        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="type in types.data" :key="type.id">
                        <td class="font-mono text-xs">{{ type.code || '-' }}</td>
                        <td class="font-semibold text-gray-900">{{ type.name }}</td>
                        <td>{{ type.description || '-' }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="can('industry_types.update') ? route('industry_types.edit', type.id) : null"
                                         :deletable="can('industry_types.destroy')"
                                         @delete="destroy(type)"/>
                        </td>
                    </tr>
                    <tr v-if="types.data.length === 0">
                        <td class="maiic-empty" colspan="4">{{ form.search ? 'No sector type matches the search.' : 'No sector type yet. Use New sector type (top right) to add one.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <pagination :links="types.links"/>
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
        types: Object,
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
                this.$inertia.get(this.route('industry_types.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 400),
            deep: true,
        },
    },
    methods: {
        async destroy(type) {
            if (!(await confirmDialog({
                title: 'Delete the ' + type.name + ' sector type?',
                message: 'The sector type is removed from the list. This cannot be undone.',
                confirmLabel: 'Delete',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('industry_types.destroy', type.id), { preserveScroll: true })
        },
    },
}
</script>
