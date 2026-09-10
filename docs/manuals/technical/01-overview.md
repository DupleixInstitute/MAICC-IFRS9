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

The platform is one Laravel application with a Vue single-page front end. It carries the solution components listed in Schedule 1 of the agreement:

| Component | Where it lives in the application |
|---|---|
| User and access management | Administration: User Management, Roles and Permissions |
| Data import and onboarding | Customer and Loan Data: Loan Book, Clients, Imports; EIR and Revenue Recognition: EIR Data |
| Collateral module | Collateral Management: Register, Types, Allocation |
| Internal grading | IFRS 9 Model Setup: PD Model Setup, Internal Grades |
| PD module | Transition Profiles, Monthly and Cumulative Probability |
| LGD module | Monthly and Cumulative LGD, discounting of recoveries |
| EAD module | Carrying amount plus undrawn commitments times utilisation, computed inside the ECL and report queries |
| EIR module | Accounting Rules, EIR Data, Fee Classification, EIR Calculations, GL Reconciliation, Coverage and Blockers |
| Forward-Looking Information | Macro Elements, Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis, Management Overlays |
| SICR and staging engine | Staging and SICR Rules, and the staging classifier applied on import |
| ECL engine | ECL Processing: ECL Calculation, time-phased projections, stress testing |
| Reports | IFRS 9 Reports hub, reconciliation reports, disbursement report, Excel and PDF exports |
| Audit trail | Administration: Audit Trail, over both the activity log and the custom audit log |
| Dashboard | Dashboard with reporting period, portfolio and compare-to filters |
| Support and documentation | Support Tickets, User Manual, Administrator Manual, this manual, the Installation Guide |

### 1.4 Technology stack

| Layer | Technology | Version (from `composer.json` and `package.json`) |
|---|---|---|
| Language | PHP | 8.1 or later required; 8.2 in production and development |
| Framework | Laravel | 10.x (`laravel/framework`) |
| Authentication | Laravel Jetstream with Fortify | Jetstream 4.x, Fortify 1.x |
| Authorisation | spatie/laravel-permission | 6.x |
| Front-end bridge | Inertia.js (`inertiajs/inertia-laravel`, `@inertiajs/vue3`) | 1.x |
| Front end | Vue | 3.x with the Composition API |
| Styling | Tailwind CSS with the forms and typography plugins | 3.x |
| Build | Vite | 5.x |
| Charts | Chart.js | 4.x |
| PDF | barryvdh/laravel-dompdf (DomPDF) | 2.x |
| Excel | maatwebsite/excel over PhpSpreadsheet | 3.x |
| Data tables | yajra/laravel-datatables | 10.x |
| Audit | spatie/laravel-activitylog plus the application's own `audit_logs` table | 4.x |
| Markdown | league/commonmark (used to render this manual) | 2.x |
| Screenshots | puppeteer with its bundled Chromium | 25.x |
| Database | MySQL 8 or MariaDB 10.4 and later | |
| Queue | Laravel database queue driver | |

Exact versions are pinned in `composer.lock` and `package-lock.json`; the tables above give the major lines so a reader knows which upstream documentation applies.
