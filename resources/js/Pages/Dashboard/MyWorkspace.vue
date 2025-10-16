<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    My Workspace
                </h2>
            </div>
        </template>

        <div class="mx-auto mt-4">
            <div class="tabs">
                <ul class="flex justify-center">
                    <li @click="activeTab = 'inbox'" :class="{ 'active': activeTab === 'inbox' }"
                        class="cursor-pointer px-4 py-2 text-gray-700 relative">
                        Reminders <span class="badge">{{ inboxCount }}</span>
                    </li>
                    <li @click="activeTab = 'assigned'" :class="{ 'active': activeTab === 'assigned' }"
                        class="cursor-pointer px-4 py-2 text-gray-700 relative">
                        Assigned To Me <span class="badge">{{ assignedCount }}</span>
                    </li>
                    <li @click="activeTab = 'approved'" :class="{ 'active': activeTab === 'approved' }"
                        class="cursor-pointer px-4 py-2 text-gray-700 relative">
                        Approved By Me <span class="badge">{{ approvedCount }}</span>
                    </li>
                    <li @click="activeTab = 'pending'" :class="{ 'active': activeTab === 'pending' }"
                        class="cursor-pointer px-4 py-2 text-gray-700 relative">
                        Pending To Me <span class="badge">{{ pendingCount }}</span>
                    </li>
                </ul>
            </div>

            <div class="tab-content mt-4">
                <div v-if="activeTab === 'inbox'">
                    <h3 class="text-2xl font-semibold mb-4 flex items-center justify-center">Reminders</h3>
                    <div v-if="inboxCount === 0" class="flex flex-col items-center justify-center h-64">
                        <svg class="w-24 h-24 text-gray-400 animate-bounce mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 15a4 4 0 014-4h1a4 4 0 018 0h1a4 4 0 014 4v2H3v-2zM7 10V7a3 3 0 013-3 3 3 0 013 3v3m6 10a3 3 0 01-6 0">
                            </path>
                        </svg>
                        <p class="text-lg text-gray-600">Nothing to show yet!</p>
                    </div>
                    <div v-else>
                        <div class="bg-white rounded shadow overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Loan ID</th>


                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Time</th>
                                        <!-- action button -->
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">

                                    <tr v-for="reminder in myReminders.data" :key="reminder.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ reminder.loan_application_id }}</td>


                                        <td class="px-6 py-4 whitespace-nowrap">{{ reminder.description }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ reminder.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{
                                            formatReminderDate(reminder.reminder_at) }}
                                        </td>

                                        <inertia-link
                                            :href="route('loan_applications.show', reminder.loan_application_id)"
                                            tabindex="-1" class="text-green-600 hover:text-green-900" title="View">
                                            View Application
                                            <font-awesome-icon icon="search" />
                                        </inertia-link>

                                    </tr>
                                </tbody>
                            </table>

                        </div>
                        <pagination :links="myReminders.links" />


                    </div>
                </div>
                <div v-if="activeTab === 'assigned'">
                    <h3 class="text-2xl font-semibold mb-4 flex items-center justify-center">Assigned</h3>
                    <div v-if="assignedCount === 0" class="flex flex-col items-center justify-center h-64">
                        <svg class="w-24 h-24 text-gray-400 animate-bounce mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 15a4 4 0 014-4h1a4 4 0 018 0h1a4 4 0 014 4v2H3v-2zM7 10V7a3 3 0 013-3 3 3 0 013 3v3m6 10a3 3 0 01-6 0">
                            </path>
                        </svg>
                        <p class="text-lg text-gray-600">Nothing to show yet!</p>
                    </div>
                    <div v-else>
                        <div class="bg-white rounded shadow overflow-x-auto">
                            <table class="w-full whitespace-no-wrap table-auto">
                                <thead class="bg-gray-50">
                                    <tr class="text-left font-bold">


                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Client</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Product</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Branch</th>

                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!assignedToMeApplications.data.length">
                                        <td colspan="7" class="px-6 py-4 text-center">
                                            No applications yet
                                        </td>
                                    </tr>
                                    <tr v-for="application in assignedToMeApplications.data" :key="application.id"
                                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center"
                                                :href="application.id ? route('loan_applications.show', application.id) : '#'"
                                                tabindex="-1">
                                                {{ application.id || 'N/A' }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                                :href="route('clients.show', application.client_id)"
                                                v-if="application.client">
                                                <img v-if="application.client.profile_photo_url"
                                                    class="block w-5 h-5 rounded-full mr-2 -my-2"
                                                    :src="application.client.profile_photo_url">
                                                {{ application.client.name }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.product">
                                                {{ application.product.name }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ $filters.formatNumber(application.amount) }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{
                                                    $filters.formatNumber(application.score)
                                                }} ({{ $filters.formatNumber(application.score_percentage) }}%)
                                            </span>
                                        </td>
                                        <td class="border-t px-6 py-4">
                                            <div v-if="application.current_linked_stage">
                                                <span v-if="application.current_linked_stage.stage">{{
                                                    application.current_linked_stage.stage.name
                                                }} -</span>

                                                <span v-if="application.current_linked_stage.status == 'pending'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'returned'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    returned
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'in_progress'"
                                                    class="px-2 rounded-full bg-blue-100 text-blue-800">
                                                    in progress
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'approved'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    approved
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'done'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    done
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'sent_back'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending,was sent back
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'rejected'"
                                                    class="px-2 rounded-full bg-red-100 text-red-800">
                                                    rejected
                                                </span>
                                            </div>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ application.date }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.branch">
                                                {{ application.branch.name }}
                                            </span>
                                        </td>

                                        <td class="border-t w-px pr-2">
                                            <div class=" flex items-center gap-4">
                                                <inertia-link :href="route('loan_applications.show', application.id)"
                                                    tabindex="-1" class="text-green-600 hover:text-green-900"
                                                    title="View">
                                                    <font-awesome-icon icon="search" />
                                                </inertia-link>
                                                <inertia-link
                                                    v-if="can('loans.applications.update') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    :href="route('loan_applications.edit', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Edit">
                                                    <font-awesome-icon icon="edit" />
                                                </inertia-link>

                                                <inertia-link
                                                    v-if="can('loans.applications.resend') && application.current_linked_stage?.status == 'returned' || application.current_linked_stage?.status == 'sent_back' && $attrs.user.id == application.created_by_id"
                                                    :href="route('loan_applications.resend', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Resend">
                                                    <font-awesome-icon icon="share" />
                                                </inertia-link>

                                                <a href="#"
                                                    v-if="can('loans.applications.destroy') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    @click="deleteAction(application.id)"
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                    <font-awesome-icon icon="trash" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>


                        </div>
                        <pagination :links="assignedToMeApplications.links" />

                    </div>
                </div>
                <div v-if="activeTab === 'approved'">
                    <h3 class="text-2xl font-semibold mb-4 flex items-center justify-center">Approved</h3>
                    <div v-if="approvedCount === 0" class="flex flex-col items-center justify-center h-64">
                        <svg class="w-24 h-24 text-gray-400 animate-bounce mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 15a4 4 0 014-4h1a4 4 0 018 0h1a4 4 0 014 4v2H3v-2zM7 10V7a3 3 0 013-3 3 3 0 013 3v3m6 10a3 3 0 01-6 0">
                            </path>
                        </svg>
                        <p class="text-lg text-gray-600">Nothing to show yet!</p>
                    </div>
                    <div v-else>
                        <div class="bg-white rounded shadow overflow-x-auto">
                            <table class="w-full whitespace-no-wrap table-auto">
                                <thead class="bg-gray-50">
                                    <tr class="text-left font-bold">


                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Client</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Product</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Branch</th>

                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!approvedByMeApplications.data.length">
                                        <td colspan="7" class="px-6 py-4 text-center">
                                            No applications yet
                                        </td>
                                    </tr>
                                    <tr v-for="application in approvedByMeApplications.data" :key="application.id"
                                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center"
                                                :href="application.id ? route('loan_applications.show', application.id) : '#'"
                                                tabindex="-1">
                                                {{ application.id || 'N/A' }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                                :href="route('clients.show', application.client_id)"
                                                v-if="application.client">
                                                <img v-if="application.client.profile_photo_url"
                                                    class="block w-5 h-5 rounded-full mr-2 -my-2"
                                                    :src="application.client.profile_photo_url">
                                                {{ application.client.name }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.product">
                                                {{ application.product.name }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ $filters.formatNumber(application.amount) }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{
                                                    $filters.formatNumber(application.score)
                                                }} ({{ $filters.formatNumber(application.score_percentage) }}%)
                                            </span>
                                        </td>
                                        <td class="border-t px-6 py-4">
                                            <div v-if="application.current_linked_stage">
                                                <span v-if="application.current_linked_stage.stage">{{
                                                    application.current_linked_stage.stage.name
                                                }} -</span>

                                                <span v-if="application.current_linked_stage.status == 'pending'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'returned'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    returned
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'in_progress'"
                                                    class="px-2 rounded-full bg-blue-100 text-blue-800">
                                                    in progress
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'approved'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    approved
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'done'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    done
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'sent_back'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending,was sent back
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'rejected'"
                                                    class="px-2 rounded-full bg-red-100 text-red-800">
                                                    rejected
                                                </span>
                                            </div>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ application.date }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.branch">
                                                {{ application.branch.name }}
                                            </span>
                                        </td>

                                        <td class="border-t w-px pr-2">
                                            <div class=" flex items-center gap-4">
                                                <inertia-link :href="route('loan_applications.show', application.id)"
                                                    tabindex="-1" class="text-green-600 hover:text-green-900"
                                                    title="View">
                                                    <font-awesome-icon icon="search" />
                                                </inertia-link>
                                                <inertia-link
                                                    v-if="can('loans.applications.update') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    :href="route('loan_applications.edit', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Edit">
                                                    <font-awesome-icon icon="edit" />
                                                </inertia-link>

                                                <inertia-link
                                                    v-if="can('loans.applications.resend') && application.current_linked_stage?.status == 'returned' || application.current_linked_stage?.status == 'sent_back' && $attrs.user.id == application.created_by_id"
                                                    :href="route('loan_applications.resend', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Resend">
                                                    <font-awesome-icon icon="share" />
                                                </inertia-link>

                                                <a href="#"
                                                    v-if="can('loans.applications.destroy') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    @click="deleteAction(application.id)"
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                    <font-awesome-icon icon="trash" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>


                        </div>
                        <pagination :links="approvedByMeApplications.links" />

                    </div>

                </div>
                <div v-if="activeTab === 'pending'">
                    <h3 class="text-2xl font-semibold mb-4 flex items-center justify-center">My Pending</h3>
                    <div v-if="pendingCount === 0" class="flex flex-col items-center justify-center h-64">
                        <svg class="w-24 h-24 text-gray-400 animate-bounce mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 15a4 4 0 014-4h1a4 4 0 018 0h1a4 4 0 014 4v2H3v-2zM7 10V7a3 3 0 013-3 3 3 0 013 3v3m6 10a3 3 0 01-6 0">
                            </path>
                        </svg>
                        <p class="text-lg text-gray-600">Nothing to show yet!</p>
                    </div>
                    <div v-else>
                        <div class="bg-white rounded shadow overflow-x-auto">
                            <table class="w-full whitespace-no-wrap table-auto">
                                <thead class="bg-gray-50">
                                    <tr class="text-left font-bold">


                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Client</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Product</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Branch</th>

                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!pendingToMeApplications.data.length">
                                        <td colspan="7" class="px-6 py-4 text-center">
                                            No applications yet
                                        </td>
                                    </tr>
                                    <tr v-for="application in pendingToMeApplications.data" :key="application.id"
                                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center"
                                                :href="application.id ? route('loan_applications.show', application.id) : '#'"
                                                tabindex="-1">
                                                {{ application.id || 'N/A' }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                                :href="route('clients.show', application.client_id)"
                                                v-if="application.client">
                                                <img v-if="application.client.profile_photo_url"
                                                    class="block w-5 h-5 rounded-full mr-2 -my-2"
                                                    :src="application.client.profile_photo_url">
                                                {{ application.client.name }}
                                            </inertia-link>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.product">
                                                {{ application.product.name }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ $filters.formatNumber(application.amount) }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{
                                                    $filters.formatNumber(application.score)
                                                }} ({{ $filters.formatNumber(application.score_percentage) }}%)
                                            </span>
                                        </td>
                                        <td class="border-t px-6 py-4">
                                            <div v-if="application.current_linked_stage">
                                                <span v-if="application.current_linked_stage.stage">{{
                                                    application.current_linked_stage.stage.name
                                                }} -</span>

                                                <span v-if="application.current_linked_stage.status == 'pending'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'returned'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    returned
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'in_progress'"
                                                    class="px-2 rounded-full bg-blue-100 text-blue-800">
                                                    in progress
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'approved'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    approved
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'done'"
                                                    class="px-2 rounded-full bg-green-100 text-green-800">
                                                    done
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'sent_back'"
                                                    class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                                    pending,was sent back
                                                </span>
                                                <span v-if="application.current_linked_stage.status == 'rejected'"
                                                    class="px-2 rounded-full bg-red-100 text-red-800">
                                                    rejected
                                                </span>
                                            </div>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center">
                                                {{ application.date }}
                                            </span>
                                        </td>
                                        <td class="border-t">
                                            <span class="px-6 py-4 flex items-center" v-if="application.branch">
                                                {{ application.branch.name }}
                                            </span>
                                        </td>

                                        <td class="border-t w-px pr-2">
                                            <div class=" flex items-center gap-4">
                                                <inertia-link :href="route('loan_applications.show', application.id)"
                                                    tabindex="-1" class="text-green-600 hover:text-green-900"
                                                    title="View">
                                                    <font-awesome-icon icon="search" />
                                                </inertia-link>
                                                <inertia-link
                                                    v-if="can('loans.applications.update') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    :href="route('loan_applications.edit', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Edit">
                                                    <font-awesome-icon icon="edit" />
                                                </inertia-link>

                                                <inertia-link
                                                    v-if="can('loans.applications.resend') && application.current_linked_stage?.status == 'returned' || application.current_linked_stage?.status == 'sent_back' && $attrs.user.id == application.created_by_id"
                                                    :href="route('loan_applications.resend', application.id)"
                                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900"
                                                    title="Resend">
                                                    <font-awesome-icon icon="share" />
                                                </inertia-link>

                                                <a href="#"
                                                    v-if="can('loans.applications.destroy') && application.current_linked_stage?.status != 'approved' && application.current_linked_stage?.status != 'rejected'"
                                                    @click="deleteAction(application.id)"
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                    <font-awesome-icon icon="trash" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>


                        </div>
                        <pagination :links="pendingToMeApplications.links" />

                    </div>
                </div>
            </div>
        </div>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>



<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSuccessButton from '@/Jetstream/SuccessButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetInfoButton from '@/Jetstream/InfoButton.vue'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import { formatDistanceToNow } from 'date-fns';





export default {
    components: {
        AppLayout,
        JetDialogModal,
        JetDangerButton,
        JetSuccessButton,
        JetSecondaryButton,
        JetLabel,
        SelectInput,
        JetInfoButton,
        Pagination

    },
    props: {
        // applications: Object,
        filters: Object,
        products: Object,
        branches: Object,
        assignedToMeApplications: Object,
        assignedToMeCount: Number,
        approvedByMeApplications: Object,
        approvedByMeCount: Number,
        pendingToMeApplications: Object,
        pendingToMeCount: Number,
        myReminders: Object,
        myRemindersCount: Number,







    },
    data() {
        return {
            pageTitle: "My Workspace",
            pageDescription: "My Workspace",
            activeTab: 'inbox',  // Default active tab
            inboxCount: this.myRemindersCount,  // Placeholder count for inbox
            assignedCount: this.assignedToMeCount,    // Placeholder count for assigned
            // assignedToMeApplications : this.assignedToMeApplications,
            approvedCount: this.approvedByMeCount,  // Placeholder count for approved
            pendingCount: this.pendingToMeCount,    // Placeholder count for pending
        }
    },
    methods: {
        formatReminderDate(date) {
            return formatDistanceToNow(new Date(date), { addSuffix: true });
        }


    },
    computed: {},
    mounted() {
        // console.log(this.myReminders.links)

    }
}
</script>

<style scoped>
.tabs ul {
    display: flex;
    border-bottom: 2px solid #e2e8f0;
}

.tabs ul li {
    margin-right: 16px;
    /* Increase the right margin to create more space between items */
    padding: 8px 16px;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    cursor: pointer;
    border-radius: 4px 4px 0 0;
    position: relative;
    /* Ensure the badge is positioned relative to the tab */
}

.tabs ul li:last-child {
    margin-right: 0;
    /* Remove the right margin from the last item */
}

.tabs ul li.active {
    background-color: #fff;
    border-top: 2px solid #4299e1;
    border-left: 2px solid #4299e1;
    border-right: 2px solid #4299e1;
    border-bottom: none;
    color: #4299e1;
}

.tab-content {
    padding: 16px;
    border: 2px solid #e2e8f0;
    border-radius: 0 4px 4px 4px;
    background-color: #fff;
}

.badge {
    background-color: #ff0000;
    color: #fff;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
    position: absolute;
    /* Position the badge absolutely */
    top: 0;
    right: 0;
    transform: translate(50%, -50%);
    /* Adjust position to look better */
}
</style>
