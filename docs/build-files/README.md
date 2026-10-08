# Build files: MAIIC raw query scripts analysis

Prepared 5 October 2026 by Dupleix Institute, in a Claude Code session. These are the Python scripts behind the files in the folder above, kept so the work can be reviewed and re-run.

**Version 2.** The report and the SQL query pack were revised on 5 October 2026 after an independent review by Codex. The review is filed in the engine repository at `MAICC-IFRS9\docs\reviews\MAIIC-Claude-Review-2026-10-05.md`. The response to each comment is Appendix E of the report.

## What each file does

| File | Purpose | Output |
|---|---|---|
| `maiic_check.py` | Prints the layout of the delivered Extract A, B and C workbooks | Screen only |
| `maiic_check2.py` | First evidence tests: rate label by product, snapshot comparison, disbursed against sanctioned, Extract B interest against Extract C, accounts missing from C | Screen only |
| `maiic_check3.py` | Finds the three runs stacked in the Extract C file | Screen only |
| `maiic_check4.py` | Exact evidence figures for the report: snapshot comparison, status counts, ledger sample, the ten active accounts with no interest | Screen only |
| `maiic_exact.py` | Exact figures for the report's appendix tables (the six accounts where B and C differ, run totals) | Screen only |
| `maiic_tr.py` | Re-test of the disbursement tranche claim, Extract A against the ledger rows in Extract B | Screen only |
| `review_checks.py` | Tests added in response to the review: direction of each transaction type from the running balance, Extract B closing balance against Extract A, the Other/Adjustment rows, repayment frequency in the two snapshots, key checks | Screen only |
| `review_balance_check.py` | Extract B closing balance against Extract A at 31 December 2025, in posting order (the 110 of 111 figure) | Screen only |
| `patch_sql.py` | The version 2 changes to the SQL pack: owner carried through every catalogue result, the export contract, session settings, and three new queries (DD_17, DD_18, DD_19) | Already applied to `MAIIC_EBanker_Data_Dictionary_Queries.sql` |
| `runorder.py` | Builds the run-order SQL file (27 queries) from `MAIIC_EBanker_Data_Dictionary_Queries.sql` | `RUN ORDER - data dictionary queries by priority.sql` |
| `maiic_report.py` | Builds the plain-language analysis report, version 2 | `MAIIC_Raw_Query_Scripts_Analysis_2026-10-05.pdf` |
| `build_transcript.py` | Exports the relevant part of the Claude session log | `Claude_Session_Transcript_Raw_Queries_2026-10-05.pdf` and `.md` |
| `patch_report.py`, `patch2.py` | One-off edits to the version 1 report script | Superseded. `maiic_report.py` was rewritten for version 2 and these no longer apply |

## Things a reviewer should know

- **Nothing here has touched the Oracle database.** The evidence tests read only the Excel extracts MAIIC delivered in August 2026. The 27 SQL queries in the folder above were written without database access and have not been run.
- **No application code was changed.** Section 13 of the report suggests changes to the MAICC-IFRS9 engine. They come from reading the code on branch `eir_revenue_recognition` on 5 October 2026. The engine was not run and its loaded data was not inspected.
- **`patch_sql.py` has already been applied.** Running it again stops on its own checks and changes nothing.
- **The session transcript is complete.** `Claude_Session_Transcript_Raw_Queries_2026-10-05.pdf` (and the `.md` copy) runs from the first request on 5 October 2026 to the analysis of MAIIC's data dictionary results on 6 October, including the Codex review and version 2. Its opening page lists the five stages of the work.
- **The 6 October analysis of the dictionary results was done with short inline scripts**, not saved files. They are reproduced in full in the transcript, in the tool calls after the heading for that day. The results they read are in `..\..\Query Request Responses 6 Oct 2026\Dupleix\Dupleix`.
- **Reading order for a newcomer:** the report PDF first (version 2), then the transcript, then the scripts here as needed.
- **`maiic_check3.py` stops with an error near the end** (a grouping line that fails on this version of pandas) after printing its main results. In the session the remaining checks were run with that line replaced; the results are in the transcript.
- **`maiic_check2.py` contains a snapshot comparison that over-reports differences** on columns with mixed date formats. `maiic_check4.py` and `review_checks.py` repeat the comparison properly and are the ones the report relies on.
- **`review_checks.py` tests direction in file order.** Sixteen rows in Extract B are out of posting order, which is why a few rows are reported as not placed. The closing-balance test in the report (110 of 111) takes the row with the highest posting number, which `review_balance_check.py` reproduces; `review_checks.py` prints the file-order version (106 of 111).
- **Paths are fixed to the PC the work was done on** (the OneDrive MAIIC project folder and the Windows fonts folder). They need changing to run anywhere else.
- **`build_transcript.py` reads the Claude Code session log** from the user profile on that PC, so it can only be re-run there.

## To re-run

