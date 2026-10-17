# Background note for Claude and Copilot: the December 2025 loan book investigation

Date: 17 October 2026, revised after independent review
Author: Claude (this session), for Edward Mazibuko

## Update: this note was wrong about one thing, and Codex/Copilot found a real lead

The investigation below originally called 26 of the 36 facilities "unexplained — every pull agrees, the data is clean." **That was not fully checked and turned out to be wrong for part of it.** Codex and Copilot were separately asked to review this investigation (briefs in `docs/reviews/Message_to_Codex_December_2025_2026-10-17.md` and `docs/reviews/Message_to_Copilot_December_2025_2026-10-17.md`; their full reviews in `docs/reviews/Codex_Review_December_2025_Loan_Book_Investigation_2026-10-17.md` and `docs/reviews/Copilot_Review_December_2025_Loan_Book_Investigation_2026-10-17.md`). Two things came out of that, both verified directly against the current code before being accepted:

1. **9 of the 36 facilities do trip the original section 6.1 stable-field conflict check**, when the current code is run fresh. The first draft of the findings note had not actually re-checked the specific 36 facilities against that flag — it quoted an aggregate conflict count from the 27-month diagnostic without mapping it back to which December facilities it covered. If you are asked to characterise a specific set of records as "clean" against some check, always re-run that exact check against that exact set — do not infer it from an aggregate figure computed for something else, even if it feels obviously related. That mistake is now the second one recorded in this note (see the first one below, about judging a time series from two months).
2. **Independent ledger/chart reconstruction — not the report's bucket fields, but the actual dated ledger receipts matched against the instalment chart in force — matches MAIIC's workbook on 5 of the 26**: JAT Group Ltd (104430000087), BenCal Technologies Ltd (104460000059), Mphanga Holdings Limited (104460000066), Pamela Investments (104450000074), Chuma Mkandawire/TA Chikoma Estates (104450000060). For JAT specifically, the build's own `daysPastDue()` already computes the right day count (69 days) from the ledger; the single-ladder staging policy is configured to use the raw report bucket count instead (1 day), discarding information the build already has. This is a real, narrow lead, not a general fix — the same reconstruction was tried on the other 21 chart-backed facilities among the 26 and still disagreed with the workbook on 18 of them, and 3 have no chart available at all.

The corrected split is 23 of 36 explained or flagged (up from 10), 13 genuinely unexplained (down from 26). The findings note has been revised to match; this background note below is also revised but keep the methodology notes as written — they are still accurate about what was done, just not about what it proved.

A Finance ageing-overlay tool was built as a result, rather than changing the staging policy outright (that is a governance decision for Edward and Dr Thom): `app/Services/Eir/LoanAgeingOverlayService.php`, importable via `php artisan eir:import-ageing-overlay`. It records Finance's own position on a loan's ageing beside the system's own figure — never replacing it, and the system's own bracket is always stated in the comment text itself, not just a column, so a comment can never be read without knowing what it is responding to. The backfilled template for all 36 facilities, with this investigation's own finding already written into each differing loan's comment, is at `docs/bootstrap/models/Finance Ageing Overlay - December 2025.csv`, alongside the ECL workbook files it was checked against.

## Why this note exists

This investigation ran long and touched a lot of ad hoc SQL and scratch scripts that no longer exist on disk (they lived in a session-specific temp directory, not the repo). If you are a future Claude or Copilot session picking this up — to review the fix, extend it, or carry on with the 13 still-unexplained facilities — this note gives you the exact methodology, queries, and reasoning so you do not have to re-derive any of it from scratch. Read this alongside `docs/MAIIC_Loan_Book_Report_Point_in_Time_Specification_v1_2026-10-16.md` (the fix itself) and `docs/MAIIC_December_2025_Loan_Book_Investigation_Findings_v1_2026-10-17.md` (the human-facing findings note this background supports, now revised to match the update above). Nothing here is new content; it is the working behind that document, kept precise for whoever picks it up next.

## The chain of three defects, and where each lives in code

