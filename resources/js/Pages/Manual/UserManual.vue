<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    company: { type: String, default: 'MAIIC' },
    generated_at: { type: String, default: '' },
})

// MAIIC logo: drop a file at public/images/maiic-logo.png and it shows
// automatically; otherwise the styled wordmark is used.
const logoOk = ref(true)

const toc = [
    { id: 'introduction', label: '1. System Overview', section: true },
    { id: 'features', label: '1.1 Key Features', sub: true },
    { id: 'login', label: '2. Logging In & Access' },
    { id: 'security', label: '3. Password & 2FA' },
    { id: 'help', label: '4. In-System Assistance' },
    { id: 'dashboard', label: '5. Dashboard', section: true },
    { id: 'workspace', label: '6. Period Workspace' },
    { id: 'clients', label: '7. Clients' },
    { id: 'portfolios', label: '8. Loan Portfolios' },
    { id: 'loanbook', label: '9. Loan Book & Imports' },
    { id: 'tprofiles', label: '10. Transition Profiles', section: true },
    { id: 'tmatrix', label: '11. Transition Matrix (PD)' },
    { id: 'lgd', label: '12. Loss Given Default' },
    { id: 'fli', label: '13. Forward-Looking Engine' },
    { id: 'ecl', label: '14. ECL Calculation' },
    { id: 'reports', label: '15. Reports & Stress Testing', section: true },
    { id: 'settings', label: '16. Manuals & Settings' },
    { id: 'glossary', label: '17. Glossary', section: true },
    { id: 'faq', label: '18. Troubleshooting & FAQ' },
]

const active = ref('introduction')
let observer = null
onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => entries.forEach((e) => { if (e.isIntersecting) active.value = e.target.id }),
        { rootMargin: '-90px 0px -70% 0px', threshold: 0 }
    )
    toc.forEach((t) => { const el = document.getElementById(t.id); if (el) observer.observe(el) })
})
onBeforeUnmount(() => observer && observer.disconnect())
function go(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }

const portfolios = [
    ['MAIIC Core', 'Agricultural & industrial term loans funded by MAIIC; commercial rates (typically >32%).'],
    ['FinES', 'Concessional World Bank–funded loans at a 10% rate; separate risk-return profile.'],
    ['Mega Farm (retail-agri)', 'Loans to individual farmers for seed, fertiliser, pesticides, equipment & irrigation.'],
    ['Agri-Inputs', 'Seed / Fertilizer / Pesticide loans — seasonal smallholder input finance.'],
    ['Farm Equipment / Irrigation', 'Asset-backed agricultural lending.'],
    ['Agri Working Capital / Industrial', 'Working-capital and industrial (FinES/MAIIC) facilities.'],
]
const glossary = [
    ['ECL', 'Expected Credit Loss — the probability-weighted credit loss. ECL = EAD × PD × LGD.'],
    ['Stage 1', 'Performing. 12-month ECL. No significant increase in credit risk since origination.'],
    ['Stage 2', 'Underperforming. A Significant Increase in Credit Risk (SICR) has occurred; lifetime ECL.'],
    ['Stage 3', 'Credit-impaired / in default. Lifetime ECL on a credit-adjusted basis.'],
    ['PD', 'Probability of Default — 12-month (Stage 1) or lifetime (Stage 2/3).'],
    ['LGD', 'Loss Given Default — share of exposure not recovered after default & collateral.'],
    ['EAD', 'Exposure at Default — carrying amount + undrawn commitments × utilisation (CCF).'],
    ['SICR', 'Significant Increase in Credit Risk — trigger that moves Stage 1 → Stage 2.'],
    ['FLI', 'Forward-Looking Information — macro scenarios/regression producing the post-FLI PD.'],
    ['Transition Matrix', 'Probabilities of moving between stages over a horizon; the basis for PD.'],
    ['Transition Profile', 'User-defined configuration (tables, grading columns, count/balance) driving the matrix.'],
    ['Cure Rate', 'Share of Stage-3 exposure restored to performing status.'],
    ['Recovery Rate', 'Share of defaulted exposure recovered.'],
    ['RBM Classification', 'Reserve Bank of Malawi prudential classes: Pass, Special Mention, Substandard, Doubtful, Loss.'],
    ['NPL', 'Non-Performing Loan — Substandard + Doubtful + Loss (90+ days past due).'],
    ['DPD', 'Days Past Due — drives RBM classification.'],
    ['HHI', 'Herfindahl–Hirschman Index — concentration measure (>2,500 = highly concentrated).'],
    ['Internal Grade', "MAIIC's A–G master risk scale (A = lowest risk … G = default), mapped from 12-month PD."],
    ['Reporting Period', 'The month-end loan-book snapshot (YYYY-MM) a calculation or report runs against.'],
]
const faqs = [
    ['A report is empty.', 'The period has no calculated ECL, or PD/LGD are not yet populated. Run the ECL calculation for that period and check the Audit & Data-Quality report.'],
    ['Numbers differ between two reports.', 'Confirm both are on the same reporting period and scope. NPL by DPD equals Stage 3 by design; if not, recalculate the period.'],
    ['“An ECL calculation is already running.”', 'Another user is calculating the same period/scope. The system locks each run; wait for it to finish and retry.'],
    ['I cannot edit a transition matrix / LGD.', 'It is Closed (locked) for audit integrity. Closed records are immutable; create a new draft if a change is required.'],
    ['A login fails repeatedly.', 'Passwords are case-sensitive. After several failed attempts contact your administrator to unlock the account.'],
]
</script>

