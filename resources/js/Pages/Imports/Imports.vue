<template>
    <app-layout title="Imports" description="Every file uploaded (loan book, clients, collateral, credit loss data and EIR data), with its progress, record counts and the rows that failed">
        <template #actions>
            <Link :href="route('loan_applications.loan-book.import.create')" class="primary-btn" title="Upload a monthly loan book file">Import loan book</Link>
            <Link :href="route('clients.import.create')" class="secondary-btn" title="Upload a client file">Import clients</Link>
            <Link :href="route('collateral.register.import')" class="secondary-btn" title="Upload a collateral register file">Import collateral</Link>
        </template>

        <div class="maiic-filterbar">
            <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2">
                    <label class="maiic-flabel" for="imp-search">File name</label>
                    <input id="imp-search" v-model="form.search" type="text" class="maiic-input" placeholder="Search by file name">
                </div>
                <div>
                    <label class="maiic-flabel" for="imp-status">Status</label>
                    <select id="imp-status" v-model="form.status" class="maiic-select">
                        <option :value="null">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="secondary-btn" @click="reset">Clear</button>
                </div>
            </div>
        </div>

        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>File name</th>
                        <th>Status</th>
                        <th>Uploaded</th>
                        <th class="num">Records inserted</th>
                        <th class="num">Failed rows</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th class="num">Duration</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="result in results.data" :key="result.id">
                        <td class="min-w-[16rem] font-semibold text-gray-900">{{ result.name }}</td>
                        <td><span class="maiic-badge capitalize" :class="statusBadge(result.status)">{{ result.status }}</span></td>
                        <td class="whitespace-nowrap">{{ result.created_at ? $filters.time(result.created_at) : '' }}</td>
                        <td class="num">{{ formatCount(result.records) }}</td>
                        <td class="num"><span :class="result.failed_records > 0 ? 'font-semibold text-red-600' : ''">{{ formatCount(result.failed_records) }}</span></td>
                        <td class="whitespace-nowrap">{{ result.started_at ? $filters.time(result.started_at) : '' }}</td>
                        <td class="whitespace-nowrap">{{ result.completed_at ? $filters.time(result.completed_at) : '' }}</td>
                        <td class="num">{{ result.started_at && result.completed_at ? calculateDuration(result.started_at, result.completed_at) : '' }}</td>
                        <td class="text-right">
                            <a v-if="result.failed_records > 0 && result.failed_file_path"
                               :href="route('imports.failed-download', result.id)" target="_blank"
                               class="maiic-action maiic-action-neutral" title="Download the failed rows">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                            </a>
                        </td>
                    </tr>
                    <tr v-if="results.data.length === 0">
                        <td class="maiic-empty" colspan="9">{{ form.search || form.status ? 'No import matches these filters.' : 'No file has been imported yet. Use Import loan book (top right) to upload the first one.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <pagination :links="results.links"/>
        <HelpManual />
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import HelpManual from '@/Components/HelpManual.vue'
import { Link } from '@inertiajs/vue3'
import pickBy from 'lodash/pickBy'
import debounce from 'lodash/debounce'

export default {
    components: { AppLayout, Pagination, HelpManual, Link },
    props: {
        results: Object,
        filters: Object,
    },
    data() {
        return {
            form: {
                search: this.filters.search || null,
                status: this.filters.status || null,
            },
        }
    },
    watch: {
        form: {
            handler: debounce(function () {
                this.$inertia.get(this.route('imports.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 400),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = { search: null, status: null }
        },
        statusBadge(status) {
            return { completed: 'maiic-badge-green', processing: 'maiic-badge-gold', pending: 'maiic-badge-grey', failed: 'maiic-badge-red' }[status] || 'maiic-badge-grey'
        },
        formatCount(n) {
            return n === null || n === undefined ? '' : Number(n).toLocaleString()
        },
        calculateDuration(start, end) {
            const totalSeconds = Math.max(0, Math.floor((new Date(end) - new Date(start)) / 1000))
            const hours = Math.floor(totalSeconds / 3600)
            const minutes = Math.floor((totalSeconds % 3600) / 60)
            const seconds = totalSeconds % 60
            return ((hours ? hours + 'h ' : '') + (hours || minutes ? minutes + 'm ' : '') + seconds + 's').trim()
        },
    },
}
</script>
