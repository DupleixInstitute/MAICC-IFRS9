<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    company: { type: String, default: 'MAIIC' },
    generated_at: { type: String, default: '' },
})

// Table of contents drives both the sidebar and the scroll-spy.
const toc = [
    { id: 'overview', label: '1. System Overview', section: true },
    { id: 'login', label: '2. Logging In & Access' },
    { id: 'navigation', label: '3. Navigation Map' },
    { id: 'workflow', label: '4. The IFRS 9 Workflow', section: true },
    { id: 'w-setup', label: '4.1 Portfolio Setup', sub: true },
    { id: 'w-data', label: '4.2 Customer & Loan Data', sub: true },
    { id: 'w-collateral', label: '4.3 Collateral', sub: true },
    { id: 'w-model', label: '4.4 IFRS 9 Model Setup', sub: true },
    { id: 'w-ecl', label: '4.5 ECL Processing', sub: true },
    { id: 'w-reports', label: '4.6 Reports', sub: true },
    { id: 'howto-ecl', label: '5. How to Run an ECL Calculation', section: true },
    { id: 'howto-stress', label: '6. How to Run a Stress Test' },
    { id: 'reports-guide', label: '7. Reports Guide' },
    { id: 'dashboard', label: '8. The Dashboard' },
    { id: 'glossary', label: '9. Glossary', section: true },
    { id: 'faq', label: '10. Troubleshooting & FAQ' },
]

const active = ref('overview')
let observer = null

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) active.value = e.target.id
            })
        },
        { rootMargin: '-80px 0px -70% 0px', threshold: 0 }
    )
    toc.forEach((t) => {
        const el = document.getElementById(t.id)
        if (el) observer.observe(el)
    })
})
onBeforeUnmount(() => observer && observer.disconnect())