<template>
    <AppLayout title="User Manual">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Manual</h2>
                <a :href="route('manual.pdf')"
                   class="inline-flex items-center px-4 py-2 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    Download PDF
                </a>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-10">

                <!-- MAIIC-branded hero -->
                <div class="rounded-2xl overflow-hidden shadow-lg mb-6"
                     style="background: linear-gradient(135deg,#14532d 0%,#166534 55%,#16a34a 100%)">
                    <div class="flex flex-wrap items-center justify-between gap-6 p-8">
                        <div class="flex items-center gap-4">
                            <img v-if="logoOk" src="/images/maiic-logo.png" alt="MAIIC"
                                 class="h-16 w-auto bg-white/95 rounded-lg p-2" @error="logoOk=false"/>
                            <div v-else class="flex items-center gap-3">
                                <div class="flex flex-col gap-1">
                                    <span class="block w-9 h-5 rounded-sm" style="background:#16a34a;transform:rotate(-12deg)"></span>
                                    <span class="block w-12 h-1.5 rounded-full" style="background:#f59e0b"></span>
                                    <span class="block w-12 h-1.5 rounded-full" style="background:#d1242f"></span>
                                </div>
                            </div>
                            <div>
                                <p class="text-white font-extrabold text-2xl tracking-tight">MAIIC</p>
                                <p class="text-maiic-100 text-xs">Malawi Agricultural &amp; Industrial Investment Corporation plc</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-widest text-amber-300 font-semibold">IFRS 9 ECL System</p>
                            <h1 class="text-3xl font-bold text-white mt-1">User Manual</h1>
                            <p class="text-maiic-100 text-sm mt-1">Generated {{ generated_at }} · {{ company }}</p>
                        </div>
                    </div>
                    <div class="h-1.5 flex">
                        <div class="flex-1" style="background:#16a34a"></div>
                        <div class="flex-1" style="background:#f59e0b"></div>
                        <div class="flex-1" style="background:#d1242f"></div>
                        <div class="flex-1" style="background:#111827"></div>
                    </div>
                </div>

                <div class="flex gap-8">
                    <!-- Sticky TOC -->
                    <aside class="hidden lg:block w-72 flex-shrink-0">
                        <nav class="sticky top-24 bg-white rounded-2xl shadow-sm border border-gray-100 p-4 max-h-[82vh] overflow-y-auto">
                            <p class="text-xs font-bold uppercase tracking-widest text-maiic-700 mb-3">Contents</p>
                            <button v-for="t in toc" :key="t.id" @click="go(t.id)"
                                    :class="['block w-full text-left rounded-md px-3 py-1.5 mb-0.5 text-sm transition',
                                             t.section ? 'font-semibold uppercase text-xs tracking-wide mt-3' : '',
                                             t.sub ? 'pl-6 text-[13px]' : '',
                                             active === t.id ? 'bg-maiic-600 text-white' : 'text-gray-600 hover:bg-maiic-50']">
                                {{ t.label }}
                            </button>
                        </nav>
                    </aside>

                    <!-- Content -->
                    <article class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm border border-gray-100 p-8 lg:p-12 space-y-12 max-w-none">

                        <!-- 1 -->
                        <section id="introduction" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">1. System Overview</h2>
                            <p class="text-gray-700 leading-relaxed">
                                The MAIIC IFRS 9 ECL System is a specialised financial-reporting and credit-risk
                                application that automates Expected Credit Loss (ECL) measurement under
                                <strong>IFRS 9</strong> for {{ company }}. It replaces the legacy Excel model with a
                                secure, database-driven platform that scales with MAIIC's growing book — traditional
                                term loans, the World-Bank–funded FinES programme, and the new Mega Farm retail-agri
                                portfolio.
                            </p>
                            <div class="mt-4 rounded-xl bg-maiic-50 border border-maiic-200 p-4 text-sm text-maiic-900">
                                <strong>The golden rule:</strong> ECL = EAD × PD × LGD, computed at account level and
                                aggregated by stage and portfolio. Every report is driven from the same loan-book
                                snapshot, so the numbers reconcile.
                            </div>
                            <h3 id="features" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">1.1 Key Features</h3>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Automated ECL</strong> across Stage 1/2/3, historical & forward-looking.</li>
                                <li><strong>System or manual</strong> inputs for PD, LGD and FLI assumptions.</li>
                                <li><strong>Audit-ready</strong>: locking, immutable closed periods, full activity log.</li>
                                <li><strong>Regulatory</strong>: RBM prudential classification & disclosure reports.</li>
                                <li><strong>Risk insights</strong>: dashboards, AI commentary, early-warning, stress testing.</li>
                            </ul>
                        </section>

                        <!-- 2 -->
                        <section id="login" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">2. Logging In &amp; Access</h2>
                            <ol class="list-decimal pl-6 space-y-2 text-gray-700">
                                <li>Open the system URL in your browser.</li>
                                <li>Enter your registered <strong>email</strong> and <strong>password</strong> (case-sensitive) and select <strong>Sign in</strong>.</li>
                                <li>Use <strong>Forgot password</strong> to receive a reset link by email.</li>
                                <li>What you see depends on your <strong>role</strong> (administrator vs standard user).</li>
                            </ol>
                            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                                <strong>Troubleshooting:</strong> verify credentials; clear cache or try another browser
                                if the page fails to load; after repeated failures contact your administrator to unlock the account.
                            </div>
                            <figure class="mt-4 border border-dashed border-gray-300 rounded-xl p-8 text-center text-gray-400 text-sm">[ Screenshot: login screen ]</figure>
                        </section>

                        <!-- 3 -->
                        <section id="security" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">3. Password Update &amp; Two-Factor Authentication</h2>
                            <p class="text-gray-700">On first login you are required to replace the temporary password. Choose a strong password (minimum 8 characters, with uppercase, numbers and special characters; avoid familiar words).</p>
                            <p class="text-gray-700 mt-2"><strong>Two-Factor Authentication (2FA)</strong> adds a one-time token from your mobile device. Open your profile, select <strong>Enable</strong> under Two-Factor Authentication and follow the on-screen steps. 2FA is strongly recommended for all finance and admin users.</p>
                        </section>

                        <!-- 4 -->
                        <section id="help" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">4. In-System Assistance</h2>
                            <p class="text-gray-700">A green <strong>?</strong> help button is available on every screen. It opens contextual help for the module in use, so users resolve questions without leaving the system. This full manual is always available from <strong>Reports → User Manual</strong>, with a one-click <strong>Download PDF</strong>.</p>
                        </section>

                        <!-- 5 -->
                        <section id="dashboard" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">5. Dashboard</h2>
                            <p class="text-gray-700">The dashboard is the real-time hub. Use the prominent <strong>Reporting Period</strong> selector (top right) to move through time.</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>KPI cards</strong>: Total Exposure (EAD), Total ECL, ECL Coverage, Stage 3 Exposure, Weighted PD, Weighted LGD — with prior-period change.</li>
                                <li><strong>Stage breakdown</strong> (Stage 1/2/3) with EAD, ECL and PD.</li>
                                <li><strong>Charts</strong> (composition & ECL trend) with a <strong>Chart / Table</strong> toggle.</li>
                                <li><strong>Portfolio summary</strong> table of headline metrics.</li>
                            </ul>
                            <figure class="mt-4 border border-dashed border-gray-300 rounded-xl p-8 text-center text-gray-400 text-sm">[ Screenshot: dashboard ]</figure>
                        </section>

                        <!-- 6 -->
                        <section id="workspace" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">6. Period Workspace</h2>
                            <p class="text-gray-700">The Workspace is the IFRS 9 <strong>period-close checklist</strong>: import → segmentation → staging → PD → LGD → FLI → run ECL → review reports → stress test → sign-off, with a progress bar and deep links to each screen.</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Role-aware:</strong> administrators tick steps (recorded with who &amp; when); others see a live read-only view.</li>
                                <li><strong>Team messages:</strong> an in-system message board per reporting period.</li>
                            </ul>
                        </section>

                        <!-- 7 -->
                        <section id="clients" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">7. Clients</h2>
                            <p class="text-gray-700">Manage borrower records that underpin loan assessment and risk profiling. The list shows Customer ID, Name, Phone and last-updated, with filter &amp; search. Add clients via <strong>bulk CSV import</strong> (Clients → Import; download the sample file for the exact format) or <strong>create one manually</strong> (Customer ID, Name, Phone in 07XXXXXXXX format).</p>
                        </section>

                        <!-- 8 -->
                        <section id="portfolios" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">8. Loan Portfolios</h2>
                            <p class="text-gray-700">Portfolios organise exposures so PD, LGD and ECL are computed per segment — no blended assumptions. Create a portfolio with a Name, Description and Active toggle. MAIIC's segments:</p>
                            <dl class="mt-3 divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                                <div v-for="[k,v] in portfolios" :key="k" class="grid grid-cols-1 sm:grid-cols-4 gap-2 px-4 py-3 odd:bg-maiic-50/40">
                                    <dt class="font-semibold text-maiic-800">{{ k }}</dt>
                                    <dd class="sm:col-span-3 text-gray-700 text-sm">{{ v }}</dd>
                                </div>
                            </dl>
                        </section>

                        <!-- 9 -->
                        <section id="loanbook" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">9. Loan Book &amp; Imports</h2>
                            <p class="text-gray-700">The loan book holds every loan at a reporting period with balance, due date, overdue days and IFRS 9 stage. Filter by Year, Month and Status; search by Contract ID or Customer.</p>
                            <p class="text-gray-700 mt-2"><strong>Importing:</strong> choose the <strong>Portfolio Group</strong> (pulled from Loan Portfolios) and the <strong>Reporting Period</strong>, then upload the CSV. The <strong>Imports Activity Log</strong> records every upload — status (Completed / In Progress / Failed), rows inserted, exception records, and start/finish/duration — for audit, reconciliation and troubleshooting.</p>
                            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900"><strong>Tip:</strong> failed files do not proceed to staging. Cross-check column names and data types against the import spec, fix, and re-upload.</div>
                        </section>

                        <!-- 10 -->
                        <section id="tprofiles" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">10. Transition Profiles (PD logic)</h2>
                            <p class="text-gray-700">A transition profile defines <em>how</em> PD is measured. You set a Profile Code &amp; Short Name, the mapped <strong>Start</strong> and <strong>End</strong> tables, the client &amp; grading columns, the value type (Text/Numeric), and the <strong>Aggregation Criteria</strong>:</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Count</strong> — PD from the number of loans transitioning.</li>
                                <li><strong>Balance</strong> — PD from exposure amounts (capital-at-risk view).</li>
                            </ul>
                            <p class="text-gray-700 mt-2">The configuration screen lets you re-order stage categories (drag &amp; drop, e.g. Stage 1, 2, 3, Paid) and set a <strong>default stage</strong> fallback. Profiles are fully user-defined and reusable — the engine is flexible by design.</p>
                        </section>

                        <!-- 11 -->
                        <section id="tmatrix" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">11. Transition Matrix (PD)</h2>
                            <p class="text-gray-700"><strong>Monthly Probability:</strong> select a transition profile, start &amp; end periods, portfolio group and calculation source (System or Manual). Proceed to matrix entry; the system computes stage-to-stage probabilities and total start balances per stage.</p>
                            <p class="text-gray-700 mt-2"><strong>Cumulative Probability:</strong> aggregates transitions across many periods for a holistic, long-horizon PD.</p>
                            <p class="text-gray-700 mt-2">Each matrix has actions: <strong>View</strong> (full matrix &amp; PD%), <strong>Edit</strong> (Draft only), <strong>Recalculate</strong>, <strong>Lock</strong> (status → Closed, immutable), then the <strong>Book</strong> action to apply the PD results to the loan book for a chosen period.</p>
                            <div class="mt-3 rounded-xl bg-maiic-50 border border-maiic-200 p-3 text-sm text-maiic-900">Locking preserves audit integrity — closed matrices cannot be altered. This resolves prior grade-averaging audit findings.</div>
                        </section>

                        <!-- 12 -->
                        <section id="lgd" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">12. Loss Given Default (LGD)</h2>
                            <p class="text-gray-700"><strong>Monthly</strong> and <strong>Cumulative</strong> LGD, each with <strong>System</strong> (auto from Stage-3 cohort, cures &amp; recoveries) or <strong>Manual</strong> (enter Start/End Stage 3, cured, partially/fully recovered, disbursed) modes.</p>
                            <p class="text-gray-700 mt-2">LGD reflects MAIIC's reality: <strong>proportional multi-collateral allocation</strong> with forced-sale discounting, and an <strong>agri credit-enhancement model</strong> (off-take / warehouse-receipt / group-cooperative guarantee / AIP backing) rather than assuming conventional real estate. Records lock to Closed and apply to the loan book.</p>
                        </section>

                        <!-- 13 -->
                        <section id="fli" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">13. Forward-Looking (FLI) Engine</h2>
                            <p class="text-gray-700">The FLI engine incorporates macro-economic information into PD. Define <strong>macro elements</strong> and <strong>scenario profiles</strong> (base / upside / downside) with probability weights, generate the <strong>weighted forecast</strong>, and run a <strong>regression</strong> (trained model or manual slope/intercept) that converts a macro shock into a PD adjustment, producing the <strong>post-FLI PD</strong>.</p>
                            <p class="text-gray-700 mt-2">For Malawi agri, rainfall/drought, FX and input prices are natural drivers. ECL can be run pre- or post-FLI; the Sensitivity report exposes the macro → PD → ECL path interactively.</p>
                        </section>

                        <!-- 14 -->
                        <section id="ecl" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">14. ECL Calculation</h2>
                            <ol class="list-decimal pl-6 space-y-2 text-gray-700">
                                <li>Ensure the period's loan book is imported and PD/LGD are populated.</li>
                                <li>Go to <strong>ECL Processing → ECL Calculation</strong>.</li>
                                <li>Choose level (portfolio/sector), the portfolio/sector, the reporting period, the PD type (pre/post-FLI) and LGD type.</li>
                                <li>Select <strong>Calculate</strong>. The engine writes ECL per loan, aggregates by stage, and marks the period calculated.</li>
                                <li>Review on the Dashboard and in the reports; export from the Expected Credit Loss screen.</li>
                            </ol>
                            <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900"><strong>Integrity:</strong> a run executes in one database transaction with a per-scope lock — two users cannot corrupt the same run, and a partial failure rolls back cleanly.</div>
                        </section>

                        <!-- 15 -->
                        <section id="reports" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">15. Reports &amp; Stress Testing</h2>
                            <p class="text-gray-700">Open <strong>Reports → IFRS 9 Reports</strong>, pick a period; every table paginates (10/page) and exports to <strong>CSV or PDF</strong>. Categories:</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Core ECL</strong> — Executive Summary, ECL by Stage / Portfolio / Sector / Product Group / Internal Grade, Account-level trail, Portfolio trend.</li>
                                <li><strong>Staging &amp; Movement</strong> — SICR trigger, Stage migration, Opening→Closing ECL reconciliation, ECL charge/release.</li>
                                <li><strong>Model Components</strong> — PD, LGD &amp; Collateral, EAD, Credit Risk Mitigation (agri).</li>
                                <li><strong>Forward-Looking</strong> — Macro scenario, Scenario-weighted ECL.</li>
                                <li><strong>RBM Prudential</strong> — RBM classification, IFRS 9 vs RBM, NPL &amp; arrears, Provision comparison, Concentration, Cooperative linkage.</li>
                                <li><strong>Disclosure &amp; Audit</strong> — FS disclosure note tables, Audit &amp; Data-Quality (hard-flags missing sector / unmapped portfolio).</li>
                                <li><strong>Analytics</strong> — AI Executive Commentary, Early Warning System.</li>
                            </ul>
                            <p class="text-gray-700 mt-2"><strong>Stress Testing</strong> is a dedicated module under Reports: per-stage PD multipliers &amp; LGD add-ons, agri presets (drought, FX/input shock), base-vs-stressed ECL by stage &amp; portfolio, and save/reload of named scenarios.</p>
                        </section>

                        <!-- 16 -->
                        <section id="settings" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">16. Manuals &amp; Settings</h2>
                            <p class="text-gray-700">Administrators configure the system under <strong>Settings</strong> — organisation, system, email/SMS, and <strong>Manual Settings</strong>. Contextual in-system manuals (the green ? help) can be created and edited per module: give a Title, pick the Route, write rich content, and Save — it links automatically to that screen.</p>
                        </section>

                        <!-- 17 -->
                        <section id="glossary" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-4">17. Glossary</h2>
                            <dl class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                                <div v-for="[term, def] in glossary" :key="term"
                                     class="grid grid-cols-1 sm:grid-cols-4 gap-2 px-4 py-3 odd:bg-maiic-50/40">
                                    <dt class="font-semibold text-maiic-800">{{ term }}</dt>
                                    <dd class="sm:col-span-3 text-gray-700 text-sm">{{ def }}</dd>
                                </div>
                            </dl>
                        </section>

                        <!-- 18 -->
                        <section id="faq" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 border-l-4 border-maiic-600 pl-3 mb-3">18. Troubleshooting &amp; FAQ</h2>
                            <div class="space-y-4 text-gray-700">
                                <div v-for="[q, a] in faqs" :key="q">
                                    <p class="font-semibold text-gray-900">{{ q }}</p>
                                    <p class="text-sm">{{ a }}</p>
                                </div>
                            </div>
                            <div class="mt-6 h-1.5 flex rounded-full overflow-hidden">
                                <div class="flex-1" style="background:#16a34a"></div>
                                <div class="flex-1" style="background:#f59e0b"></div>
                                <div class="flex-1" style="background:#d1242f"></div>
                                <div class="flex-1" style="background:#111827"></div>
                            </div>
                            <p class="text-xs text-gray-400 mt-3">MAIIC IFRS 9 ECL System · Prepared by Dupleix Institute · {{ generated_at }}</p>
                        </section>

                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
