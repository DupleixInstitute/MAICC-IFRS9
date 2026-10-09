<template>
    <app-layout title="Settings">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Settings</h2>
            <p class="mt-1 text-sm text-gray-600">Organisation details, the reporting currency, system email and the staging basis the IFRS 9 engine uses.</p>
        </template>
        <template #actions>
            <Link v-if="can('eir.govern')" :href="route('eir-governance.index')"
                  class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <font-awesome-icon icon="gavel"/> Governance Centre
            </Link>
        </template>

        <div class="w-full space-y-5">
            <!-- At a glance: values read from the saved settings and the governed staging basis -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="maiic-kpi">
                    <div class="maiic-kpi-label">Organisation</div>
                    <div class="truncate text-lg font-extrabold text-gray-900">{{ organisation.company_name || 'Not set' }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#d4a017">
                    <div class="maiic-kpi-label">Reporting currency</div>
                    <div class="truncate text-lg font-extrabold text-gray-900">{{ currentCurrency ? currentCurrency.label : 'Not set' }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#0f766e">
                    <div class="maiic-kpi-label">Days past due counted from</div>
                    <div class="text-base font-extrabold leading-snug text-gray-900">{{ dpdBasis || 'Not set' }}</div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 pt-4">
                    <nav class="flex gap-6 overflow-x-auto" aria-label="Settings">
                        <button
                            v-for="t in tabs"
                            :key="t.key"
                            type="button"
                            class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                            :class="active === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            :aria-current="active === t.key ? 'page' : null"
                            @click="openTab(t.key)"
                        >
                            {{ t.label }}
                            <span v-if="t.count !== null"
                                  class="ml-1 rounded-full px-2 py-0.5 text-xs"
                                  :class="active === t.key ? 'bg-maiic-100 text-maiic-700' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
                        </button>
                    </nav>
                </div>

                <div class="border-b border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900">{{ currentTab.label }}</h3>
                    <p class="text-xs text-gray-500">{{ currentTab.description }}</p>
                </div>

                <!-- Organisation -->
                <form v-if="active === 'organisation'" class="p-5" @submit.prevent="saveOrganisation">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="company_name" class="maiic-flabel">Organisation name</label>
                            <input id="company_name" v-model="orgForm.company_name" type="text" class="maiic-input" required>
                            <p class="mt-1 text-xs text-gray-500">Shown in the top bar and on every report and export.</p>
                            <jet-input-error :message="orgForm.errors.company_name" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_email" class="maiic-flabel">Organisation email</label>
                            <input id="company_email" v-model="orgForm.company_email" type="email" class="maiic-input">
                            <p class="mt-1 text-xs text-gray-500">The general contact address printed on reports.</p>
                            <jet-input-error :message="orgForm.errors.company_email" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_tel" class="maiic-flabel">Telephone</label>
                            <input id="company_tel" v-model="orgForm.company_tel" type="text" class="maiic-input">
                            <p class="mt-1 text-xs text-gray-500">Main office number, printed on reports.</p>
                            <jet-input-error :message="orgForm.errors.company_tel" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_mobile" class="maiic-flabel">Mobile</label>
                            <input id="company_mobile" v-model="orgForm.company_mobile" type="text" class="maiic-input">
                            <p class="mt-1 text-xs text-gray-500">Optional second contact number.</p>
                            <jet-input-error :message="orgForm.errors.company_mobile" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_website" class="maiic-flabel">Website</label>
                            <input id="company_website" v-model="orgForm.company_website" type="text" class="maiic-input" placeholder="https://">
                            <p class="mt-1 text-xs text-gray-500">The organisation's public website address.</p>
                            <jet-input-error :message="orgForm.errors.company_website" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_address" class="maiic-flabel">Postal address</label>
                            <textarea id="company_address" v-model="orgForm.company_address" rows="3" class="maiic-input"></textarea>
                            <p class="mt-1 text-xs text-gray-500">Printed in the header of reports and exports.</p>
                            <jet-input-error :message="orgForm.errors.company_address" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_logo" class="maiic-flabel">Logo</label>
                            <div class="flex items-center gap-3">
                                <img v-if="organisation.company_logo_url" :src="organisation.company_logo_url" alt="Current logo" class="h-12 w-auto rounded border border-gray-200 bg-gray-50 p-1">
                                <input id="company_logo" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-maiic-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-maiic-700 hover:file:bg-maiic-100"
                                       @input="orgForm.company_logo = $event.target.files[0]">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Shown in the sidebar and on reports. PNG or JPG, up to 2 MB. Leave empty to keep the current one.</p>
                            <jet-input-error :message="orgForm.errors.company_logo" class="mt-1"/>
                        </div>
                        <div>
                            <label for="company_small_logo" class="maiic-flabel">Small logo</label>
                            <div class="flex items-center gap-3">
                                <img v-if="organisation.company_small_logo_url" :src="organisation.company_small_logo_url" alt="Current small logo" class="h-12 w-auto rounded border border-gray-200 bg-gray-50 p-1">
                                <input id="company_small_logo" type="file" accept="image/*" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-maiic-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-maiic-700 hover:file:bg-maiic-100"
                                       @input="orgForm.company_small_logo = $event.target.files[0]">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Square version used when the sidebar is collapsed. Leave empty to keep the current one.</p>
                            <jet-input-error :message="orgForm.errors.company_small_logo" class="mt-1"/>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700 disabled:opacity-50" :disabled="orgForm.processing">
                            <font-awesome-icon icon="check"/> Save organisation details
                        </button>
                    </div>
                </form>

                <!-- Reporting -->
                <form v-else-if="active === 'reporting'" class="p-5" @submit.prevent="saveReporting">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="currency" class="maiic-flabel">Reporting currency</label>
                            <select id="currency" v-model="reportingForm.currency" class="maiic-select" required>
                                <option value="" disabled>Choose a currency</option>
                                <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}{{ c.active ? '' : ' - not active' }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">The currency code shown beside amounts on the dashboard and in the accounting reports. It labels figures; it does not convert them.</p>
                            <jet-input-error :message="reportingForm.errors.currency" class="mt-1"/>
                        </div>
                        <div class="space-y-3">
                            <div v-if="currentCurrency && !currentCurrency.active" class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                <svg class="mt-0.5 h-5 w-5 flex-none text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.26 2.6c.77-1.33 2.7-1.33 3.47 0l6.03 10.45c.77 1.33-.19 3-1.73 3H3.97c-1.54 0-2.5-1.67-1.73-3L8.26 2.6zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <div>
                                    <p class="font-semibold">The chosen currency is not marked active</p>
                                    <p class="mt-0.5">Check it is the currency the loan book is held in. If the right currency is missing, add it under Currencies, then choose it here.</p>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                                <p class="font-semibold text-gray-800">Financial year and periods</p>
                                <p class="mt-0.5">Reporting periods are kept on their own screen.</p>
                                <Link :href="route('accounting.financial_periods.index')" class="mt-2 inline-flex items-center gap-1 font-semibold text-maiic-700 hover:text-maiic-900">
                                    Open Financial Periods <font-awesome-icon icon="chevron-right" class="h-3 w-3"/>
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700 disabled:opacity-50" :disabled="reportingForm.processing">
                            <font-awesome-icon icon="check"/> Save reporting currency
                        </button>
                    </div>
                </form>

                <!-- Email -->
                <form v-else-if="active === 'email'" class="p-5" @submit.prevent="saveEmail">
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="mail_mailer" class="maiic-flabel">Sending method</label>
                            <select id="mail_mailer" v-model="emailForm.mail_mailer" class="maiic-select" required>
                                <option value="smtp">Mail server (SMTP)</option>
                                <option value="sendmail">This server's sendmail</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">How the system sends password resets and new-user login details.</p>
                            <jet-input-error :message="emailForm.errors.mail_mailer" class="mt-1"/>
                        </div>
                        <div class="hidden md:block"></div>
                        <template v-if="emailForm.mail_mailer === 'smtp'">
                            <div>
                                <label for="mail_host" class="maiic-flabel">Mail server</label>
                                <input id="mail_host" v-model="emailForm.mail_host" type="text" class="maiic-input" placeholder="smtp.example.com">
                                <p class="mt-1 text-xs text-gray-500">The SMTP server name your IT team gives you.</p>
                                <jet-input-error :message="emailForm.errors.mail_host" class="mt-1"/>
                            </div>
                            <div>
                                <label for="mail_port" class="maiic-flabel">Port</label>
                                <input id="mail_port" v-model="emailForm.mail_port" type="number" min="1" max="65535" class="maiic-input">
                                <p class="mt-1 text-xs text-gray-500">Usually 587 with TLS, 465 with SSL, or 25 with no encryption.</p>
                                <jet-input-error :message="emailForm.errors.mail_port" class="mt-1"/>
                            </div>
                            <div>
                                <label for="mail_username" class="maiic-flabel">Username</label>
                                <input id="mail_username" v-model="emailForm.mail_username" type="text" class="maiic-input" autocomplete="off">
                                <p class="mt-1 text-xs text-gray-500">The mailbox account used to sign in to the mail server.</p>
                                <jet-input-error :message="emailForm.errors.mail_username" class="mt-1"/>
                            </div>
                            <div>
                                <label for="mail_password" class="maiic-flabel">Password</label>
                                <input id="mail_password" v-model="emailForm.mail_password" type="password" class="maiic-input" autocomplete="new-password"
                                       :placeholder="email.mail_password_set ? 'Saved - leave blank to keep it' : 'Not set'">
                                <p class="mt-1 text-xs text-gray-500">Never shown once saved. Leave blank to keep the current password.</p>
                                <jet-input-error :message="emailForm.errors.mail_password" class="mt-1"/>
                            </div>
                            <div>
                                <label for="mail_encryption" class="maiic-flabel">Encryption</label>
                                <select id="mail_encryption" v-model="emailForm.mail_encryption" class="maiic-select">
                                    <option value="">None</option>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">How the connection to the mail server is secured.</p>
                                <jet-input-error :message="emailForm.errors.mail_encryption" class="mt-1"/>
                            </div>
                            <div class="hidden md:block"></div>
                        </template>
                        <div>
                            <label for="mail_from_address" class="maiic-flabel">Sender address</label>
                            <input id="mail_from_address" v-model="emailForm.mail_from_address" type="email" class="maiic-input" placeholder="noreply@example.com">
                            <p class="mt-1 text-xs text-gray-500">The "from" address users see on system emails.</p>
                            <jet-input-error :message="emailForm.errors.mail_from_address" class="mt-1"/>
                        </div>
                        <div>
                            <label for="mail_from_name" class="maiic-flabel">Sender name</label>
                            <input id="mail_from_name" v-model="emailForm.mail_from_name" type="text" class="maiic-input">
                            <p class="mt-1 text-xs text-gray-500">The name shown beside the sender address.</p>
                            <jet-input-error :message="emailForm.errors.mail_from_name" class="mt-1"/>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700 disabled:opacity-50" :disabled="emailForm.processing">
                            <font-awesome-icon icon="check"/> Save email settings
                        </button>
                    </div>
                </form>

                <!-- Staging basis (read only; the Governance Centre is the one place to change it) -->
                <div v-else-if="active === 'staging'" class="space-y-5 p-5">
                    <div class="flex gap-3 rounded-lg border border-maiic-200 bg-maiic-50 p-4 text-sm text-maiic-900">
                        <svg class="mt-0.5 h-5 w-5 flex-none text-maiic-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                        <div class="space-y-1">
                            <p class="font-semibold">How loans are staged</p>
                            <p>Each month-end, days past due are counted on the basis below and compared with the thresholds for the loan's facility class, under the Reserve Bank of Malawi directive for development finance institutions. The missed-instalment trigger and the cure period then apply.</p>
                            <p>These values are governed. They are changed only in the Governance Centre, by a proposal that a second person approves, from an effective date. A month is always staged on the values in force at its month-end, so re-running an earlier month keeps the basis it was staged on.</p>
                        </div>
                    </div>

                    <div>
                        <h4 class="maiic-section-title mt-0">Governed staging settings in force today</h4>
                        <div class="maiic-panel">
                            <div class="maiic-table-wrap">
                                <table class="maiic-table">
                                    <thead>
                                        <tr>
                                            <th>Setting</th>
                                            <th>Value in force</th>
                                            <th>In force since</th>
                                            <th>Approved by</th>
                                            <th>Pending changes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="g in staging.governed" :key="g.key">
                                            <td>
                                                <div class="font-semibold text-gray-900">{{ g.label }}</div>
                                                <div class="text-xs text-gray-500">{{ g.help }}</div>
                                            </td>
                                            <td>
                                                <span v-if="g.value" class="maiic-badge maiic-badge-green">{{ g.value }}</span>
                                                <span v-else class="maiic-badge maiic-badge-red">Not approved yet</span>
                                            </td>
                                            <td class="whitespace-nowrap">{{ g.effective_from ? sinceLabel(g.effective_from) : '-' }}</td>
                                            <td class="whitespace-nowrap">{{ g.approved_by || '-' }}</td>
                                            <td>
                                                <span v-if="g.pending" class="maiic-badge maiic-badge-gold">{{ g.pending }} awaiting approval or a later date</span>
                                                <span v-else class="maiic-badge maiic-badge-grey">None</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!staging.governed.length">
                                            <td colspan="5" class="maiic-empty">No governed staging settings were found. Open the Governance Centre to set them.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="maiic-section-title">Days-past-due thresholds by facility class</h4>
                        <div class="maiic-panel">
                            <div class="maiic-table-wrap">
                                <table class="maiic-table">
                                    <thead>
                                        <tr>
                                            <th>Facility class</th>
                                            <th class="num">Applies from tenor (months)</th>
                                            <th class="num">Stage 2 from (days)</th>
                                            <th class="num">Stage 3 from (days)</th>
                                            <th>Effective from</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="t in staging.thresholds" :key="t.id">
                                            <td class="font-semibold text-gray-900">{{ classLabel(t.facility_class) }}</td>
                                            <td class="num">{{ t.min_tenor_months }}</td>
                                            <td class="num">{{ t.stage2_dpd }}</td>
                                            <td class="num">{{ t.stage3_dpd }}</td>
                                            <td class="whitespace-nowrap">{{ t.in_force ? formatDate(t.effective_from) : 'Not set' }}</td>
                                            <td>
                                                <span v-if="t.in_force" class="maiic-badge maiic-badge-green">In force</span>
                                                <span v-else class="maiic-badge maiic-badge-gold">Proposal, not in force</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!staging.thresholds.length">
                                            <td colspan="6" class="maiic-empty">No thresholds are loaded. Staging then uses Stage 2 from 31 days and Stage 3 from 181 days.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">A loan uses the most specific row it qualifies for: its own facility class before "All facilities", then the longest tenor it meets. These rows are loaded with the system and are not edited on screen.</p>
                    </div>

                    <div class="flex justify-end">
                        <Link v-if="can('eir.govern')" :href="route('eir-governance.index')"
                              class="inline-flex items-center gap-2 rounded-md bg-maiic-600 px-4 py-2 text-sm font-semibold text-white hover:bg-maiic-700">
                            Change in the Governance Centre <font-awesome-icon icon="chevron-right" class="h-3 w-3"/>
                        </Link>
                        <p v-else class="text-xs text-gray-500">You need the governance right to propose a change. Ask an administrator.</p>
                    </div>
                </div>

                <!-- More -->
                <div v-else class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="item in visibleLinks" :key="item.route" :href="route(item.route)"
                          class="group flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-maiic-400 hover:shadow-md">
                        <span class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-maiic-50 text-maiic-600 group-hover:bg-maiic-600 group-hover:text-white">
                            <font-awesome-icon :icon="item.icon"/>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-bold text-gray-900 group-hover:text-maiic-700">{{ item.label }}</span>
                            <span class="mt-0.5 block text-sm text-gray-500">{{ item.description }}</span>
                        </span>
                    </Link>
                    <p v-if="!visibleLinks.length" class="maiic-empty sm:col-span-2 xl:col-span-3">You do not have access to any of these screens. Ask an administrator.</p>
                </div>
            </div>
        </div>
        <HelpManual/>
    </app-layout>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import HelpManual from '@/Components/HelpManual.vue'

// IFRS 9 ECL and EIR only. Settings inherited from the platform this system
// was cloned from (SMS gateway, loan application score bands, loan approval
// stages, banks, legal types, branches, chart of accounts, invoice fields,
// licence type, self registration, timezone, site online) are not shown:
// nothing in the IFRS 9 or EIR engines reads them. Their routes still exist.
const LINKS = [
    { route: 'lgd-calculations.index', icon: 'exchange-alt', label: 'LGD payment tracking', description: 'Payment tracking runs used to build the loss given default.', permission: 'settings' },
    { route: 'currencies.index', icon: 'dollar-sign', label: 'Currencies', description: 'The currency list the reporting currency is chosen from.', permission: 'currencies.index' },
    { route: 'accounting.financial_periods.index', icon: 'calendar', label: 'Financial periods', description: 'The financial year and the reporting periods.', permission: '' },
    { route: 'manuals.index', icon: 'book', label: 'Page help text', description: 'The help text shown on each screen.', permission: 'settings' },
    { route: 'license.index', icon: 'shield-alt', label: 'Licence', description: 'Check and verify the licence key for this installation.', permission: 'settings' },
    { route: 'eir-governance.index', icon: 'gavel', label: 'Governance Centre', description: 'Governed EIR, staging and model settings, changed under maker-checker.', permission: 'eir.govern' },
]

const TABS = [
    { key: 'organisation', label: 'Organisation', description: 'Name, contact details and logos shown in the app and on reports.' },
    { key: 'reporting', label: 'Reporting', description: 'The currency amounts are labelled in, and where the financial periods are kept.' },
    { key: 'email', label: 'Email', description: 'The mail server and sender used for password resets and new-user login details.' },
    { key: 'staging', label: 'Staging basis', description: 'How days past due are counted and the thresholds that set each stage. Read only here.' },
    { key: 'more', label: 'More settings', description: 'Other administration screens the IFRS 9 system uses.' },
]

const CLASS_LABELS = { DEFAULT: 'All facilities', LONG_TERM: 'Long term', MEGA_FARM: 'Mega Farm', SHORT_TERM: 'Short term' }

export default {
    components: { AppLayout, JetInputError, HelpManual, Link },
    props: {
        tab: { type: String, default: 'organisation' },
        organisation: Object,
        reporting: Object,
        currencies: Array,
        email: Object,
        staging: Object,
    },
    data() {
        return {
            active: this.tab,
            orgForm: this.$inertia.form({
                company_name: this.organisation.company_name,
                company_email: this.organisation.company_email,
                company_mobile: this.organisation.company_mobile,
                company_tel: this.organisation.company_tel,
                company_website: this.organisation.company_website,
                company_address: this.organisation.company_address,
                company_logo: null,
                company_small_logo: null,
            }),
            reportingForm: this.$inertia.form({
                currency: this.reporting.currency,
            }),
            emailForm: this.$inertia.form({
                mail_mailer: this.email.mail_mailer || 'smtp',
                mail_host: this.email.mail_host,
                mail_port: this.email.mail_port,
                mail_username: this.email.mail_username,
                mail_password: '',
                mail_encryption: this.email.mail_encryption,
                mail_from_address: this.email.mail_from_address,
                mail_from_name: this.email.mail_from_name,
            }),
        }
    },
    computed: {
        visibleLinks() {
            return LINKS.filter((item) => !item.permission || this.can(item.permission))
        },
        tabs() {
            const counts = {
                staging: this.staging.governed.length + this.staging.thresholds.length,
                more: this.visibleLinks.length,
            }
            return TABS.map((t) => ({ ...t, count: counts[t.key] ?? null }))
        },
        currentTab() {
            return this.tabs.find((t) => t.key === this.active) || this.tabs[0]
        },
        currentCurrency() {
            return this.currencies.find((c) => c.value === String(this.reporting.currency)) || null
        },
        dpdBasis() {
            const g = this.staging.governed.find((x) => x.key === 'dpd_basis')
            return g ? g.value : null
        },
    },
    watch: {
        tab(value) {
            this.active = value
        },
    },
    methods: {
        openTab(key) {
            this.active = key
            // Keep the tab in the address so a refresh or a shared link opens it;
            // Inertia's history state is kept as it is.
            try {
                const url = new URL(window.location.href)
                url.searchParams.set('tab', key)
                window.history.replaceState(window.history.state, '', url.toString())
            } catch (e) { /* no-op */ }
        },
        saveOrganisation() {
            this.orgForm.post(this.route('settings.general.update'), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    this.orgForm.company_logo = null
                    this.orgForm.company_small_logo = null
                },
            })
        },
        saveReporting() {
            this.reportingForm.post(this.route('settings.system.update'), { preserveScroll: true })
        },
        saveEmail() {
            this.emailForm.post(this.route('settings.email.update'), {
                preserveScroll: true,
                onSuccess: () => { this.emailForm.mail_password = '' },
            })
        },
        classLabel(value) {
            return CLASS_LABELS[value] || String(value || '').replace(/_/g, ' ').toLowerCase().replace(/^\w/, (c) => c.toUpperCase())
        },
        formatDate(value) {
            if (!value) return '-'
            const d = new Date(value + 'T00:00:00')
            return isNaN(d) ? value : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        },
        sinceLabel(value) {
            // A row dated 1900-01-01 is the value the system was installed with.
            return value <= '1901-01-01' ? 'From installation' : this.formatDate(value)
        },
    },
}
</script>