1. **Re-pull drift** (the original bug): `LandingZoneReader::latestRunsOn()` in `app/Services/Ebanker/LandingZoneReader.php`. E-Banker's Loan Book Report (`P2_08`/`M09_03`/`MF_05`) is re-run many times for the same closed `ASONDATE`, each run getting a fresh `LOAN_BOOK_DET_ID_A` (so it lands as a genuinely new row, not a version of the old one — `PackLandingService::landFile()`'s versioning never sees it). The old rule picked the highest `source_key` (effectively the latest run). The fix prefers the earliest pull by its own `TRANSACTION_DATE`, but only once `CARRYING_AMOUNT`, `ARREAS_TOTAL` and `OVERDUE_PRINCI_DATE` agree across every candidate pull (`LandingZoneReader::stableFieldsAgree()`, public static). Disagreement there returns the pre-fix choice with a `pick_conflict` key.

2. **Self-contradictory buckets**: `LandingZoneReader::bucketsLookBroken()`. A pull where `DAY_1_30` through `DAY_271_360` sum to ~0 while that same pull's own `ARREAS_TOTAL` is clearly positive. Found by cross-referencing E-Banker's raw bucket fields directly — see the SQL below. This is unrelated to drift between pulls: the three stable fields above can all agree across every pull and still all be internally wrong in the same way, because the contradiction is within one pull, not between pulls.

3. **Cross-month date jump**: `LoanBookBuildService::overdueDateJumpFlag()` and `priorOverdueDates()`. The reported `OVERDUE_PRINCI_DATE` jumps to an implausible value from one month-end's booked figure to the next — either before the loan's own value date, or by more than 150 days. This is a *different* axis again: it's not about which pull within one month to trust, it's the booked figure itself discontinuing across months, where every pull of the new month agrees on the (wrong) value, so neither of the first two checks sees it.

All three only ever flag (`pick_conflict` folded into `build_flag` via `LoanBookBuildService::rowsA()`/`rowsB()`); none of them guess or silently correct a value. That is a deliberate, repeated pattern in this codebase ("fail closed, never estimate") — do not change that if you extend this further.

## The database used, and why

This machine has several `maiic_ifrs9*` databases. The `.env` default, `maiic_ifrs9`, is missing the E-Banker landing zone entirely (no `ebanker_raw_rows` table) — do not use it for this kind of investigation. `maiic_ifrs9_bootstrap` was confirmed to hold the real landed data (795,100 raw rows, loan books from 2024-07 to 2026-09, 123 accounts with duplicate December 2025 pulls) and was the one the user designated as the target for this work. If you need to repeat or extend any of this, connect to `maiic_ifrs9_bootstrap` explicitly (`DB_DATABASE=maiic_ifrs9_bootstrap`, or `config(['database.connections.mysql.database' => 'maiic_ifrs9_bootstrap'])` inside a bootstrapped script) — never assume the `.env` default is the right target without checking first. For anything that writes (a loan book rebuild), use a throwaway copy made with `mysqldump`/`mysql` piped together, and drop it afterward; never write to `maiic_ifrs9_bootstrap` itself without being asked.

## The December 2025 workbook: what it is and its limits