Python 3 with `pandas`, `openpyxl`, `reportlab` and `pypdf` (`pypdfium2` and `pillow` only if page previews are wanted).

1. `python maiic_check4.py`, `python maiic_exact.py`, `python maiic_tr.py`, `python review_checks.py` and `python review_balance_check.py` reproduce the figures quoted in the report.
2. `python runorder.py` rebuilds the run-order SQL file from the main SQL pack.
3. `python maiic_report.py` rebuilds the PDF report.

## 7 October 2026: audit of the eighteen follow-up extracts

The result files are in `..\Follow-Up Scripts Resutls`. The audit report is `..\MAIIC_FollowUp_Extracts_Audit_2026-10-07.pdf`. These scripts produced it; run them from this folder in the order listed (they need `pandas`, `openpyxl`, `xlrd` and `reportlab`).

| File | Purpose |
|---|---|
| `fu_load.py` | Shared loader: reads every result file, parses dates with the explicit month/day/year format, keeps account numbers as text |
| `fu_profile.py` | Rows, columns and date formats of every file; proves the month-first order |
| `fu_a1.py` | Coverage per table, scope, interest policy, ledger signs, loan book and balance history months, rate set-up, chart shape |
| `fu_a2.py` | Reconciliations: ledger to balance history, to loan book carrying amount, to Extract C; interest recomputed from narrations; drawdowns and fees; Extract A dates |
| `fu_a3.py` | Trial balance to ledger (loan GL balances and interest income); interest summary; fee sources; chart conventions; Extract A Excel dates. Writes `tb_balance_recon.csv` and `tb_income_recon.csv` |
| `fu_a4.py` | Income rows that did not tie, pre- and post-migration coverage, status codes, terms available for schedule generation |
| `fu_a5.py` | Rebuilds every interest posting from daily balances and the narration rate; writes `interest_rebuild.csv`; checks the December 2025 trial balance |
| `fu_report.py` | Builds the audit PDF |

## 6 and 7 October 2026: the email, the notes to Barry and Credit

| File | Purpose | Output |
|---|---|---|
| `sql_to_pdf.py` | Makes the PDF and TXT copies of the follow-up queries file for the email | `..\FOLLOW-UP extracts for Barry - 6 Oct 2026.pdf` and `.txt` |
| `make_email.py` | Builds the Outlook-ready email with the three attachments and refreshes the Markdown draft | `..\EMAIL - 18 follow-up extracts by 10am 7 Oct 2026 (open in Outlook and send).eml` |
| `gl_note.py` | Note to Barry on the two constant FInES GL differences, with the two queries | `..\Note to Barry - the two FInES GL differences - 7 Oct 2026.pdf` |
| `zaithwa_note.py` | Note to Barry on the Zaithwa Farms balance difference, with the date settings and three queries | `..\Note to Barry - Zaithwa Farms balance difference - 7 Oct 2026.pdf` |
| `offer_note.py` | Request to Credit for the twelve offer letters (the ten sample loans and the two sanction checks) | `..\Request to Credit - the twelve offer letters - 7 Oct 2026.pdf` |

The session transcript (`..\Claude_Session_Transcript_Raw_Queries_2026-10-05.pdf` and `.md`) was rebuilt on 7 October and now runs to the end of this work; its opening page lists six stages.

## 7 October 2026, afternoon: Barry's GL and Zaithwa Farms results

Barry ran the queries from the two morning notes and returned five files in `..\Follow-Up Scripts Resutls\GL and ZF loan\` (GL_01, GL_02, ZF_01, ZF_02, ZF_03). GL_01 and ZF_02 are empty, which is itself the answer (no account-less postings; no posting of 1,827,633.62 anywhere).

| Script | What it does | Output |
|---|---|---|
| `patch_fu_report.py` | Patches `fu_report.py` in place for the afternoon findings: cover note, summary row, section 5 rows a and d marked resolved, section 11 rows answered, Appendix A rows for the five new files and the duplicate-row check. Run it once, then run `fu_report.py` | `fu_report.py` (patched copy here is already patched) |
| `fu_report.py` | Rebuilds the follow-up audit, now 13 pages | `..\MAIIC_FollowUp_Extracts_Audit_2026-10-07.pdf` |
| `outcome_note.py` | Plain-language outcome for Barry and Finance: the GL opening balances keyed on 1 January 2025 (+400,000.00 on 1050201, -1,000,000.00 on 1050202, carried into 2026) and the Zaithwa Farms balance-table fault (duplicate 30 April 2025 row, May row restarted from nil); what to fix | `..\Outcome - GL differences and Zaithwa Farms explained - 7 Oct 2026.pdf` |

Findings in one line each: the ledger is complete and right for both items; the fixes are on MAIIC's side (Finance to confirm the 2024 audited figure and adjust the two GL openings; the vendor to rebuild the Zaithwa Farms month-end rows from May 2025). Readiness is unchanged: both were reconciling items, not data gaps.
