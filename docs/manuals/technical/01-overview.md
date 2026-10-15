## 1. Overview

### 1.1 Purpose and audience

This Technical Manual describes how the MAIIC IFRS 9 Expected Credit Loss (ECL) and Effective Interest Rate (EIR) platform is built, how its engines calculate, how data flows through it and how it is operated. It is written for the MAIIC ICT team who will host and maintain the system, for Dupleix Institute engineers who support it, and for auditors who need to trace a reported figure back to the code that produced it.

Two companion documents cover what this manual does not. The Administrator Manual (in the help centre under System Documentation) explains the administration screens step by step. The Installation and Configuration Guide covers server preparation, installation, environment settings and upgrades. The User Manual covers the analyst workflow.

### 1.2 Document control

| Item | Value |
|---|---|
| Document | MAIIC IFRS 9 Platform Technical Manual |
| Contract reference | Implementation, Licence and Support Agreement, Schedule 1, deliverable 6 |
| Version | 1.0 |
| Status | Draft for MAIIC review |
| Prepared by | Dupleix Institute (Pty) Ltd |
| Document owner | MAIIC Head of ICT |
| Source | `docs/manuals/technical` in the application repository, rendered live in the application and exported to PDF from the same files |

Because the chapters live in the repository next to the code, every code change that alters behaviour is expected to update the matching chapter in the same commit. The revision date shown on the in-app page and the PDF cover is the newest modification date of the chapter files.

### 1.3 The platform at a glance

The platform is one Laravel application with a Vue single-page front end. Below Dashboard, Workspace and Reports, the sidebar groups the screens in four blocks: Data foundation, Financial modelling, Risk & regulatory, and Administration & support (chapter 2.3). It carries the solution components listed in Schedule 1 of the agreement:

| Component | Where it lives in the application |
|---|---|
| User and access management | Administration & support, Access & Setup: Users & Roles (Users, Roles & Permissions) |
| Data import and onboarding | Portfolio Setup (Loan Portfolios, Product Groups, Sector Types); Customer & Loan Data: Clients, Loan Book, Imports & Feeds (Imports, E-Banker Loads, Loan Book Builds, Periods Built, Query Register, Take-on Blocks, Take-on Population); EIR & Revenue Recognition: EIR Data. Every import offers a sample file in the exact format, a column guide and a Check file that refuses a file with any error before anything is written (chapter 4) |
| Collateral module | Collateral Management (Collateral Register, Collateral Allocation, Collateral Types) |
| Internal grading | IFRS 9 Model Setup: PD Model, Internal Grades (grading profiles, including the S&P grades of the ECL model workbook imported as a draft profile) |
| PD module | IFRS 9 Model Setup: PD Model (Transition Profiles, Monthly Probability, Cumulative Probability) |
| LGD module | IFRS 9 Model Setup: LGD Model (Monthly LGD, Cumulative LGD), and the discounting of recoveries |
| EAD module | Carrying amount plus undrawn commitments times utilisation, under one rule (`app/Services/Ecl/EclExposure.php`) used by the ECL engines and reports; nil for a loan written off to nil |
| EIR module | EIR & Revenue Recognition: EIR Data (Contract Master, Cash Flows, Drawdowns, Reference Rates, Trial Balances, Write-offs, Schedule Review), Restructures & Resets, Accounting Rules, Fee Classification, EIR Calculations (Calculations, Blockers, Coverage by Portfolio, Largest Facilities, EIR as at a Date), GL Reconciliation; the governed settings in Administration & support, Governance, Governance Centre |
| Forward-Looking Information | Forward-Looking Information: FLI Month-End (Overview, Impact Analysis), Macro Statistics, Forward-Looking Model (Credit Loss Data, Correlation Finder, Regression, Model Versions, Adjusted Forecast, Weighted Forecast), Scenarios & Overlays (Scenario Profiles, Scenario Sets, Economic Scenarios, Manual Overlays, External Calculations, Calculation History) |
| SICR and staging engine | IFRS 9 Model Setup: Staging & SICR Rules (SICR Groups, SICR Alert Items, SICR Trigger Alerts), and the staging classifier applied on import |
| ECL engine | ECL Processing: ECL Calculation (with the time-phased projections; the booked allowance is the discounted ECL), Mega Farm Programme (measured by its own engine and kept out of the main PD, LGD and ECL); stress testing from the Reports hub |
| Reports | Reports: the IFRS 9 reports hub, from which every report, reconciliation, disbursement report, the RBM return, the auditor pack and every Excel and PDF export is reached; the reports carry the booked (discounted) ECL |
| Audit trail | Administration & support, Governance: Audit (Audit Trail, Audit Trace, Compliance Audits); the Audit Trail reads both the activity log and the custom audit log |
| Dashboard | Dashboard with reporting period, portfolio and compare-to filters; Workspace for the period close checklist |
| Support and documentation | Administration & support, Support & Maintenance: Support Tickets, and System Documentation (User Manual, Administrator Manual, Technical Manual (this manual), Installation Guide) |
| Operations | Administration & support: Access & Setup (Financial Periods, Settings) and Support & Maintenance (Maintenance: the month-end and system jobs run from the browser, chapter 9) |

### 1.4 Technology stack

| Layer | Technology | Version (from `composer.json` and `package.json`) |
|---|---|---|
| Language | PHP | 8.1 or later required; 8.2 in production and development |
| Framework | Laravel | 10.x (`laravel/framework`) |
| Authentication | Laravel Jetstream with Fortify | Jetstream 3.x, Fortify 1.x |
| Authorisation | spatie/laravel-permission | 5.x |
| Front-end bridge | Inertia.js (`inertiajs/inertia-laravel`, `@inertiajs/vue3`) | 0.6.x on the server, 1.x in the browser |
| Front end | Vue | 3.x with the Composition API |
| Styling | Tailwind CSS with the forms and typography plugins | 3.x |
| Build | Vite | 4.x |
| Charts | Chart.js | 3.x |
| PDF | barryvdh/laravel-dompdf (DomPDF) | 2.x |
| Excel | maatwebsite/excel over PhpSpreadsheet | 3.x |
| Data tables | yajra/laravel-datatables | 10.x |
| Audit | spatie/laravel-activitylog plus the application's own `audit_logs` table | 4.x |
| Markdown | league/commonmark (used to render this manual) | 2.x |
| Screenshots | puppeteer with its bundled Chromium | 25.x |
| Database | MySQL 8 or MariaDB 10.4 and later | |
| Queue | Laravel database queue driver | |

Exact versions are pinned in `composer.lock` and `package-lock.json`; the tables above give the major lines so a reader knows which upstream documentation applies.
