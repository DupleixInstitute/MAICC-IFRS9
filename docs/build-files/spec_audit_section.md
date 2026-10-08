## 12. Compliance audit workbooks

### 12.1 In plain language

An auditor's first question is not "what is the number" but "show me where the standard says so, and show me the system doing it". The ZNBS stress-testing suite answers that with one audit workbook per Bank of Zambia directive: every section of the directive on its own row, what the system does about it, where to see it, a status, and a place for the reviewer to sign. The same engine builds the Excel, a Markdown twin and a PDF from one data file, so the three can never say different things. Decision D25 brings that to MAIIC, with two additions the EIR work makes possible: each row names the Governance Centre setting that governs it, and each row names the test that proves it.

The result is five workbooks, a register in the system where MAIIC signs each row, and an auditor's pack per period that bundles them with the figures. Deloitte gets a document they can walk from paragraph to screen to test; Dr Thom gets a count of what is done, partly done, outstanding, not applicable or waiting on evidence, which is the project's status in one line.

### 12.2 The five workbooks

| Workbook | What the rows are | Reviewer |
|---|---|---|
| **IFRS 9: the EIR and amortised cost** | The paragraphs the engine implements: the Appendix A definitions of effective interest rate, amortised cost and gross carrying amount; 5.4.1 to 5.4.4 (interest at the EIR; Stage 3 on the net amount, 5.4.1(b)); B5.4.1 to B5.4.7 (fees that are integral, the floating-rate reset, re-estimation of cash flows); 5.4.3 and B5.4.6 (modification gain or loss); 3.3.2 and B3.3.6 (derecognition and the 10 percent test) | Dr Thom, then Deloitte |
| **IFRS 9: impairment** | 5.5 and B5.5: staging, significant increase in credit risk, 12-month and lifetime losses, forward-looking information, write-off; the ECL module already built | Dr Thom, then Deloitte |
| **IFRS 7 and IAS 1: presentation and disclosure** | IAS 1.82(a), interest revenue calculated using the EIR shown as its own line; IFRS 7.20 and 7.35A to 7.35N, the credit-risk disclosures; each row mapped to the disclosure report that produces it | Deloitte |
| **RBM classification and provisioning** | The Reserve Bank directive behind the "IFRS 9 vs RBM" report, section by section, with the report line that answers each | MAIIC Risk |
| **Contract Schedule 1** | Each deliverable and acceptance item of the implementation agreement, with the screen, document or test that discharges it | Dr Thom, at acceptance (P9) |

### 12.3 What a workbook contains

Four sheets. The first three are the ZNBS shape; the fourth is new.

| Sheet | Content |
|---|---|
| **Contents** | The standard's own order of sections, each hyperlinked to its row on the Audit sheet; the status counts at the top |
| **Audit** | One row per section of the standard, eleven columns (12.4), a status drop-down with colour coding, and conditional formatting that flags a "Done" with no test named |
| **Findings** | What needs a decision or a fix: number, reference, finding, what was found, impact, recommended action, owner, status |
| **Baselines** | The acceptance ties of section 9, one per row: the test, the expected figure, the system's current figure read at generation, and a live PASS or FAIL formula. The MAIIC counterpart of the ZNBS golden-numbers check |

### 12.4 The eleven columns of the Audit sheet

The nine ZNBS columns, then two MAIIC additions.

| # | Column | What goes in it |
|---|---|---|
| 1 | Reference | The paragraph or section number of the standard |
| 2 | Section name | Its heading |
| 3 | What it requires | The requirement in one or two plain sentences |
| 4 | Status | One of: Done; Partially done; Outstanding; Not applicable, documented; Evidence needed from MAIIC |
| 5 | What the engine does | The behaviour, and the evidence checked when the status was set |
| 6 | General comment | Anything a reader needs that the other columns do not carry |
| 7 | Compliance comment | How the behaviour satisfies the requirement, or why it does not yet |
| 8 | Where to see it | The screen (as a route the register turns into a link) and the report or export |
| 9 | Reviewer sign-off | Initials and date; in the register this is captured under maker-checker, not typed |
| 10 | **Governance setting** | The key of the setting in section 4.2 that governs the behaviour (for example `rate_change_classification` on B5.4.5), so a reviewer sees which choice each paragraph turned on |
| 11 | **Test that proves it** | The PHPUnit test, by class and method, that fails if the behaviour changes; a row may be Done only if this column is filled |

