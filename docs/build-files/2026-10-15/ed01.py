p = 'docs/manuals/technical/01-overview.md'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:70]
    s = s.replace(a, b)


rep("The platform is one Laravel application with a Vue single-page front end. It carries the solution components listed in Schedule 1 of the agreement:",
    "The platform is one Laravel application with a Vue single-page front end. Below Dashboard, Workspace and Reports, the sidebar groups the screens in four blocks: Data foundation, Financial modelling, Risk & regulatory, and Administration & support (chapter 2.3). It carries the solution components listed in Schedule 1 of the agreement:")
rep("| User and access management | Administration: Users & Roles (Users, Roles & Permissions) |",
    "| User and access management | Administration & support, Access & Setup: Users & Roles (Users, Roles & Permissions) |")
rep("Take-on Blocks, Take-on Population); EIR & Revenue Recognition: EIR Data |",
    "Take-on Blocks, Take-on Population); EIR & Revenue Recognition: EIR Data. Every import offers a sample file in the exact format, a column guide and a Check file that refuses a file with any error before anything is written (chapter 4) |")
rep("| Internal grading | IFRS 9 Model Setup: PD Model, Internal Grades |",
    "| Internal grading | IFRS 9 Model Setup: PD Model, Internal Grades (grading profiles, including the S&P grades of the ECL model workbook imported as a draft profile) |")
rep("| EAD module | Carrying amount plus undrawn commitments times utilisation, computed inside the ECL and report queries |",
    "| EAD module | Carrying amount plus undrawn commitments times utilisation, under one rule (`app/Services/Ecl/EclExposure.php`) used by the ECL engines and reports; nil for a loan written off to nil |")
rep("| EIR module | EIR & Revenue Recognition: EIR Data (Contract Master, Cash Flows, Drawdowns, Reference Rates, Schedule Review), EIR Rules (Accounting Rules, Fee Classification), EIR Calculations (Calculations, Blockers, Coverage by Portfolio, Largest Facilities, EIR as at a Date, GL Reconciliation), Governance Centre |",
    "| EIR module | EIR & Revenue Recognition: EIR Data (Contract Master, Cash Flows, Drawdowns, Reference Rates, Trial Balances, Write-offs, Restructures & Resets, Schedule Review), EIR Rules (Accounting Rules, Fee Classification), EIR Calculations (Calculations, Blockers, Coverage by Portfolio, Largest Facilities, EIR as at a Date, GL Reconciliation); the governed settings in Administration & support, Governance, Governance Centre |")
rep("| ECL engine | ECL Processing: ECL Calculation (with the time-phased projections), Mega Farm Programme; stress testing from the Reports hub |",
    "| ECL engine | ECL Processing: ECL Calculation (with the time-phased projections; the booked allowance is the discounted ECL), Mega Farm Programme (measured by its own engine and kept out of the main PD, LGD and ECL); stress testing from the Reports hub |")
rep("| Reports | Reports: the IFRS 9 reports hub, from which every report, reconciliation, disbursement report and Excel and PDF export is reached |",
    "| Reports | Reports: the IFRS 9 reports hub, from which every report, reconciliation, disbursement report, the RBM return, the auditor pack and every Excel and PDF export is reached; the reports carry the booked (discounted) ECL |")
rep("| Audit trail | Administration: Audit (Audit Trail, Audit Trace, Compliance Audits); the Audit Trail reads both the activity log and the custom audit log |",
    "| Audit trail | Administration & support, Governance: Audit (Audit Trail, Audit Trace, Compliance Audits); the Audit Trail reads both the activity log and the custom audit log |")
rep("| Support and documentation | Administration: Support Tickets; System Documentation: User Manual, Administrator Manual, Technical Manual (this manual), Installation Guide |",
    "| Support and documentation | Administration & support, Support & Maintenance: Support Tickets, and System Documentation (User Manual, Administrator Manual, Technical Manual (this manual), Installation Guide) |")
rep("| Operations | Administration: Financial Periods, Settings, Maintenance (the month-end and system jobs run from the browser, chapter 9.5) |",
    "| Operations | Administration & support: Access & Setup (Financial Periods, Settings) and Support & Maintenance (Maintenance: the month-end and system jobs run from the browser, chapter 9) |")
rep("| Authentication | Laravel Jetstream with Fortify | Jetstream 4.x, Fortify 1.x |", "| Authentication | Laravel Jetstream with Fortify | Jetstream 3.x, Fortify 1.x |")
rep("| Authorisation | spatie/laravel-permission | 6.x |", "| Authorisation | spatie/laravel-permission | 5.x |")
rep("| Front-end bridge | Inertia.js (`inertiajs/inertia-laravel`, `@inertiajs/vue3`) | 1.x |", "| Front-end bridge | Inertia.js (`inertiajs/inertia-laravel`, `@inertiajs/vue3`) | 0.6.x on the server, 1.x in the browser |")
rep("| Build | Vite | 5.x |", "| Build | Vite | 4.x |")
rep("| Charts | Chart.js | 4.x |", "| Charts | Chart.js | 3.x |")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
