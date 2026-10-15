from ed import edit
p='app/Console/Commands/MaintenanceTask.php'
edit(p, r""" *   php artisan maiic:task month-end 2026-08 --user=1  stage, PD and LGD, forward-looking route and ECL and record, EIR revenue, Mega Farm""",
r""" *   php artisan maiic:task month-end 2026-08 --user=1  scheme settings, stage, PD and LGD, forward-looking route and ECL and record, ledger cash, GL fees, EIR revenue, Mega Farm""")
edit(p, r"""                ['Clients for new loan-book customers', 'clients:link-loan-book', $u],""", r"""                ['Clients for new loan-book customers', 'clients:link-loan-book', $u],
                // the scheme master of the pack (SM_01, DD_10 where it lacks a field) so a schedule reads its scheme's interest basis; the over-sanction flag (spec 7.3)
                ['Scheme settings and the over-sanction flag', 'eir:load-schemes', []],""")
edit(p, r"""                ['Actual cash from the E-Banker ledger', 'eir:load-ledger-cash', $u],""", r"""                ['Actual cash from the E-Banker ledger', 'eir:load-ledger-cash', $u],
                // the month's GL_04 fee postings tied to their loans (PENDING for Fee Classification), the precedence, and the fee GL gate against the trial balance (spec 7.8.3 to 7.8.5)
                ['GL fee postings tied to their loans, and the fee GL gate', 'eir:build-gl-fees', $u],""")