The five statuses mean exactly what they mean at ZNBS: Done is implemented and visible in an approved output; Partially done is mechanics in place with a parameter, input or piece of evidence still differing from the requirement; Outstanding is required and not built; Not applicable carries its reason and reference; Evidence needed means the system is ready and MAIIC must supply a document, minute or dataset.

### 12.5 Where it lives in the system

| Place | What is there |
|---|---|
| **Governance Centre, Compliance Audits** | The register. One card per workbook with its status counts and the three downloads (Excel, PDF, Markdown). Opening a card shows the rows; a reviewer with the govern permission sets a row's status and signs it, a second person approves, and the audit log records both. Column 8 renders as a link to the screen. The workbook is generated from the register, so the signed state is the database, never a file someone edited |
| **Report Hub, Auditor Pack** | Per period: the Deloitte export (O12), the five workbooks as at that period, and the Baselines sheet, bundled in one archive with a checksum per file and a manifest, the way the ZNBS regulatory return is archived. This is what is handed to Deloitte |
| **Governance Centre, Audit Trail, Audit Trace** | The per-record trace from the ZNBS suite: open any contract and see, oldest to newest, every change to its schedule, every reset and modification, and the governance values each of its months was run under. The workbook states the rule; the trace shows the rule applied to one loan |

### 12.6 How it is built

- **The engine is ported, not rewritten.** `tools/compliance/` from the ZNBS repository (`audit_workbook.py`, `build_audit.py`, `md_to_pdf.py`) becomes `tools/compliance/` in MAICC-IFRS9, with the two columns and the Baselines sheet added. Each standard is a data module in `tools/compliance/standards/` exposing `META`, `ROWS`, `FINDINGS` and `BASELINES`; the builder validates them (eleven fields per row, a known status, no duplicate reference, a reason on every Not applicable, a test on every Done) and refuses to write a misleading workbook.
- **One source, three outputs, one register.** The data modules are the source. The builder writes the Excel, the Markdown and the PDF to `docs/compliance/`; a seeder loads the same rows into `compliance_audit_rows` for the register; the register's generation step reads the signed state back and rebuilds the three files. Nothing is typed twice.
- **The Baselines sheet reads the system.** At generation the builder runs the section 9 checks against the database and writes the current figure beside the expected one; the PASS or FAIL formula lives in the sheet so Deloitte can see it recompute.
- **Tables and code.** `compliance_audits` (one per workbook: key, title, standard, reviewer), `compliance_audit_rows` (the eleven columns, status, signed_by, signed_at, approved_by, approved_at), `compliance_findings`; `ComplianceAuditService`, `ComplianceAuditController`, pages under `Pages/Governance/Compliance`; the trace page `Pages/Audit/Trace.vue` ported with the EIR subjects (contract, schedule, reset, modification, setting); the pack builder `eir:build-auditor-pack {period}`.
- **The IFRS 9 EIR module is drafted first**, from this specification: every paragraph it cites already maps to a service and a test, so its rows can be written now and signed as the phases land. The impairment, disclosure and RBM modules follow from the existing reports; Schedule 1 from the contract.

### 12.7 Acceptance

1. Each of the five workbooks builds from its module in the three formats, and the Markdown and Excel carry identical rows.
2. The builder refuses a Done row with no test, a Not applicable with no reason, and a duplicate reference.
3. In the register, a status change and a sign-off need two people, and both appear in the audit log with the old and new values.
4. Every column 8 link opens the screen it names for a user with the permission; every column 11 test exists and passes.
5. The Baselines sheet shows PASS on every section 9 tie against the production copy.
6. The auditor's pack for a period contains the export, the five workbooks and the manifest, and every checksum in the manifest verifies.

### 12.8 Order of work

CA-1 port the engine and add the two columns and the Baselines sheet (one day); CA-2 the IFRS 9 EIR module, drafted from this document (one day, then kept current as P5 to P7 land); CA-3 the register, the trace and the pack (two days); CA-4 the four remaining modules (two days, Schedule 1 last, at P9). CA-1 and CA-2 start with P4b; CA-3 sits with P8, since the register and the pack are screens of the suite layout; CA-4 is finished before UAT.
