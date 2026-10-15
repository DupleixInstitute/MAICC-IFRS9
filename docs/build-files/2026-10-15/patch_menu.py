p = r'c:\xampp\htdocs\MAICC-IFRS9\config\menu.php'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new)

rep("""            $leaf('Stress Testing', 'stress-testing.index'),
        ], 1),""", """            $leaf('Stress Testing', 'stress-testing.index'),
            $leaf('RBM Return (provisional)', 'rbm-return.index', 'file-invoice'),
            $leaf('Auditor Pack', 'auditor-pack.index', 'file-archive', permission: 'eir.export'),
        ], 1),""")
rep("""            $leaf('Imports', 'imports.index'),
        ], 2),""", """            $leaf('Imports', 'imports.index'),
            $leaf('E-Banker Feed', 'eir-feed.index', 'cloud-download-alt', permission: 'eir.view'),
            $leaf('Take-on Schedules', 'eir-takeon.index', 'file-invoice', permission: 'eir.view'),
        ], 2),""")
rep("""            $leaf('EIR Calculations', 'eir-calculations.index', permission: 'settings'),""", """            $leaf('EIR Calculations', 'eir-calculations.index', permission: 'settings'),
            $leaf('EIR as at a Date', 'eir-as-at.index', 'calendar-day', permission: 'eir.view'),""")
rep("""                $leaf('Macro Elements', 'macro-statistics.index'),
                $leaf('Scenario Profiles', 'scenarios.profiles'),
                $leaf('Weighted Forecast', 'macro-forecast-weighted.index'),
                $leaf('Credit Loss Data', 'credit-loss-data.index'),
                $leaf('Adjusted Forecast', 'forecasting.manual'),
                $leaf('Regression Analysis', 'regression.index'),
            ], 3),""", """                $leaf('Macro Elements', 'macro-statistics.index', permission: 'macro.view'),
                $leaf('Scenario Profiles', 'scenarios.profiles'),
                $leaf('Scenario Sets', 'scenario-sets.index'),
                $leaf('Weighted Forecast', 'macro-forecast-weighted.index'),
                $leaf('Credit Loss Data', 'credit-loss-data.index'),
                $leaf('Adjusted Forecast', 'forecasting.manual'),
                $leaf('Correlation Finder', 'fli-correlation.index'),
                // The legacy one-click regression screen is retired (system
                // audit of 9 October 2026, finding M6); the regression runs on
                // FLI Adjustments under maker-checker.
                $leaf('Regression Analysis', 'fli-adjustments.index'),
            ], 3),""")
rep("""                $leaf('External Calculations', 'fli.external.index'),
                $leaf('Calculation History', 'fli.external.list'),
            ], 4),""", """                $leaf('Manual Overlays', 'fli-overlays.index'),
                $leaf('External Calculations', 'fli.external.index', permission: 'reports.ifrs9'),
                $leaf('Calculation History', 'fli.external.list', permission: 'reports.ifrs9'),
            ], 4),""")
rep("""            $leaf('ECL Calculation', 'expected-credit-loss.index'),
        ], 6),""", """            $leaf('ECL Calculation', 'expected-credit-loss.index'),
            $leaf('Mega Farm Programme', 'megafarm.index', 'seedling'),
        ], 6),""")
rep("""            $leaf('Audit Trail', 'audit-trail.index'),
            $leaf('Support Tickets', 'tickets.index'),""", """            $leaf('Audit Trail', 'audit-trail.index'),
            $leaf('Audit Trace', 'audit-trace.index', 'history'),
            $leaf('Compliance Audits', 'compliance-audits.index', 'clipboard-check'),
            $leaf('Support Tickets', 'tickets.index'),""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
