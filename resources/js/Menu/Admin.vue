<template>
    <!-- Primary Navigation Menu -->
    <div class="mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <inertia-link :href="route('dashboard')">
                        <jet-application-mark class="block h-9 w-auto"/>
                    </inertia-link>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-4 sm:-my-px sm:ml-10 sm:flex">
                    <jet-nav-link :href="route('dashboard')" :active="route().current('dashboard')">
                                    <span class="ml-1 mr-1">
                                        <font-awesome-icon icon="home"/>
                                    </span>
                        Dashboard
                    </jet-nav-link>
                    <jet-nav-link v-if="can('patients')" :href="route('patients.index')"
                                  :active="route().current('patients.*')">
                                   <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="hospital-user"/>
                                   </span>
                        Patients
                    </jet-nav-link>
                    <jet-nav-link v-if="can('consultations')" :href="route('consultations.index')"
                                  :active="route().current('consultations.*')">
                                    <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="stethoscope"/>
                                    </span>
                        Consultations
                    </jet-nav-link>
                    <jet-nav-link v-if="can('appointments')" :href="route('appointments.index')"
                                  :active="route().current('appointments.*')">
                                     <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="calendar"/>
                                     </span>
                        Appointments
                    </jet-nav-link>
                    <div class="sm:flex sm:items-center sm:ml-6" v-if="can('billing')">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="dollar-sign"/>
                                                 </span>
                                    Billing
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>
                            <template #content>
                                <jet-dropdown-link v-if="can('billing.invoices.index')"
                                                   :href="route('billing.invoices.index')">
                                    View Invoices
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('billing.claims.index')"
                                                   :href="route('billing.claims.index')">
                                    View Claims
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('billing.payments.index')"
                                                   :href="route('billing.payments.index')">
                                    View Payments
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('billing.claim_batches.index')"
                                                   :href="route('billing.claim_batches.index')">
                                    Claim Batches
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                    <div class="sm:flex sm:items-center sm:ml-6" v-if="can('accounting')">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="database"/>
                                                 </span>
                                    Accounting
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>
                            <template #content>
                                <jet-dropdown-link v-if="can('accounting.chart_of_accounts.index')"
                                                   :href="route('accounting.chart_of_accounts.index')">
                                    Chart of Accounts
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('accounting.journal_entries.index')"
                                                   :href="route('accounting.journal_entries.index')">
                                    Journals
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('accounting.financial_periods.index')"
                                                   :href="route('accounting.financial_periods.index')">
                                    Financial Periods
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                    <div class="sm:flex sm:items-center sm:ml-6" v-if="can('communication')">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="mail-bulk"/>
                                                 </span>
                                    Communication
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>
                            <template #content>
                                <jet-dropdown-link v-if="can('communication.campaigns.index')"
                                                   :href="route('communication.campaigns.index')">
                                    View Campaigns
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('communication.campaigns.create')"
                                                   :href="route('communication.campaigns.create')">
                                    Create Campaign
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('communication.templates.index')"
                                                   :href="route('communication.templates.index')">
                                    Manage Templates
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('communication.sms_gateways.index')"
                                                   :href="route('communication.sms_gateways.index')">
                                    SMS Gateways
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                    <div class="sm:flex sm:items-center sm:ml-6" v-if="can('inventory')">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="barcode"/>
                                                 </span>
                                    Inventory
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>
                            <template #content>
                                <jet-dropdown-link v-if="can('inventory.sales.pos')"
                                                   :href="route('inventory.sales.pos')">
                                    POS
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.sales.index')"
                                                   :href="route('inventory.sales.index')">
                                    View Sales
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.sales.create')"
                                                   :href="route('inventory.sales.create')">
                                    Add Sale
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.purchases.index')"
                                                   :href="route('inventory.purchases.index')">
                                    View Purchases
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.purchases.create')"
                                                   :href="route('inventory.purchases.create')">
                                    Add Purchase
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.purchases.returns.index')"
                                                   :href="route('inventory.purchases.returns.index')">
                                    Purchase Returns
                                </jet-dropdown-link>

                                <jet-dropdown-link v-if="can('inventory.sales.returns.index')"
                                                   :href="route('inventory.sales.returns.index')">
                                    Sale Returns
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.products.index')"
                                                   :href="route('inventory.products.index')">
                                    Manage Products
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.warehouses.index')"
                                                   :href="route('inventory.warehouses.index')">
                                    Manage Warehouses
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.suppliers.index')"
                                                   :href="route('inventory.suppliers.index')">
                                    Manage Suppliers
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.stock_takes.index')"
                                                   :href="route('inventory.stock_takes.index')">
                                    Stock Takes
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('inventory.stock_adjustments.index')"
                                                   :href="route('inventory.stock_adjustments.index')">
                                    Stock Adjustments
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                    <div class="sm:flex sm:items-center sm:ml-6" v-if="can('expenses')">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="database"/>
                                                 </span>
                                    Expenses
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>
                            <template #content>
                                <jet-dropdown-link v-if="can('expenses.index')"
                                                   :href="route('expenses.index')">
                                    View Expenses
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('expenses.index')"
                                                   :href="route('expenses.create')">
                                    Create Expense
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('expenses.types.index')"
                                                   :href="route('expenses.types.index')">
                                    Manage Expense Types
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                    <jet-nav-link v-if="can('reports')" :href="route('reports.index')"
                                  :active="route().current('reports.*')">
                                    <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="chart-bar"/>
                                    </span>
                        Reports
                    </jet-nav-link>
                    <div class="hidden sm:flex sm:items-center sm:ml-6">
                        <jet-dropdown align="right" width="48">
                            <template #trigger>
                                <a href="#" type="button"
                                   class="h-full inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                                                <span class="ml-1 mr-1">
                                                    <font-awesome-icon icon="plus"/>
                                                 </span>
                                    More
                                    <svg class="ml-2 -mr-0.5 h-4 w-4"
                                         xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </template>

                            <template #content>

                                <jet-dropdown-link v-if="can('users')" :href="route('users.index')"
                                                   :active="route().current('users.*')">
                                     <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="users"/>
                                     </span>
                                    Users
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('branches.index')"
                                                   :href="route('branches.index')"
                                                   :active="route().current('branches.*')">
                                         <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="building"/>
                                    </span>
                                    Branches
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('forms.index')"
                                                   :href="route('forms.index')"
                                                   :active="route().current('forms.*')">
                                         <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="book"/>
                                    </span>
                                    Manage Forms
                                </jet-dropdown-link>
                                <jet-dropdown-link v-if="can('settings')"
                                                   :href="route('settings.index')"
                                                   :active="route().current('settings.*')">
                                         <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="cogs"/>
                                    </span>
                                    Settings
                                </jet-dropdown-link>
                            </template>
                        </jet-dropdown>
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <!-- Settings Dropdown -->
                <div class="ml-3 relative">
                    <jet-dropdown align="right" width="48">
                        <template #trigger>
                            <button v-if="$page.props.jetstream.managesProfilePhotos"
                                    class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition duration-150 ease-in-out">
                                <img class="h-8 w-8 rounded-full object-cover"
                                     :src="$page.props.user.profile_photo_url"
                                     :alt="$page.props.user.name"/>
                            </button>

                            <span v-else class="inline-flex rounded-md">
                                <button type="button"
                                        class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                    Hi {{ $page.props.user.first_name }}

                                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                         viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </span>
                        </template>

                        <template #content>
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                Manage Account
                            </div>

                            <jet-dropdown-link :href="route('profile.show')">
                                Profile
                            </jet-dropdown-link>

                            <jet-dropdown-link :href="route('api-tokens.index')"
                                               v-if="$page.props.jetstream.hasApiFeatures">
                                API Tokens
                            </jet-dropdown-link>

                            <div class="border-t border-gray-100"></div>

                            <!-- Authentication -->
                            <form @submit.prevent="logout">
                                <jet-dropdown-link as="button">
                                    Log Out
                                </jet-dropdown-link>
                            </form>
                        </template>
                    </jet-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="showingNavigationDropdown = ! showingNavigationDropdown"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path
                            :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"/>
                        <path
                            :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}"
         class="sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <jet-responsive-nav-link :href="route('dashboard')" :active="route().current('dashboard')">
                 <span class="ml-1 mr-1">
                    <font-awesome-icon icon="home"/>
                </span>
                Dashboard
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('patients')" :href="route('patients.index')"
                                     :active="route().current('patients.*')">
                                   <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="hospital-user"/>
                                   </span>
                Patients
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('consultations')" :href="route('consultations.index')"
                                     :active="route().current('consultations.*')">
                                    <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="stethoscope"/>
                                    </span>
                Consultations
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('appointments')" :href="route('appointments.index')"
                                     :active="route().current('appointments.*')">
                 <span class="ml-1 mr-1">
                <font-awesome-icon icon="calendar"/>
                 </span>
                Appointments
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('billing.invoices.index')"
                                     :href="route('billing.invoices.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="money-bill"/>
                 </span>
                View Invoices
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('billing.claims.index')"
                                     :href="route('billing.claims.index')">
                 <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                View Claims
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('billing.payments.index')"
                                     :href="route('billing.payments.index')">
                 <span class="ml-1 mr-1">
                    <font-awesome-icon icon="dollar-sign"/>
                 </span>
                View Payments
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('billing.claim_batches.index')"
                                     :href="route('billing.claim_batches.index')">
                 <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                Claim Batches
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('accounting.chart_of_accounts.index')"
                                     :href="route('accounting.chart_of_accounts.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                Chart of Accounts
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('accounting.journal_entries.index')"
                                     :href="route('accounting.journal_entries.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                Journals
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('accounting.financial_periods.index')"
                                     :href="route('accounting.financial_periods.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                Financial Periods
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('communication.campaigns.index')"
                                     :href="route('communication.campaigns.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="envelope"/>
                 </span>
                View Campaigns
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('communication.templates.index')"
                                     :href="route('communication.templates.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                 </span>
                Manage Campaign Templates
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('users')" :href="route('users.index')"
                                     :active="route().current('users.*')">
                                     <span class="ml-1 mr-1">
                                    <font-awesome-icon icon="users"/>
                                     </span>
                Users
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('branches.index')"
                                     :href="route('branches.index')"
                                     :active="route().current('branches.*')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="building"/>
                </span>
                Branches
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('forms.index')"
                                     :href="route('forms.index')"
                                     :active="route().current('forms.*')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="book"/>
                </span>
                Manage Forms
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('expenses.index')"
                                     :href="route('expenses.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="file-export"/>
                </span>
                View Expenses
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('expenses.types.index')"
                                     :href="route('expenses.types.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="database"/>
                </span>
                Manage Expense Types
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('inventory.products.index')"
                                     :href="route('inventory.products.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="barcode"/>
                </span>
                View Products
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('inventory.purchases.index')"
                                     :href="route('inventory.purchases.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="dollar-sign"/>
                </span>
                View Purchases
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('inventory.purchases.returns.index')"
                                     :href="route('inventory.purchases.returns.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="arrow-alt-circle-right"/>
                </span>
                Purchase Returns
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('inventory.sales.index')"
                                     :href="route('inventory.sales.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="money-bill"/>
                </span>
                View Sales
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('inventory.sales.returns.index')"
                                     :href="route('inventory.sales.returns.index')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="arrow-alt-circle-left"/>
                </span>
                Sale Returns
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('reports')" :href="route('reports.index')"
                                     :active="route().current('reports.*')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="chart-bar"/>
                </span>
                Reports
            </jet-responsive-nav-link>
            <jet-responsive-nav-link v-if="can('settings')"
                                     :href="route('settings.index')"
                                     :active="route().current('settings.*')">
                <span class="ml-1 mr-1">
                    <font-awesome-icon icon="cogs"/>
                </span>
                Settings
            </jet-responsive-nav-link>

        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="flex items-center px-4">
                <div v-if="$page.props.jetstream.managesProfilePhotos" class="flex-shrink-0 mr-3">
                    <img class="h-10 w-10 rounded-full object-cover"
                         :src="$page.props.user.profile_photo_url" :alt="$page.props.user.name"/>
                </div>

                <div>
                    <div class="font-medium text-base text-gray-800">{{ $page.props.user.name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ $page.props.user.email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <jet-responsive-nav-link :href="route('profile.show')"
                                         :active="route().current('profile.show')">
                    Profile
                </jet-responsive-nav-link>

                <jet-responsive-nav-link :href="route('api-tokens.index')"
                                         :active="route().current('api-tokens.index')"
                                         v-if="$page.props.jetstream.hasApiFeatures">
                    API Tokens
                </jet-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" @submit.prevent="logout">
                    <jet-responsive-nav-link as="button">
                        Log Out
                    </jet-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import JetBanner from "@/Jetstream/Banner.vue";
import JetDropdown from "@/Jetstream/Dropdown.vue";
import JetDropdownLink from "@/Jetstream/DropdownLink.vue";
import JetNavLink from "@/Jetstream/NavLink.vue";
import JetResponsiveNavLink from "@/Jetstream/ResponsiveNavLink.vue";
import FlashMessages from "@/Jetstream/FlashMessages.vue";
import JetApplicationMark from "@/Jetstream/ApplicationMark.vue";

export default {
    components: {
        JetBanner,
        JetDropdown,
        JetDropdownLink,
        JetNavLink,
        JetResponsiveNavLink,
        FlashMessages,
        JetApplicationMark,
    },

    data() {
        return {
            showingNavigationDropdown: false,
        }
    },

    methods: {
        switchToTeam(team) {
            this.$inertia.put(route('current-team.update'), {
                'team_id': team.id
            }, {
                preserveState: false
            })
        },

        logout() {
            this.$inertia.post(route('logout'));
        },
    }
}
</script>
