# Build files, 9 to 14 October 2026

These are the scripts behind the work in `Claude_Session_Transcript_Feeds_and_Integration_2026-10-14` (packs 3 to 10, the December 2025 ECL models against the RBM DFI Directive, and the E-Banker integration pack). Each script is reproduced in full in the transcript at the point where it ran. Paths inside the scripts are those of the Dupleix workstation; change them before re-running elsewhere. Scripts that read a database read only the throwaway bootstrap database (`--env=bootstrap`), never production.

## Renderers (shared)

| Script | What it does |
|---|---|
| `spec_render.py` | Turns a Markdown document with a front-matter block into .docx and .pdf. As of 14 October it also places a logo (`logo:`), fully justifies text (`justify: yes`), builds a table of contents with page numbers (`toc: yes`), places figures (`![caption](file.png)`), keeps short tables and figures whole on one page, never splits a table row and never breaks a word across a table column. Usage: `python spec_render.py "<document>.md"` |
| `sql_to_pdf.py` | Turns a pack's all-runs .sql sheet into the PDF given to MAIIC ICT |
| `build_transcript_integration.py` | Builds this session's transcript (.md and .pdf) from the session log |

## The E-Banker integration pack (3. Project Execution/E-Banker Integration)

| Script | Output |
|---|---|
| `feed_diagram.py` | Figure 1, `Feed routes and landing zone - diagram.png` |
| `bootstrap_diagram.py` | Figure 2, `Bootstrap - test harness and reference copy - diagram.png` |
| `route3_diagram.py` | Figure 3, `Route 3 - push by API endpoint - sequence diagram.png` |
| `tables_diagram.py` | Figure 4, `Landing zone - the four tables, one payment followed through - diagram.png` (the MUSECO payment is real data from the bootstrap database) |
| `gantt.py` | Figure 5, `Route 4 - proposed build and test timetable - gantt.png` |
| `dar_routes.py`, `dar_landing.py`, `dar_bootstrap_setup.py`, `dar_route3.py`, `dar_tables.py`, `dar_timetable.py`, `fix_p1.py` | The successive additions to the technical request: the five routes, the landing zone, the bootstrap and account set-up, route 3, the four tables and connection test, the timetable |
| `tech_update.py`, `tech_release.py` | The 14 October revision: the system on MAIIC's server, AnyDesk-supervised runs, the pull-before-test release steps, pack 10 in the annex, the grant list rebuilt from the register |
| `register.py` | Reads the query register (`database/seeders/EbankerQuerySeeder.php`) into tables and query ids (114 queries after pack 10) |
| `register_sections.py`, `security_sections.py`, `finish_docs.py` | The register-and-Oracle sections, the configuration-and-security sections, and their insertion into both documents |
| `patch_render.py`, `patch_toc.py` | The renderer changes (logo, justification, unsplit tables and figures; table of contents) |
| `verify_human.py` | Checks the humanised documents against snapshots taken before editing: front matter, headings, figures, code spans, table rows and labels, and every number unchanged |
| `contact.py` | Contact sheets of every PDF page, used to check that no table or figure overlaps or breaks badly |

## Pack 9 and the December models

| Script | What it does |
|---|---|
| `pack9_run8_split.py` | Splits pack 9's RUN 8 (OVERDUE_DETAILS) into 8a, 8b and 8c after the 100 MB failure, updates the all-runs sheet, README and PDF |
| `pack9_results.py`, `cv10_tie.py`, `cv10_tie2.py`, `takeon_dump.php`, `cv04_gl.php` | The pack 9 results review: row counts against RUN 1, OPENING_BAL tied to the 31 July 2024 take-on postings, the GL opening balances tied to the pack 7 ledger copy |
| `dec_models_scan.py`, `dec_models_dump.py`, `dec_models_cells.py`, `dec_models_probe.py`, `sys_dec2025_rbm.php` | The December 2025 ECL models read cell by cell against the directive, and the system's December 2025 classification for comparison |

## Others

`make_transcript_builder.py` makes the transcript builder from the 9 October one; `chk.py`, `fix_rbm_texts.py`, `morat.php` and `why500.php` are small checks run during the session.
