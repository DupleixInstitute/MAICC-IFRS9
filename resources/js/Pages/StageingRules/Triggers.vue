<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                        <svg class="w-6 h-6 mr-2 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        SICR Trigger Alerts
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Report significant increases in credit risk events</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="px-3 py-1 bg-orange-100 text-orange-800 text-xs font-medium rounded-full">
                        {{ triggers?.data?.length || 0 }} Triggers
                    </div>
                    <div class="px-3 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                        Alert System
                    </div>
                </div>
            </div>
        </template>
        
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Action Bar -->
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">SICR Alert Management</h3>
                    <p class="mt-1 text-sm text-gray-500">Monitor and trigger alerts for significant credit risk changes</p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="openModal"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200"
                    >
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Trigger Alert
                    </button>
                </div>
            </div>
            
            <!-- Triggers History Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Trigger History
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Timestamp</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    SICR Details
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Account
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Affect All
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last Update
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Triggered By
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-900 uppercase tracking-wider bg-gray-100">
                                    <div class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-1 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Actions
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(trigger, index) in triggers.data" :key="trigger.id" 
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                class="hover:bg-orange-50 transition-colors duration-150"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-orange-100 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ formatDate(trigger.created_at) }}</div>
                                            <div class="text-sm text-gray-500">{{ formatTime(trigger.created_at) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded bg-indigo-100 flex items-center justify-center">
                                                <span class="text-xs font-medium text-indigo-600">{{ trigger.group?.name?.substring(0, 2).toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ trigger.group?.name }}</div>
                                            <div class="text-sm text-gray-500">{{ trigger.item?.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ trigger.customer_id || 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ trigger.account_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="trigger.affect_all" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Yes
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        No
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="trigger.last_update" class="text-sm text-gray-900">
                                        <div>{{ formatDate(trigger.last_update) }}</div>
                                        <div class="text-xs text-gray-500">{{ formatTime(trigger.last_update) }}</div>
                                    </div>
                                    <span v-else class="text-sm text-gray-500">N/A</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-6 w-6">
                                            <div class="h-6 w-6 rounded-full bg-gray-200 flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-600">{{ trigger.user?.name?.charAt(0).toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-2">
                                            <div class="text-sm font-medium text-gray-900">{{ trigger.user?.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span v-if="!trigger.removal_date" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        Alert Active
                                    </span>
                                    <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-gray-400" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        Removed
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap bg-gray-50">
                                    <div class="flex items-center justify-center space-x-2">
                                        <!-- Update Loan Book Button -->
                                        <button
                                            v-if="!trigger.removal_date"
                                            @click="openUpdateModal(trigger)"
                                            class="inline-flex items-center px-4 py-2 border-2 border-blue-200 rounded-lg text-sm font-semibold text-blue-800 bg-blue-100 hover:bg-blue-200 hover:border-blue-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-blue-300 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105"
                                            title="Update Loan Book for this trigger"
                                        >
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a2 2 0 002 2V4a2 2 0 012-2h11a2 2 0 00-2-2H4zm3 6a2 2 0 012-2h5a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V8zm2-2a1 1 0 000 2h5a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            Update Book
                                        </button>
                                        
                                        <!-- Remove Alert Button -->
                                        <button
                                            v-if="!trigger.removal_date"
                                            @click="removeAlert(trigger)"
                                            class="inline-flex items-center px-4 py-2 border-2 border-red-200 rounded-lg text-sm font-semibold text-red-800 bg-red-100 hover:bg-red-200 hover:border-red-300 hover:shadow-md focus:outline-none focus:ring-3 focus:ring-red-300 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105"
                                            title="Remove this alert permanently"
                                        >
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2h8a2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                            </svg>
                                            Remove
                                        </button>
                                        
                                        <!-- Placeholder for removed alerts -->
                                        <div v-if="trigger.removal_date" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-200 border-2 border-gray-300">
                                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-600">
                                                Removed {{ formatDate(trigger.removal_date) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="triggers.data.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No triggers found</h3>
                                        <p class="text-sm text-gray-500 mb-4">No SICR alerts have been triggered yet.</p>
                                        <button
                                            @click="openModal"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-orange-700 bg-orange-100 hover:bg-orange-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                                        >
                                            <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            Trigger First Alert
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-if="triggers.links" class="bg-white px-6 py-3 border-t border-gray-200">
                    <pagination :links="triggers.links"/>
                </div>
            </div>
        </div>
        
        <!-- Trigger Alert Modal -->
        <jet-modal :show="showModal" @close="closeModal" max-width="2xl">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-orange-600 to-red-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Trigger SICR Alert
                    </h3>
                    <p class="mt-1 text-orange-100 text-sm">Report a significant increase in credit risk event</p>
                </div>
                
                <form @submit.prevent="submit" class="p-6">
                    <div class="space-y-6">
                        <!-- Alert Information Panel -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Important Notice</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>This alert will be logged and may trigger additional review processes. Ensure all information is accurate before submitting.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- SICR Group -->
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <jet-label class="text-sm font-medium text-gray-900">
                                        SICR Group *
                                    </jet-label>
                                    <p class="text-xs text-gray-500 mt-1">Select the risk factor group</p>
                                    <div class="mt-3 relative">
                                        <select 
                                            v-model.number="form.group_id" 
                                            @change="filterItems"
                                            class="form-input"
                                            required
                                            :disabled="processing"
                                        >
                                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SICR Item -->
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <jet-label class="text-sm font-medium text-gray-900">
                                        SICR Item *
                                    </jet-label>
                                    <p class="text-xs text-gray-500 mt-1">Specific risk factor triggered</p>
                                    <div class="mt-3 relative">
                                        <select 
                                            v-model.number="form.item_id" 
                                            class="form-input"
                                            required
                                            :disabled="processing"
                                        >
                                            <option v-for="i in filteredItems" :key="i.id" :value="i.id">{{ i.name }}</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Search -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Customer ID
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Search and select customer by ID</p>
                                <div class="mt-3 relative customer-search-container">
                                    <input 
                                        v-model="customerSearch" 
                                        @input="searchCustomers"
                                        @focus="showCustomerDropdown = true"
                                        class="form-input"
                                        :class="{ 'border-red-300 focus:border-red-300 focus:ring-red-200': $page.props.errors?.customer_id }"
                                        type="text"
                                        placeholder="Type to search customers..."
                                        :disabled="processing"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    
                                    <!-- Customer Dropdown -->
                                    <div v-if="showCustomerDropdown && customerResults.length > 0" 
                                         class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                        <div v-for="customer in customerResults" 
                                             :key="customer.id" 
                                             @click="selectCustomer(customer)"
                                             class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-orange-50">
                                            <span class="font-medium block truncate">{{ customer.external_identity_id }}</span>
                                            <span class="text-gray-500 text-sm">{{ customer.loan_count }} loan(s)</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Customer Error Message -->
                                <div v-if="$page.props.errors?.customer_id" class="mt-2">
                                    <p class="text-sm text-red-600 flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $page.props.errors.customer_id }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Options -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Affect All Checkbox -->
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <label class="flex items-center">
                                        <input 
                                            v-model="form.affect_all" 
                                            type="checkbox"
                                            class="rounded border-gray-300 text-orange-600 shadow-sm focus:border-orange-300 focus:ring focus:ring-orange-200 focus:ring-opacity-50"
                                            :disabled="processing || !form.customer_id"
                                        >
                                        <span class="ml-2 text-sm font-medium text-gray-900">Affect all accounts under customer</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">Apply trigger to all loans for this customer</p>
                                </div>
                            </div>
                            
                            <!-- Effective Period -->
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <jet-label class="text-sm font-medium text-gray-900">
                                        Effective Period
                                    </jet-label>
                                    <p class="text-xs text-gray-500 mt-1">Date when trigger becomes effective</p>
                                    <div class="mt-3 relative">
                                        <input 
                                            v-model="form.effective_period" 
                                            class="form-input" 
                                            type="date"
                                            :disabled="processing"
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Number -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Account Number *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Customer account reference</p>
                                <div class="mt-3 relative">
                                    <input 
                                        v-model="form.account_number" 
                                        class="form-input" 
                                        type="text"
                                        placeholder="e.g., ACC-123456789"
                                        required
                                        :disabled="processing"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 01-3.46 0" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reason -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Reason for Alert *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Detailed explanation of the credit risk increase</p>
                                <div class="mt-3">
                                    <textarea 
                                        v-model="form.reason" 
                                        class="form-input" 
                                        rows="4"
                                        placeholder="Provide detailed information about why this SICR alert is being triggered..."
                                        required
                                        :disabled="processing"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Loan Book Option -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <label class="flex items-center">
                                    <input 
                                        v-model="form.update_loan_book_now" 
                                        type="checkbox"
                                        class="rounded border-gray-300 text-orange-600 shadow-sm focus:border-orange-300 focus:ring focus:ring-orange-200 focus:ring-opacity-50"
                                        :disabled="processing"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-900">Update Loan Book Now</span>
                                </label>
                                <p class="text-xs text-gray-500 mt-1">Immediately apply this trigger to update the loan book</p>
                            </div>
                        </div>
                        
                        <!-- File Attachment -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Supporting Documentation (Optional)
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Upload supporting files or evidence</p>
                                <div class="mt-3">
                                    <input 
                                        @change="onFile" 
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 file:cursor-pointer border border-gray-300 rounded-md" 
                                        type="file"
                                        :disabled="processing"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-6 border-t border-gray-200 mt-6 space-x-3">
                        <button
                            type="button"
                            @click="closeModal"
                            :disabled="processing"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="processing || !isValid"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            {{ processing ? 'Triggering Alert...' : 'Trigger Alert' }}
                        </button>
                    </div>
                </form>
            </div>
        </jet-modal>
        
        <!-- Update Loan Book Modal -->
        <jet-modal :show="showUpdateModal" @close="closeUpdateModal" max-width="lg">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Update Loan Book
                    </h3>
                    <p class="mt-1 text-blue-100 text-sm">Apply trigger changes to the loan book</p>
                </div>
                
                <form @submit.prevent="submitUpdateLoanBook" class="p-6">
                    <div class="space-y-6">
                        <!-- Error Display -->
                        <div v-if="$page.props.errors?.customer" class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>{{ $page.props.errors.customer }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Trigger Information -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">Trigger Details</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p><strong>Account:</strong> {{ selectedTrigger?.account_number }}</p>
                                        <p><strong>SICR Group:</strong> {{ selectedTrigger?.group?.name }}</p>
                                        <p><strong>SICR Item:</strong> {{ selectedTrigger?.item?.name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Effective Period -->
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Effective Period *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Select the period for which to update the loan book</p>
                                <div class="mt-3 relative">
                                    <input 
                                        v-model="updateForm.effective_period" 
                                        class="form-input" 
                                        type="date"
                                        required
                                        :disabled="updateProcessing"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-6 border-t border-gray-200 mt-6 space-x-3">
                        <button
                            type="button"
                            @click="closeUpdateModal"
                            :disabled="updateProcessing"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="updateProcessing || !updateForm.effective_period"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="updateProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ updateProcessing ? 'Updating...' : 'Update Loan Book' }}
                        </button>
                    </div>
                </form>
            </div>
        </jet-modal>
        
        <teleport to="head">
            <title>SICR Trigger Alerts - IFRS 9 Staging Rules</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetModal from '@/Jetstream/Modal.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    props: { groups: Array, items: Array, triggers: Object },
    components: { AppLayout, JetButton, JetLabel, JetModal, Pagination },
    data(){
        return { 
            form: { 
                group_id: this.groups[0]?.id||null, 
                item_id: null, 
                account_number: '', 
                reason: '', 
                attachment: null,
                customer_id: '',
                affect_all: false,
                effective_period: '',
                update_loan_book_now: false
            }, 
            processing: false,
            showModal: false,
            
            // Customer search
            customerSearch: '',
            customerResults: [],
            showCustomerDropdown: false,
            searchTimeout: null,
            
            // Update loan book modal
            showUpdateModal: false,
            selectedTrigger: null,
            updateForm: {
                effective_period: ''
            },
            updateProcessing: false
        }
    },
    computed:{
        filteredItems() { 
            return this.items.filter(i => i.group_id === this.form.group_id) 
        },
        isValid() {
            return this.form.group_id && 
                   this.form.item_id && 
                   this.form.account_number && 
                   this.form.reason
        }
    },
    watch: {
        'form.group_id': {
            handler() {
                this.filterItems()
            },
            immediate: true
        }
    },
    mounted() {
        // Close customer dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = e.target.closest('.customer-search-container')
            if (!dropdown) {
                this.showCustomerDropdown = false
            }
        })
    },
    methods:{
        // Modal Controls
        openModal() {
            this.showModal = true
            this.resetForm()
        },
        closeModal() {
            this.showModal = false
            this.resetForm()
        },
        resetForm() {
            this.form = {
                group_id: this.groups[0]?.id||null,
                item_id: null,
                account_number: '',
                reason: '',
                attachment: null,
                customer_id: '',
                affect_all: false,
                effective_period: '',
                update_loan_book_now: false
            }
            this.customerSearch = ''
            this.customerResults = []
            this.showCustomerDropdown = false
            this.filterItems()
        },
        
        // Data Management
        filterItems() { 
            if (!this.filteredItems.find(i => i.id === this.form.item_id)) {
                this.form.item_id = this.filteredItems[0]?.id || null
            }
        },
        onFile(e) { 
            this.form.attachment = e.target.files[0] 
        },
        
        // Customer Search
        searchCustomers() {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout)
            }
            
            this.searchTimeout = setTimeout(() => {
                if (this.customerSearch.length < 2) {
                    this.customerResults = []
                    return
                }
                
                fetch(`${this.route('sicr-triggers.customers')}?search=${encodeURIComponent(this.customerSearch)}`)
                    .then(response => response.json())
                    .then(data => {
                        this.customerResults = data.customers || []
                    })
                    .catch(error => {
                        console.error('Customer search failed:', error)
                        this.customerResults = []
                    })
            }, 300)
        },
        
        selectCustomer(customer) {
            this.form.customer_id = customer.external_identity_id
            this.customerSearch = customer.external_identity_id
            this.showCustomerDropdown = false
            this.customerResults = []
        },
        
        // Update Loan Book Modal
        openUpdateModal(trigger) {
            this.selectedTrigger = trigger
            this.updateForm = {
                effective_period: ''
            }
            this.showUpdateModal = true
        },
        
        closeUpdateModal() {
            this.showUpdateModal = false
            this.selectedTrigger = null
            this.updateForm = {
                effective_period: ''
            }
        },
        
        submitUpdateLoanBook() {
            if (!this.updateForm.effective_period) return
            
            this.updateProcessing = true
            
            this.$inertia.post(this.route('sicr-triggers.update-loan-book', this.selectedTrigger.id), {
                effective_period: this.updateForm.effective_period
            }, {
                onFinish: () => {
                    this.updateProcessing = false
                },
                onSuccess: () => {
                    this.closeUpdateModal()
                    this.$toast?.success('Loan book updated successfully!')
                },
                onError: (errors) => {
                    console.error('Update loan book errors:', errors)
                    this.$toast?.error('Failed to update loan book. Please try again.')
                }
            })
        },
        
        // Remove Alert
        removeAlert(trigger) {
            if (!confirm('Are you sure you want to remove this alert? This action cannot be undone.')) {
                return
            }
            
            this.$inertia.post(this.route('sicr-triggers.remove-alert', trigger.id), {}, {
                onSuccess: () => {
                    this.$toast?.success('Alert removed successfully!')
                },
                onError: (errors) => {
                    console.error('Remove alert errors:', errors)
                    this.$toast?.error('Failed to remove alert. Please try again.')
                }
            })
        },
        
        // Form Submission
        submit() {
            if (!this.isValid) return
            
            this.processing = true
            const data = new FormData()
            
            Object.keys(this.form).forEach(key => {
                if (key === 'affect_all' || key === 'update_loan_book_now') {
                    // Handle boolean values
                    data.append(key, this.form[key] ? '1' : '0')
                } else if (this.form[key] !== null && this.form[key] !== '') {
                    data.append(key, this.form[key])
                }
            })
            
            this.$inertia.post(this.route('sicr-triggers.store'), data, { 
                onFinish: () => { 
                    this.processing = false 
                },
                onSuccess: () => {
                    this.closeModal()
                    this.$toast?.success('SICR alert triggered successfully!')
                },
                onError: (errors) => {
                    console.error('Validation errors:', errors)
                    this.$toast?.error('Please check your input and try again.')
                }
            })
        },
        
        // Date Formatting
        formatDate(dateString) {
            const date = new Date(dateString)
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            })
        },
        formatTime(dateString) {
            const date = new Date(dateString)
            return date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit' 
            })
        }
    }
}
</script>

<style scoped>
.form-input {
    @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200;
}

.form-input:focus {
    @apply border-orange-300 bg-orange-50;
}

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
