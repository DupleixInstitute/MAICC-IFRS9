"""Spec v4: section 6.11 the one-command bootstrap from committed inputs (the suite's method); 6.9 and 6.7 point to it."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)

sec = '''### 6.11 The bootstrap: a clean install that loads itself and proves itself

The Dupleix suite installs a client system with one command that wipes a clean database, seeds it, loads the client's own input files from a folder committed in the repository, runs the engines and checks the result against the golden numbers. MAIIC adopts the same method (decision D26), so that any server, including Deloitte's copy and the UAT copy, is built from nothing to a proven state without anyone sending a file.

**The committed inputs.** `docs/bootstrap/` in the repository holds, exactly as received and never re-saved: the E-Banker pack of 7 October 2026 (the 18 extracts and the 5 afternoon queries, 23 files), the data-dictionary results of 6 October (24 files), the take-on workbook with its fee columns, the SQL that produced the pack, and `manifest.json` with every file's query id, row count and SHA-256 and the accepted exceptions the gates allow. The committed copy is read first; a OneDrive path is only a local fallback for development. A replacement, for example the workbook Finance returns with the fees filled in, is a new file beside the old one and a new manifest entry; nothing in the folder is edited in place. The files are MAIIC's data (borrower names and balances); the repository is private and access to it is governed as access to the production database is.

**The command.**

`php artisan eir:bootstrap --fresh --with-client-inputs --build --verify`

| Step | What it does | Idempotent |
|---|---|---|
| 1 | `migrate:fresh` on `--fresh`, refused if user data exists unless `--force-wipe` is also passed | Safety, not a step |
| 2 | Seeds: roles and permissions, the Governance Centre defaults (section 4.2), the help centre, the fee rulebook, the compliance-audit modules (section 12) | Yes: a key that exists is left alone |
| 3 | Lands the committed pack into the landing zone by route 1 (section 6.4): the manifest is checked file by file, the gates run, the accepted exceptions are recorded against the load | Yes: a file whose hash is already loaded is skipped |
| 4 | Lands the take-on workbook (section 6.9) and the fees it carries | Yes |
| 5 | On `--build`: builds the loan books for every month from July 2024 to the last month in the pack by the method in force (section 6.2), builds the take-on population under its basis, generates the version 1 schedules | Yes: a locked period is never restated |
| 6 | On `--verify`: runs the baselines of section 9 against the database and prints the table, PASS or FAIL per row, and exits non-zero on any FAIL | Yes |

Anything the bootstrap approves (a load, a build, a generated schedule) is stamped with the approver label "System Bootstrap (automated data-readiness, not a MAIIC approval)", so that no one can mistake it for a sign-off by MAIIC; the maker-checker approvals of the Governance Centre and the register are never given by the bootstrap.

**What it is for.** The first installation on MAIIC's server; every UAT and acceptance round, which starts from a bootstrap so that the result is reproducible; Deloitte's copy; and the developers' own daily state, since a bootstrap with `--build --verify` is also the end-to-end test of the data foundation. The monthly packs of section 6.5 are loaded by the feed, not by the bootstrap; the bootstrap loads what is committed.

'''
rep("### 6.10 The EIR as at any date", sec + "### 6.10 The EIR as at any date")
# renumber: keep 6.10 as is but the bootstrap appears before it; swap the order so 6.10 then 6.11 read in sequence
s = s.replace("### 6.11 The bootstrap: a clean install that loads itself and proves itself", "### 6.10 The bootstrap: a clean install that loads itself and proves itself", 1)
s = s.replace("### 6.10 The EIR as at any date", "### 6.11 The EIR as at any date", 1)
s = s.replace("`eir-as-at.index` (new, section 6.10)", "`eir-as-at.index` (new, section 6.11)")
s = s.replace("`EirAsAtService` (6.10);", "`EirAsAtService` (6.11);")
# 6.7 history load uses the bootstrap
rep("### 6.7 Loading the history, once\n\n1. **Freeze the 7 October pack.** The eighteen follow-up extracts and the five of the afternoon, with their SHA-256 hashes, become pack 1 under route 1. They are never opened in Excel.",
    "### 6.7 Loading the history, once\n\nThe history is loaded by the bootstrap of section 6.10 from the committed inputs; the steps below are what it does and how it is proven.\n\n1. **The 7 October pack is committed** under `docs/bootstrap/ebanker-pack-2026-10-07/` with its manifest and hashes; it is pack 1 under route 1. The files are never opened in Excel.")
# 6.9 where: committed copy first
rep("**Where.** Data Foundation, Take-on Schedules: upload the workbook, see each block",
    "**Where.** The workbook is committed under `docs/bootstrap/takeon/` and loaded by the bootstrap (6.10); the returned version with the fees replaces it as a new file. Data Foundation, Take-on Schedules: upload a workbook (the alternative to the committed copy), see each block")
# D26
rep("| D23 | **The three MAIIC extract scripts are retired.**",
    "| D26 | **One-command bootstrap from committed inputs** (section 6.10): the E-Banker pack, the data-dictionary results, the take-on workbook and the queries are committed under `docs/bootstrap/` with a manifest, and `eir:bootstrap` builds a clean install to a proven state. | Edward, 7 Oct 2026 | Any server, including UAT and Deloitte's copy, is reproducible from nothing; the bootstrap is also the end-to-end test of the data foundation |\n| D23 | **The three MAIIC extract scripts are retired.**")
# section 0: where files are named
rep("Where a file is named, it is one of the extracts in `2. Documents from clients\\Raw Query Scripts\\Query Requests to MAIIC\\Follow-Up Scripts Resutls\\`. The scripts that produced every figure in this document are in `Build files\\` beside them, with a README that says which script makes which number.",
    "Where a file is named, it is one of the extracts committed under `docs/bootstrap/` in the repository (section 6.10), with a copy in `2. Documents from clients\\Raw Query Scripts\\Query Requests to MAIIC\\Follow-Up Scripts Resutls\\`. The scripts that produced every figure in this document are in `Build files\\` beside them, with a README that says which script makes which number.")
# P4b
rep("`EirAsAtService` (6.11);", "`EirAsAtService` (6.11); `eir:bootstrap` with the committed inputs and `--verify` (6.10);")
# acceptance baselines intro
rep("The ties achieved this week become regression tests. A build that cannot reproduce them has broken something.",
    "The ties achieved this week become regression tests, run by `eir:bootstrap --verify` (section 6.10) and shown on the Baselines sheet of every audit workbook (section 12). A build that cannot reproduce them has broken something.")
# glossary
rep("- **Landing zone**:", "- **Bootstrap (the command)**: `eir:bootstrap`, which builds a clean install from the inputs committed under `docs/bootstrap/` and verifies it against the baselines.\n- **Landing zone**:")
open(p, "w", encoding="utf-8").write(s); print("6.10 bootstrap added; 6.11 as-at; D26; 6.7 and 6.9 repointed")
