<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 90px 40px 60px 40px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #1f2937; line-height: 1.55; margin: 0; }

    .hdr { position: fixed; top: -65px; left: 0; right: 0; height: 50px; }
    .hdr .co { font-size: 12px; font-weight: bold; color: #14532d; }
    .hdr .ba { height: 3px; background: #16a34a; margin-top: 6px; }
    .ftr { position: fixed; bottom: -42px; left: 0; right: 0; height: 30px;
           font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    .ftr .pg:after { content: "Page " counter(page); }

    .cover { text-align: center; padding-top: 150px; }
    .cover h1 { font-size: 34px; color: #14532d; margin: 0; }
    .cover .sub { font-size: 15px; color: #16a34a; margin-top: 10px; }
    .cover .co { font-size: 18px; font-weight: bold; margin-top: 60px; color: #1f2937; }
    .cover .dt { font-size: 11px; color: #6b7280; margin-top: 6px; }
    .cover .rule { width: 120px; height: 4px; background: #16a34a; margin: 24px auto; }

    h2 { font-size: 18px; color: #14532d; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; margin-top: 28px; }
    h3 { font-size: 13px; color: #16a34a; margin-top: 18px; margin-bottom: 4px; }
    p { margin: 6px 0; }
    ol, ul { margin: 6px 0 10px 0; padding-left: 20px; }
    li { margin: 4px 0; }
    .lead { font-size: 12px; color: #374151; }
    .tip { background: #dcfce7; border-left: 4px solid #16a34a; padding: 8px 12px; margin: 10px 0; font-size: 10px; }
    .warn { background: #fff7ed; border-left: 4px solid #d97706; padding: 8px 12px; margin: 10px 0; font-size: 10px; }
    .step { background: #f0fdf4; border-radius: 6px; padding: 8px 12px; margin: 8px 0; }
    .toc div { padding: 4px 0; border-bottom: 1px dotted #d1d5db; font-size: 11px; }
    .term { font-weight: bold; color: #14532d; }
    .pb { page-break-before: always; }
    table.gloss { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.gloss td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 10px; vertical-align: top; }
    table.gloss td.k { width: 130px; font-weight: bold; color: #14532d; background: #f0fdf4; }
</style>
</head>
<body>
    <div class="hdr"><div class="co">{{ $company }} &mdash; IFRS 9 ECL System</div><div class="ba"></div></div>
    <div class="ftr"><table style="width:100%"><tr><td>IFRS 9 User Manual</td><td style="text-align:right" class="pg"></td></tr></table></div>

    <div class="cover">
        <h1>IFRS 9 User Manual</h1>
        <div class="rule"></div>
        <div class="sub">Expected Credit Loss &mdash; Step-by-Step Guide for Everyday Users</div>
        <div class="co">{{ $company }}</div>
        <div class="dt">Issued {{ $generated_at }}</div>
    </div>

    <div class="pb"></div>
    <h2>What's in this guide</h2>
    <div class="toc">
        <div>1. Before you start &mdash; what this system does</div>
        <div>2. The big picture &mdash; how a number becomes an ECL</div>
        <div>3. Step 1 &mdash; Portfolios</div>
        <div>4. Step 2 &mdash; Clients</div>
        <div>5. Step 3 &mdash; Loan Books</div>
        <div>6. Step 4 &mdash; Transition Profiles</div>
        <div>7. Step 5 &mdash; Transition Matrices (PD)</div>
        <div>8. Step 6 &mdash; LGD (both methods)</div>
        <div>9. Step 7 &mdash; Expected Credit Loss</div>
        <div>10. Step 8 &mdash; Reports &amp; Summary</div>
        <div>11. The IFRS 9 Regulatory Reporting Suite</div>
        <div>12. Early Warning System, AI Commentary &amp; Navigation</div>
        <div>13. Glossary &amp; FAQ</div>
    </div>

    <div class="pb"></div>
    <h2>1. Before you start &mdash; what this system does</h2>
    <p class="lead">This system works out how much money MAIIC should set aside today for loans
        that may not be fully repaid. That amount is the <span class="term">Expected Credit
        Loss (ECL)</span>, required by <span class="term">IFRS&nbsp;9</span>.</p>
    <p>You do not need to be an accountant. You enter data in a fixed order, press calculate at
        each stage, and the system does the maths. This guide covers the IFRS&nbsp;9 workflow
        only — credit scoring is not part of this system.</p>
    <div class="tip"><strong>Golden rule:</strong> work in order — Portfolios → Clients →
        Loan Books → Transition Profiles → Transition Matrices → LGD → ECL → Reports.</div>

    <h2>2. The big picture &mdash; how a number becomes an ECL</h2>
    <p>Every ECL figure is three ideas multiplied together:</p>
    <ol>
        <li><span class="term">PD</span> — how likely the loan goes bad (a %).</li>
        <li><span class="term">LGD</span> — how much we lose if it does, after recoveries (a %).</li>
        <li><span class="term">EAD</span> — how much is owed (an amount).</li>
    </ol>
    <p style="text-align:center; font-size:14px; color:#14532d; font-weight:bold;">ECL = PD &times; LGD &times; EAD</p>
    <h3>Loan stages</h3>
    <ul>
        <li><span class="term">Stage 1</span> — healthy; provide 12-month ECL.</li>
        <li><span class="term">Stage 2</span> — risk significantly increased; provide lifetime ECL.</li>
        <li><span class="term">Stage 3</span> — in default; treated as 100% likely to default.</li>
    </ul>

    <div class="pb"></div>
    <h2>3. Step 1 &mdash; Portfolios</h2>
    <p>A <span class="term">portfolio</span> is a named group of similar loans. Open
        <strong>Portfolio Setup</strong>, create portfolios with clear names, save.</p>
    <h2>4. Step 2 &mdash; Clients</h2>
    <p>Open <strong>Customer &amp; Loan Data → Clients</strong>. Ensure each client has a unique
        customer ID — that is how loans attach to them.</p>
    <h2>5. Step 3 &mdash; Loan Books</h2>
    <p>The loan book is one row per loan per month: balance, dates, arrears, stage. This is where
        <strong>EAD</strong> comes from. Import the month's file under
        <strong>Customer &amp; Loan Data → Loan Book</strong>; check totals and staging.</p>
    <div class="tip">One reporting period at a time. The month you load is the month every later step calculates.</div>

    <div class="pb"></div>
    <h2>6. Step 4 &mdash; Transition Profiles</h2>
    <p>A transition profile tells the system how to measure loans moving between stages: which
        data, which grading column, by balance or by count. Set up once under
        <strong>IFRS 9 Model Setup → PD Model Setup → Transition Profiles</strong>.</p>
    <h2>7. Step 5 &mdash; Transition Matrices (PD)</h2>
    <p>A transition matrix is a grid of the chance a loan moves from one stage to another — the
        <strong>PD</strong>. <span class="term">Monthly</span> = one month;
        <span class="term">Cumulative</span> = several months combined (lifetime PD for Stage 2).
        Each row should add to 100%.</p>
    <div class="warn">If a row does not total 100%, the loan data for that month is incomplete — fix it first.</div>

    <div class="pb"></div>
    <h2>8. Step 6 &mdash; LGD (both methods)</h2>
    <p><span class="term">LGD</span> is the fraction lost when a loan defaults, after recovery.</p>
    <ul>
        <li><span class="term">Customer LGD</span> — based on the customer's own collateral/circumstances.</li>
        <li><span class="term">Collection LGD</span> — based on the institution's historical recovery performance (portfolio-wide default).</li>
    </ul>
    <p>Both are stored for every loan; <span class="term">Monthly</span> and
        <span class="term">Cumulative</span> versions exist. You pick the method at the ECL step.</p>

    <h2>9. Step 7 &mdash; Expected Credit Loss</h2>
    <p>Open <strong>ECL Processing → Run ECL Calculation</strong>. Choose the period, level
        (portfolio/sector), PD type and LGD method, and run. The system writes ECL onto every
        loan and stores stage-level totals.</p>
    <div class="tip">Re-running an ECL period is safe — it overwrites with fresh figures, never duplicates.</div>
    <div class="warn">Reports only show periods where ECL has been calculated.</div>

    <div class="pb"></div>
    <h2>10. Step 8 &mdash; Reports &amp; Summary</h2>
    <p>The <strong>IFRS 9 Reports</strong> area turns the calculated numbers into board-ready
        documents. Every report views on screen and downloads as a polished PDF.</p>

    <h2>11. The IFRS 9 Regulatory Reporting Suite</h2>
    <p class="lead">Open <strong>Reports → IFRS 9 Reports</strong>. Pick a reporting period, choose
        a <strong>section tab</strong>, then a report — only that report loads.</p>
    <h3>Core ECL</h3>
    <ul><li>ECL Summary by Stage; Account-Level ECL; Stage Allocation.</li></ul>
    <h3>Staging &amp; Movement</h3>
    <ul><li>SICR Trigger; Stage Migration; Opening-to-Closing ECL Reconciliation; Gross Carrying
        Amount Movement; ECL Charge / Release.</li></ul>
    <h3>Model Components</h3>
    <ul><li>PD Report; LGD &amp; Collateral; EAD &amp; Off-Balance Sheet.</li></ul>
    <h3>Forward-Looking</h3>
    <ul><li>Macro Scenario &amp; Forward-Looking; Scenario-Weighted ECL.</li></ul>
    <h3>RBM Prudential</h3>
    <ul><li>RBM Asset Classification; IFRS 9 vs RBM Mapping; NPL &amp; Arrears; Provision
        Comparison (IFRS 9 ECL vs RBM provision).</li></ul>
    <h3>Disclosure &amp; Audit</h3>
    <ul><li>Financial Statement Disclosure; Audit Trail &amp; Data Quality.</li></ul>
    <h3>Stress Testing — interactive</h3>
    <p>The <span class="term">Sensitivity Analysis</span> is not hardcoded. Enter your own
        <strong>PD</strong>, <strong>LGD</strong> and <strong>combined</strong> shock percentages
        (comma-separated, e.g. <em>10,25,50</em>) and press <strong>Run</strong>; ECL recomputes
        for each shock with the impact and % increase. Leave blank for defaults.</p>

    <div class="pb"></div>
    <h2>12. Early Warning System, AI Commentary &amp; Navigation</h2>
    <p><strong>Analytics → Early Warning System</strong> surfaces risk before default:
        performing (Stage 1) accounts already in arrears, facilities at &ge;90% utilisation, and
        fresh Stage 1&rarr;2 migrations, with a ranked watchlist and HIGH / MEDIUM / WATCH severity.</p>
    <p><strong>Analytics → AI Executive Commentary</strong> auto-writes a plain-language narrative
        of the ECL position (exposure, movement, NPL ratio, coverage) for board packs. It
        supports — not replaces — management and audit judgement.</p>
    <h3>Finding your way around</h3>
    <ul>
        <li><span class="term">Portfolio Setup</span> · <span class="term">Customer &amp; Loan Data</span> · <span class="term">Collateral Management</span></li>
        <li><span class="term">IFRS 9 Model Setup</span> → Staging &amp; SICR, PD Model, LGD Model, Forward-Looking Model, Management Overlays</li>
        <li><span class="term">ECL Processing</span> → run the calculation</li>
        <li><span class="term">Reports</span> → the tabbed IFRS 9 suite + reconciliation/export</li>
        <li><span class="term">Analytics</span> → EWS, AI commentary, regression</li>
        <li><span class="term">Administration</span> → users, settings</li>
    </ul>
    <p>The menu expands one section at a time and highlights the page you are on.</p>

    <div class="pb"></div>
    <h2>13. Glossary &amp; FAQ</h2>
    <table class="gloss">
        <tr><td class="k">IFRS 9</td><td>The standard requiring provision for expected (not just incurred) loan losses.</td></tr>
        <tr><td class="k">ECL</td><td>Expected Credit Loss — money set aside today for likely future losses.</td></tr>
        <tr><td class="k">PD</td><td>Probability of Default — chance a loan goes bad (%).</td></tr>
        <tr><td class="k">LGD</td><td>Loss Given Default — share lost on default, after recoveries (%).</td></tr>
        <tr><td class="k">EAD</td><td>Exposure at Default — amount at risk.</td></tr>
        <tr><td class="k">Coverage ratio</td><td>ECL ÷ exposure — how heavily the book is provisioned.</td></tr>
        <tr><td class="k">RBM class</td><td>Performing / Special Mention / Non-Performing (RBM Directive 2018).</td></tr>
        <tr><td class="k">Reporting period</td><td>The month the figures relate to, e.g. 2025-11.</td></tr>
    </table>
    <h3>A report is missing a month?</h3>
    <p>Reports list only periods whose ECL has been calculated. Run ECL for that month.</p>
    <h3>Can I re-run a calculation?</h3>
    <p>Yes — it overwrites that period, never duplicates.</p>
    <h3>Which LGD method?</h3>
    <p>Use Collection LGD as the institution-wide default; Customer LGD where reliable
        customer-level recovery data exists. Be consistent month to month.</p>
    <h3>Numbers look wrong?</h3>
    <p>Almost always the loan book for that month is incomplete or mis-staged. Fix it, then
        re-run from the Transition Matrix step down.</p>

    <p style="margin-top:30px; font-size:10px; color:#9ca3af; text-align:center;">
        End of manual &mdash; {{ $company }} &middot; Generated {{ $generated_at }}
    </p>
</body>
</html>
