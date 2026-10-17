# Review brief: December 2025 loan book investigation

Paste this to Codex or Copilot. Save Codex's response as `docs/reviews/Codex_Review_December_2025_Loan_Book_Investigation_2026-10-17.md` and Copilot's as `docs/reviews/Copilot_Review_December_2025_Loan_Book_Investigation_2026-10-17.md`, matching the existing review files in this folder.

---

## The task

Review and independently verify a Claude session's investigation into why the MAICC-IFRS9 system's December 2025 loan-book arrears figures disagree with MAIIC's own December 2025 ECL workbook on 36 facilities. Read these three documents first, in this order:

1. `docs/MAIIC_Loan_Book_Report_Point_in_Time_Specification_v1_2026-10-16.md` — the original bug and fix (E-Banker's Loan Book Report gets re-run many times for the same closed month, and the arrears-ageing columns drift each time; the fix picks the earliest trustworthy pull instead of the latest).
2. `docs/MAIIC_December_2025_Loan_Book_Investigation_Findings_v1_2026-10-17.md` — the findings note: of 36 facilities disagreeing with MAIIC's workbook, 10 are now explained by two further defects found along the way (a pull whose own arrears buckets contradict its own total-overdue field; the reported overdue date jumping implausibly between month-ends). The other 26 are traced back through their full history and found clean, consistent, and unexplained by anything in the available data.
3. `docs/reviews/Background_for_AI_Assistants_December_2025_Loan_Book_Investigation_2026-10-17.md` — the technical background: exact SQL used, the account list, code locations (`app/Services/Ebanker/LandingZoneReader.php`, `app/Services/Ebanker/LoanBookBuildService.php`), and a mistake the session made and corrected (initially judging a two-month comparison as "anomalous" when the full history showed it was normal periodic behaviour).

Then look at the actual code changes (`git log --oneline -5` on branch `eir_revenue_recognition`, or `git show` the relevant commits) and the new tests (`tests/Feature/Ebanker/LoanBookReportPointInTimeTest.php`, `tests/Feature/Ebanker/LoanBookRunDriftNoticeTest.php`, `tests/Feature/Ebanker/OverdueDateJumpFlagTest.php`).

## What to actually check

Don't just re-read my SQL and nod — go to the data yourself:

- Database: `maiic_ifrs9_bootstrap` (MySQL, local). **Not** the `.env` default (`maiic_ifrs9`), which is missing the E-Banker landing zone table entirely — check this yourself before trusting any other database on this machine.
- The landing zone is `ebanker_raw_rows` (one JSON `payload` column per extracted row, keyed by `query_id`, `account`, `row_date`, `source_key`). The Loan Book Report query ids are `P2_08`, `M09_03`, `MF_05`. Figure out the payload's shape yourself from a handful of real rows rather than trusting field names quoted in the background note — the background note gives you a starting point, not a substitute for looking.
- The built loan book is `loan_books`, one row per `contract_id` per `reporting_period`.
- The December 2025 workbook extract is `docs/bootstrap/models/ECL model 14 Dec 2025 - portfolio report (values).csv` — note its own provenance comment line at the top (sha256, source filename). It's a values-only export; no formulas survive.

## What I actually want from you

1. **Verify the two new defects are real and the fix is sound** (not just "the tests pass" — check whether the 150-day threshold and the "all buckets nil but ARREAS_TOTAL positive" rule are the right thresholds, or whether you'd draw the line differently, and why).
2. **Independently re-attempt the 26 unexplained facilities.** The findings note lists eleven hypotheses already tried and ruled out (section 7 of the findings note) — don't repeat those without new evidence. But you have fresh eyes and no assumptions baked in from the earlier back-and-forth; look at the schema yourself, pull the raw payloads for a few of the 26 facilities, and see if there's an angle the Claude session didn't think to check. In particular: the workbook CSV has columns the Claude session may not have fully exhausted (`product`, `sector`, `value_date` precision, `count` — the row's own ordinal position in the workbook, which might hint at a batch/tab boundary). Worth a look.
3. **Flag anything that looks wrong**, not just gaps — if you think a conclusion in the findings note is mistaken, say so and show the evidence.

Keep the response as a markdown file, concise, evidence-led — numbered findings with what reproduces and what doesn't, same style as the existing `docs/reviews/Codex_Review_Held_Fee_Classification_Export_Reimport_2026-10-16.md` for format reference.
