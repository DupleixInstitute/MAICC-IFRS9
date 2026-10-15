# Build files, 9 to 15 October 2026

These are the scripts behind the work in `docs/sessions/Claude_Session_Transcript_UI_GoLive_and_Fees_2026-10-15` (the user-interface rework, packs 8 to 10, the go-live build, the production database and Finance's fees). Each script is reproduced in the transcript at the point where it ran. They are copied as they were run, from the session's working folder: paths inside them are those of the Dupleix workstation, so change them before re-running elsewhere. Two folders of the working area are left out because they were only snapshots of this repository's own code (`head`, `fcca5af`), as are files over 200 KB.

Scripts that read a database read the working copy they name (`DB_DATABASE=...`), never production unless the script says so; the ones that wrote to production on 15 October are listed under "Production database".

## The transcript

| Script | What it does |
|---|---|
| `build_transcript_golive.py` | Builds the transcript (.md and .pdf) from the session log: `python build_transcript_golive.py <output folder>`. Tool inputs over 24,000 and results over 12,000 characters keep their beginning and end, with the cut marked; messages sent while a turn was running are included |
| `redact_session_log.py` | Finds credentials in a session log and writes a redacted copy: `python redact_session_log.py <log.jsonl> [<copy.jsonl>]`. Used for the transcript and for the raw log kept on OneDrive (two Laravel application keys found) |

## Finance's fees (15 October, `tools/`)

| Script | Output (OneDrive `MAIIC/3. Project Execution/Fees 2025 - workbook, GL and design`) |
|---|---|
| `make_fee_review.py` | `MAIIC fee classification review - 324 lines - 15 Oct 2026.xlsx`: every fee line with the rule that would match it (the app's `FeeRuleMatcher` reproduced), flags, the GL against loan-system pairs and the per-loan view |
| `make_fee_recon_2025.py` | `MAIIC 2025 fees - Finance workbook vs general ledger - 15 Oct 2026.xlsx`: Finance's 34 loans against the 2025 postings on GL 4871 and 4873, with the "Questions for Tamanda" sheet (Q1, Chikwendeni's 800) |
| `make_fee_recon_2024.py` | `MAIIC 2024 fees - Finance workbook vs general ledger - 15 Oct 2026.xlsx`: the 33 loans of 2023 and 2024 against the old ledger (`CV_04a`) and E-Banker (`GL_04`); the Mega Farm programme accruals shown apart |
| `make_fee_upload_files.py` | `MAIIC fee workbook upload - 2025 - ready - 15 Oct 2026.csv` and `MAIIC fee workbook uploads - for Finance - 15 Oct 2026.xlsx`, each run first through the system's gate (`tieout_json.php`) |
| `tieout_json.php`, `tieout_dry.php` | Run `FeeWorkbookTieOut::check` (read only) on a JSON list of fee rows |
| `run_fee_import.php` | Runs the real fee import job on a throwaway database (it refuses `maiic_ifrs9`); used to prove the 2025 file loads, ties 27 held postings and is idempotent |

## Production database (14 to 15 October)

| Script | What it does |
|---|---|
| `promote_newprod.sql` | Carried the administrator's login and the 14 tickets into the fresh bootstrap before it replaced production (the swap itself was a set of `RENAME TABLE` statements, in the transcript) |
| `smoke_pages.php` | Opens every parameter-free GET page as user 1 and reports any status of 400 or more. Leave out `/migrate` and `/cache`: they run commands when opened |
| `tmp-pa/scan.py` | Scans every text column of a database for machine-specific paths (drive letters, xampp, OneDrive, localhost) |
| `tmp-pa/counts.py` | Compares the row counts of two databases table by table |
| `tmp-pa/sp_extract.py` | Takes the two S&P grading sheets of the December 2025 ECL model as values (`docs/bootstrap/models`) |

## Packs 8 to 10 (`tools/`, `packs1/`)

`make_pack9.py`, `make_pack9_bootstrap.py`, `make_pack10.py`, `make_pack10_followup.py` make the pack run sheets and the unsent emails; `packs1/` lands the packs, builds the manifests, checks the take-on tie and sets the golden numbers (`set_golden.php`); `octinputs/queries` holds the pack SQL as sent.

## The rest, by folder

| Folder | Content |
|---|---|
| `ui_stage1/`, `ui_breadcrumbs/`, `book/`, `dashboard_charts/` | The navigation, tabs, sidebar, breadcrumbs, dashboard and its charts: the patches and the page captures used to check them |
| `imports/`, `imports_shots/`, `ingest_check/` | The sample files, instructions and checks on every import, and the landing-zone checks |
| `docs_audit/`, `inst/`, `specaudit14/` | The documentation audit (help coverage, screenshots, the installation guide edits) and the specification audit |
| `rv/`, `rv3/`, `reports_dl/`, `rep/` | The results review for every period against MAIIC's December 2025 model (the report in Downloads) and the reports checked |
| `e123/`, `restructure/`, `gaps/`, `ctl/`, `pv/`, `pv47/`, `segments/`, `fli/` | The go-live build: GL fee legs, restructures, the gaps closed, controls, the PD and LGD work, the forward-looking chain |
| `uat/`, `octtools/` | The click-through tests of the screens and the next-period checks |
| top level | Probes and patches run during the session (`edit_*.py`, `patch_*.py`, `*probe*.php`, `chk*.php`) |