function go(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const glossary = [
    ['ECL', 'Expected Credit Loss — the probability-weighted estimate of credit losses, the core IFRS 9 output. ECL = EAD × PD × LGD.'],
    ['Stage 1', 'Performing. 12-month ECL is recognised. No significant increase in credit risk since origination.'],
    ['Stage 2', 'Underperforming. A Significant Increase in Credit Risk (SICR) has occurred; lifetime ECL is recognised.'],
    ['Stage 3', 'Credit-impaired (in default). Lifetime ECL on a credit-adjusted basis.'],
    ['PD', 'Probability of Default — likelihood a borrower defaults, over 12 months (Stage 1) or lifetime (Stage 2/3).'],
    ['LGD', 'Loss Given Default — the share of exposure not recovered after default, after collateral and recoveries.'],
    ['EAD', 'Exposure at Default — the expected balance at default: carrying amount + undrawn commitments × utilisation (CCF).'],
    ['CCF', 'Credit Conversion Factor — the fraction of an undrawn commitment expected to be drawn before default.'],
    ['SICR', 'Significant Increase in Credit Risk — the trigger that moves an exposure from Stage 1 to Stage 2.'],
    ['FLI', 'Forward-Looking Information — macro-economic scenarios and adjustments applied to PD (post-FLI PD).'],
    ['Coverage Ratio', 'ECL ÷ EAD — provision held as a percentage of exposure.'],
    ['NPL', 'Non-Performing Loan — RBM: Substandard + Doubtful + Loss (90+ days past due).'],
    ['DPD', 'Days Past Due — days an obligation has been in arrears; drives RBM classification.'],
    ['RBM Classification', 'Reserve Bank of Malawi prudential asset classification (Directive 2018): Pass, Special Mention, Substandard, Doubtful, Loss.'],
    ['HHI', 'Herfindahl–Hirschman Index — concentration measure; higher means more concentrated. >2,500 = highly concentrated.'],
    ['Internal Grade', 'MAIIC’s A–G master risk scale (A lowest risk … G default), mapped from the 12-month PD — used for DFI reporting.'],
    ['Transition Matrix', 'A matrix of probabilities of moving between stages/grades over a period; the basis for PD term structures.'],
    ['Reporting Period', 'The month-end snapshot of the loan book (YYYY-MM) that a calculation or report runs against.'],
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
                <div class="flex gap-8">

                    <!-- Sticky TOC -->
                    <aside class="hidden lg:block w-72 flex-shrink-0">
                        <nav class="sticky top-24 bg-white rounded-2xl shadow-sm border border-gray-100 p-4 max-h-[80vh] overflow-y-auto">
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
                    <article class="flex-1 min-w-0 bg-white rounded-2xl shadow-sm border border-gray-100 p-8 lg:p-12 space-y-12
                                    prose-headings:scroll-mt-24 max-w-none">

                        <header class="border-b border-gray-100 pb-6">
                            <p class="text-xs uppercase tracking-widest text-maiic-600 font-semibold">{{ company }}</p>
                            <h1 class="text-3xl font-bold text-gray-900 mt-1">IFRS 9 ECL System — User Manual</h1>
                            <p class="text-gray-500 mt-2">
                                A plain-language guide to running the IFRS 9 expected-credit-loss process end to end.
                                Generated {{ generated_at }}.
                            </p>
                        </header>

                        <section id="overview" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">1. System Overview</h2>
                            <p class="text-gray-700 leading-relaxed">
                                This system computes <strong>Expected Credit Loss (ECL)</strong> under IFRS 9 for
                                {{ company }}, a Development Finance Institution. It takes the loan book, classifies
                                every exposure into <strong>Stage 1, 2 or 3</strong>, estimates <strong>PD</strong>,
                                <strong>LGD</strong> and <strong>EAD</strong>, applies forward-looking macro
                                adjustments, and produces the ECL provision plus a full suite of regulatory and
                                management reports — including the Reserve Bank of Malawi (RBM) prudential
                                classification used for supervisory reporting.
                            </p>
                            <div class="mt-4 rounded-xl bg-maiic-50 border border-maiic-200 p-4 text-sm text-maiic-900">
                                <strong>The golden rule:</strong> ECL = EAD × PD × LGD, summed by stage and portfolio.
                                Every report is driven from the same loan-book snapshot, so the numbers reconcile.
                            </div>
                        </section>

                        <section id="login" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">2. Logging In & Access</h2>
                            <ol class="list-decimal pl-6 space-y-2 text-gray-700">
                                <li>Open the system URL in your browser.</li>
                                <li>Enter your email and password and select <strong>Log in</strong>.</li>
                                <li>What you can see depends on your <strong>role</strong>. Administrators see the full
                                    workflow and admin tools; other users see the screens relevant to their work.</li>
                                <li>Sign out from the user menu (top right) when you finish.</li>
                            </ol>
                            <figure class="mt-4 border border-dashed border-gray-300 rounded-xl p-8 text-center text-gray-400 text-sm">
                                [ Screenshot: login screen ]
                            </figure>
                        </section>

                        <section id="navigation" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">3. Navigation Map</h2>
                            <p class="text-gray-700">The left sidebar follows the IFRS 9 process top to bottom:</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Dashboard</strong> — headline ECL position and trends.</li>
                                <li><strong>Portfolio Setup</strong> — loan portfolios, sector types, product groups.</li>
                                <li><strong>Customer &amp; Loan Data</strong> — clients, loan book, imports, disbursements, exports.</li>
                                <li><strong>Collateral Management</strong> — collateral types and allocation.</li>
                                <li><strong>IFRS 9 Model Setup</strong> — staging &amp; SICR, PD model, LGD model, forward-looking, overlays.</li>
                                <li><strong>ECL Processing</strong> — run the ECL calculation.</li>
                                <li><strong>Reports</strong> — IFRS 9 Reports hub, Stress Testing, this manual.</li>
                                <li><strong>Analytics</strong> — early-warning and supporting analytics.</li>
                            </ul>
                        </section>

                        <section id="workflow" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">4. The IFRS 9 Workflow</h2>
                            <p class="text-gray-700">Work through the menu groups in order. Each step feeds the next.</p>

                            <h3 id="w-setup" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.1 Portfolio Setup</h3>
                            <p class="text-gray-700">Define <strong>Loan Portfolios</strong> (e.g. Agri-Inputs, Farm
                                Equipment, Irrigation, Agri Working Capital, Industrial), <strong>Sector Types</strong>
                                (RBM economic sectors) and <strong>Product Groups</strong>. ECL, PD and LGD are
                                computed per portfolio, so correct segmentation matters.</p>

                            <h3 id="w-data" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.2 Customer &amp; Loan Data</h3>
                            <p class="text-gray-700">Load clients and the monthly <strong>Loan Book</strong> via
                                <strong>Imports</strong>. Each row is a loan at a reporting period (YYYY-MM) with its
                                balance, stage, arrears and PD/LGD inputs. Use the export screens to reconcile.</p>

                            <h3 id="w-collateral" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.3 Collateral</h3>
                            <p class="text-gray-700">Register collateral types and allocate collateral to loans. The
                                discounted (forced-sale) value reduces LGD and net unsecured exposure.</p>

                            <h3 id="w-model" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.4 IFRS 9 Model Setup</h3>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700">
                                <li><strong>Staging &amp; SICR Rules</strong> — quantitative thresholds and qualitative SICR triggers that assign Stage 1/2/3.</li>
                                <li><strong>PD Model Setup</strong> — transition matrices (monthly &amp; cumulative) and internal grade profiles produce the PD term structure.</li>
                                <li><strong>LGD Model Setup</strong> — monthly and cumulative LGD from recovery/cure experience.</li>
                                <li><strong>Forward-Looking Model</strong> — macro elements, scenario profiles and weighted forecasts produce the post-FLI PD.</li>
                                <li><strong>Management Overlays</strong> — economic scenarios and external/expert adjustments.</li>
                            </ul>

                            <h3 id="w-ecl" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.5 ECL Processing</h3>
                            <p class="text-gray-700">With PD and LGD in place, run <strong>ECL Calculation</strong> for
                                the period — see Section 5 for the exact steps.</p>

                            <h3 id="w-reports" class="text-lg font-semibold text-gray-900 mt-6 scroll-mt-24">4.6 Reports</h3>
                            <p class="text-gray-700">The <strong>IFRS 9 Reports</strong> hub holds every regulatory and
                                management report; <strong>Stress Testing</strong> is a dedicated module; this manual
                                lives under Reports too.</p>
                        </section>

                        <section id="howto-ecl" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">5. How to Run an ECL Calculation</h2>
                            <ol class="list-decimal pl-6 space-y-2 text-gray-700">
                                <li>Confirm the loan book for the period is imported and PD/LGD are populated.</li>
                                <li>Go to <strong>ECL Processing → ECL Calculation</strong>.</li>
                                <li>Choose the <strong>level</strong> (portfolio or sector) and the specific
                                    portfolio/sector.</li>
                                <li>Select the <strong>reporting period</strong>, the <strong>PD type</strong>
                                    (pre-FLI or post-FLI) and the <strong>LGD type</strong>.</li>
                                <li>Select <strong>Calculate</strong>. The system writes ECL per loan and aggregates
                                    by stage; the period is marked calculated.</li>
                                <li>Review results on the <strong>Dashboard</strong> and in the reports.</li>
                            </ol>
                            <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                                A calculation runs in one database transaction and locks the period/scope, so two
                                people cannot corrupt the same run.
                            </div>
                        </section>

                        <section id="howto-stress" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">6. How to Run a Stress Test</h2>
                            <ol class="list-decimal pl-6 space-y-2 text-gray-700">
                                <li>Go to <strong>Reports → Stress Testing</strong>.</li>
                                <li>Pick the reporting period and (optionally) a portfolio.</li>
                                <li>Set a <strong>PD multiplier</strong> and <strong>LGD add-on</strong> per stage, or
                                    apply a preset (e.g. <em>Severe drought</em>).</li>
                                <li>Select <strong>Run Stress Test</strong> to see base vs stressed ECL by stage and
                                    portfolio.</li>
                                <li><strong>Save</strong> the scenario to reload it later.</li>
                            </ol>
                        </section>

                        <section id="reports-guide" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">7. Reports Guide</h2>
                            <p class="text-gray-700">In the IFRS 9 Reports hub, pick a period at the top; every table
                                paginates (10/row) and exports to CSV or PDF. Categories:</p>
                            <ul class="list-disc pl-6 space-y-1 text-gray-700 mt-2">
                                <li><strong>Core ECL</strong> — Executive Summary, ECL by Stage, by Portfolio, by Sector, by Product Group, by Internal Grade, Portfolio Trend, Account-Level trail.</li>
                                <li><strong>Staging &amp; Movement</strong> — SICR triggers, stage migration, Opening→Closing ECL reconciliation, ECL charge/release.</li>
                                <li><strong>Model Components</strong> — PD, LGD &amp; collateral, EAD.</li>
                                <li><strong>Forward-Looking</strong> — macro scenarios, scenario-weighted ECL.</li>
                                <li><strong>RBM Prudential</strong> — RBM classification, IFRS 9 vs RBM, NPL &amp; arrears, provision comparison, concentration.</li>
                                <li><strong>Disclosure &amp; Audit</strong> — financial-statement disclosure, audit &amp; data quality.</li>
                            </ul>
                            <p class="text-gray-700 mt-3">The <strong>Audit &amp; Data Quality</strong> report hard-flags
                                missing sector tags or unmapped portfolios — fix those before relying on the numbers.</p>
                        </section>

                        <section id="dashboard" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">8. The Dashboard</h2>
                            <p class="text-gray-700">The Dashboard shows headline KPIs (EAD, ECL, coverage, Stage 3,
                                weighted PD/LGD), the stage breakdown, and charts you can switch between
                                <strong>Chart</strong> and <strong>Table</strong> view. Change the
                                <strong>reporting period</strong> at the top right to move through time.</p>
                            <figure class="mt-4 border border-dashed border-gray-300 rounded-xl p-8 text-center text-gray-400 text-sm">
                                [ Screenshot: dashboard with KPI cards and charts ]
                            </figure>
                        </section>

                        <section id="glossary" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-4">9. Glossary</h2>
                            <dl class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                                <div v-for="[term, def] in glossary" :key="term"
                                     class="grid grid-cols-1 sm:grid-cols-4 gap-2 px-4 py-3 odd:bg-maiic-50/40">
                                    <dt class="font-semibold text-maiic-800">{{ term }}</dt>
                                    <dd class="sm:col-span-3 text-gray-700 text-sm">{{ def }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section id="faq" class="scroll-mt-24">
                            <h2 class="text-2xl font-bold text-gray-900 mb-3">10. Troubleshooting & FAQ</h2>
                            <div class="space-y-4 text-gray-700">
                                <div>
                                    <p class="font-semibold">A report is empty.</p>
                                    <p class="text-sm">The period has no calculated ECL, or PD/LGD are not populated.
                                        Run the ECL calculation for that period first, and check Audit &amp; Data Quality.</p>
                                </div>
                                <div>
                                    <p class="font-semibold">Numbers differ between two reports.</p>
                                    <p class="text-sm">Confirm both are on the same reporting period and scope. NPL by
                                        DPD equals Stage 3 by design; if not, the period needs recalculation.</p>
                                </div>
                                <div>
                                    <p class="font-semibold">“An ECL calculation is already running.”</p>
                                    <p class="text-sm">Another user is calculating the same period/scope. Wait for it
                                        to finish, then retry.</p>
                                </div>
                            </div>
                        </section>

                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
