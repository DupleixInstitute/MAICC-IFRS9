<template>
    <app-layout title="Loan Portfolios" description="The portfolios a loan book is imported into and the ECL is calculated for">
        <template #actions>
            <input v-model="form.search" type="text" class="maiic-input w-56" placeholder="Search portfolios" aria-label="Search portfolios"/>
            <select v-model="form.status" class="maiic-select w-36" aria-label="Status">
                <option :value="null">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <Link :href="route('portfolios.create')" class="primary-btn">New portfolio</Link>
        </template>

        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="portfolio in portfolios.data" :key="portfolio.id">
                        <td class="font-semibold text-gray-900">{{ portfolio.name }}</td>
                        <td>{{ portfolio.description || '-' }}</td>
                        <td><span class="maiic-badge" :class="portfolio.active ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ portfolio.active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ portfolio.created_by?.name || '-' }}</td>
                        <td class="whitespace-nowrap">{{ portfolio.created_at ? $filters.time(portfolio.created_at) : '' }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="route('portfolios.edit', portfolio.id)" :deletable="true" @delete="destroy(portfolio)"/>
                        </td>
                    </tr>
                    <tr v-if="portfolios.data.length === 0">
                        <td class="maiic-empty" colspan="6">{{ form.search || form.status ? 'No portfolio matches these filters.' : 'No portfolio yet. Use New portfolio (top right) to add the first one.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <pagination :links="portfolios.links"/>
        <HelpManual/>
    </app-layout>
</template>

<script>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
import Pagination from '@/Components/Pagination.vue'
import HelpManual from '@/Components/HelpManual.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'
import debounce from 'lodash/debounce'

export default {
    components: { AppLayout, RowActions, Link, Pagination, HelpManual },
    props: {
        filters: Object,
        portfolios: Object,
    },
    setup(props) {
        const form = ref({
            search: props.filters.search || null,
            status: props.filters.status || null,
        })

        const performSearch = debounce(() => {
            router.get(route('portfolios.index'), pickBy(form.value), { preserveState: true, preserveScroll: true, replace: true })
        }, 300)

        watch(form, performSearch, { deep: true })

        async function destroy(portfolio) {
            if (!(await confirmDialog({
                title: 'Delete the ' + portfolio.name + ' portfolio?',
                message: 'A portfolio that holds loan books cannot be deleted; mark it inactive instead.',
                confirmLabel: 'Delete',
                tone: 'danger',
            }))) return
            router.delete(route('portfolios.destroy', portfolio.id), { preserveScroll: true })
        }

        return { form, destroy }
    },
}
</script>