`docs/bootstrap/models/ECL model 14 Dec 2025 - portfolio report (values).csv` is a **values-only** export (formulas already stripped) of MAIIC's own December 2025 ECL model, 108 facilities, matched to the system's loan book by customer name and exact balance (see `app/Console/Commands/StagingDecemberCheck.php`, which already existed before this session and does this matching — read it before writing a new matcher). Key columns: `age_bracket_old`, `age_bracket_revised`, `workbook_stage` (`workbook_stage` is derived from `age_bracket_revised` per the CSV's own provenance comment line), `overdue_principal`, `overdue_interest`, `b_1_30` through `b_above_360` (two overlapping bucket schemes), `classification` (an S&P-style internal credit grade), `business_unit`.

Two columns looked promising and turned out to be **dead weight across the entire 108-row workbook, not just the 36 mismatches** — check this yourself before trusting either:
- `overdue_principal` and `overdue_interest` are 0 for all 108 rows.
- `b_181_270`, `b_271_360`, `b_above_360` are blank (not even "0") for all 108 rows.

Do not re-waste time on these two; they were checked exhaustively (see the hypothesis table in the findings note, section 7) and ruled out as uninformative, not as evidence of anything.

There is a companion file, `ECL model 14 Dec 2025 - S&P grading sheets (values).xlsx` (two sheets: `ASS1-Internal Grades`, `PDs-S&P`) — this is reference/lookup data for the `classification` column (an internal-grade-to-S&P-rating PD table), not loan-level data. It does not help distinguish the 36 mismatches; `classification` is a mix of grades in both the matched and mismatched groups.

## The exact SQL used for the two new defects

**Self-contradictory buckets** (per pull, per account, at `row_date = '2025-12-31'`):

```sql
SELECT source_key,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.DAY_1_30')) AS d1_30,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.DAY_31_91')) AS d31_91,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.DAY_91_180')) AS d91_180,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.DAY_181_270')) AS d181_270,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.DAY_271_360')) AS d271_360,
  JSON_UNQUOTE(JSON_EXTRACT(payload,'$.ARREAS_TOTAL')) AS arrears_total
FROM ebanker_raw_rows
WHERE query_id IN ('P2_08','M09_03','MF_05') AND account = :account
  AND row_date = '2025-12-31' AND superseded_at IS NULL
ORDER BY CAST(source_key AS UNSIGNED);
```
Flag when all five `DAY_*` columns sum to ~0 but `ARREAS_TOTAL` is clearly positive. Threshold used in code: buckets sum `< 0.01`, `ARREAS_TOTAL > 0.01` (`LandingZoneReader::bucketsLookBroken()`).

**Cross-month jump**: pulled `loan_books.overdue_principal_date` for consecutive `reporting_period`s for a given `contract_id`, computed the day-difference, and separately checked `overdue_principal_date < create_date` (the loan's own value date). The 150-day threshold was set empirically: across all 36 facilities' full history (traced back to loan inception, in some cases to 2020), the largest month-to-month move among facilities later judged "clean" was 141 days (ordinary bi-monthly/quarterly instalment schedules); every move beyond 150 days coincided with an implausible value (a date before disbursement, or a date far in the future). If you refine this threshold, re-run the full-history trace first — do not just eyeball two months, as this session initially did and then had to correct (see "A mistake worth knowing about" below).

## The account lists (December 2025, `maiic_ifrs9_bootstrap`)

All 36 facilities the December 2025 workbook check disagrees with the system on (contract ids, no leading zeros — E-Banker's own account field has three leading zeros, e.g. `000104430000002`):

```
104430000002 104430000004 104420000005 104420000007 104420000012 104450000023
104450000046 104420000052 104450000077 104450000056 104460000059 104450000063
104460000066 104450000069 104460000070 104450000072 104450000074 104450000076
104460000080 104450000060 104460000083 104460000087 104450000091 104460000092
104430000062 104460000098 104430000068 104460000107 104430000070 104430000071
104460000109 104460000110 104460000112 104430000082 104430000087 104430000089
```

Of these, explained by the self-contradictory-bucket defect: `104430000002 104430000004 104450000046 104420000052 104450000056 104450000076 104450000091` (7).
Explained by the cross-month jump defect: `104430000002 104430000004 104420000007 104420000012 104420000052 104430000089` (6).
(Three overlap: `104430000002 104430000004 104420000052` show both.)

Flagged by the original section 6.1 stable-field conflict check, run fresh (not from stale stored data — see the warning below): `104430000002 104420000052 104430000062 104430000068 104430000070 104430000071 104430000082 104430000087 104430000089` (9).
Matches the workbook under independent ledger/chart reconstruction (Codex and Copilot reviews): `104460000059 104460000066 104450000074 104450000060 104430000087` (5; `104430000087` overlaps the conflict list).
No instalment chart available to reconstruct against at all: `104460000107 104460000110 104460000112` (3).

**Unexplained — 13 remain** (the union of every category above removes 23 of the 36): `104420000005 104450000023 104450000077 104450000063 104450000069 104460000070 104450000072 104460000080 104460000083 104460000087 104460000092 104460000098 104460000109`. Listed in full, with the corrected "Explained by" column, in the findings note's section 8 table.

**Warning if you query `maiic_ifrs9_bootstrap` directly:** its stored `loan_books` table is the original, pre-fix build — nobody has ever written the fixed figures back into it. `build_flag` on that stored table will read empty for facilities the *current* code would flag. To see what the current code actually does, call `LoanBookBuildService::rowsFor($period, $method)` fresh (it does not write) rather than reading the stored table. This is exactly the mistake that produced the overreach corrected above.

## A mistake worth knowing about, if you extend this

Partway through, this session flagged 13 of the 36 as "a December-specific anomaly" based on comparing only November and December 2025. That was wrong, and was caught and corrected before being finalized: tracing the *full* history (back to loan inception) showed that 60-to-92-day jumps happen constantly, in all directions, all year, for loans with bi-monthly or quarterly instalment schedules — it's normal behaviour, not corruption. Only jumps beyond ~150 days, or a date predating the loan's own disbursement, are genuine anomalies. **If you are asked to look for "implausible" data in a time series like this, always pull the full history before judging a single month-pair** — a two-point comparison cannot tell normal periodic behaviour from a real defect.

## What's left, concretely, if you want to continue

The 13 still-unexplained facilities are not a code problem. Every hypothesis testable from the available extracts has been tried and ruled out (full list in the findings note, section 7) — do not re-run those same checks without a new idea; re-read that table first, and note that the ledger/chart reconstruction (which did find something, on 5 *other* facilities) has already been tried on these 13 too and still disagrees. The only paths that could still close this gap:

1. **Get the live workbook, not the values-only export.** If MAIIC can supply the actual `.xlsx` with formulas intact (not a paste-as-values snapshot), the formula behind `age_bracket_revised` for the 13 named facilities would likely answer this directly.
2. **Ask a person**, now via the overlay file below, rather than by further code. Whoever at MAIIC Finance or Credit prepared `MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx` (see the CSV's own provenance line for that filename and its SHA-256) can say what source or method set these brackets.

**Do not build the staging-policy change yourself.** The 5-facility ledger/chart lead in the update at the top of this note is real, but it is a governance decision (should the single-ladder policy ever prefer a schedule-corrected count over the raw report bucket) for Edward and Dr Thom, not something to wire in. What was built instead is a recording mechanism: `app/Services/Eir/LoanAgeingOverlayService.php` (export a CSV backfilled from the loan book, with the system's own bracket always stated in the comment text; import a completed one back in, keyed on contract id and reporting period, never recomputing anything) and `php artisan eir:import-ageing-overlay {file} --user=`. The backfilled template for this investigation's 36 facilities is `docs/bootstrap/models/Finance Ageing Overlay - December 2025.csv` — it already carries each facility's specific finding in its comment (conflict-flagged, ledger-matched, no-chart-available, or genuinely unexplained), and Finance fills in `finance_days`/`finance_bracket`/`comment`. If you regenerate this file, pull `staging_dpd` fresh via `rowsFor()`, not from the stale stored table (see the warning above), and keep the per-facility category tags consistent with the account lists above — a past version of this exact script had a bug where PHP silently casts a purely-numeric string array key to an integer, breaking strict (`true`-flagged) `in_array()` checks against string-valued lists; cast back to `(string)` before any such check.

## Tests to know about

- `tests/Feature/Ebanker/LoanBookReportPointInTimeTest.php` — the re-pull and self-contradictory-bucket checks, at the `LandingZoneReader` level.
- `tests/Feature/Ebanker/LoanBookRunDriftNoticeTest.php` — the landing-time drift notice (audit log on re-pull of an already-landed month).
- `tests/Feature/Ebanker/OverdueDateJumpFlagTest.php` — the cross-month jump check, at the `LoanBookBuildService` level.
- `tests/Feature/Ebanker/LoanBookBuildServiceTest.php` — pre-existing; one fixture (`test_method_a_copies_the_stored_run_and_derives_marked_where_none_exists`) happens to be a genuine stable-field conflict and was updated to assert the new flag rather than `null`.
- `tests/Feature/Eir/LoanAgeingOverlayTest.php` — the Finance overlay export/import.

Full regression (`php artisan test --testsuite=Feature --filter="Ebanker|Eir|Rbm"`) passed clean after each change in this session. Re-run it after any further change here — it takes about 6-7 minutes.
