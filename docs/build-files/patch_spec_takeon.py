"""Spec v4: section 6.9 the take-on schedules; section 7.6 points to it; the takeon_history_basis setting; section 6.10 the EIR as at any date."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)

takeon = '''### 6.9 The take-on schedules

**What they are for.** E-Banker's history of the 109 take-on loans begins on 31 July 2024 with one opening posting each. The effective interest rate needs what happened before that: the origination date, the original principal, the contractual rate and instalment pattern, and the fees charged at the start. Tamanda's workbook of amortisation schedules at 31 October 2024 (100 blocks, mapped on 7 October to 105 of the 109 facilities) is the only record of that. The engine uses it for two things: to solve the EIR at origination and roll the amortised cost forward to 31 July 2024, so that the loan enters E-Banker at its true amortised cost rather than its take-on balance; and as the version 1 schedule of those loans (`schedule_source = TAKEON_WORKBOOK`) in place of a generated one.

**The fees.** Neither the workbook nor E-Banker records the fees charged when a take-on loan was granted, and the EIR cannot be solved without them. The mapping workbook returned to Tamanda on 7 October (`Take-on schedules with mapping - with fee columns - for Tamanda to confirm - 7 Oct 2026.xlsx`) carries, on its Upload summary sheet, seven columns for her to complete per facility: the arrangement fee, the legal fees, any other fee that was a condition of the loan, the date charged, whether the fees were deducted from the amount paid out, the offer letter or receipt the figures come from, and a total. A blank means no such fee; a zero means known to be nil. This replaces the separate fee template of 25 September for the take-on loans (O14 is unchanged in substance: Finance supplies the fees; the system reads them).

**Landed, not typed.** The workbook enters by the feed door as a pack of its own kind: its hash, then two raw tables. `takeon_blocks` holds one row per block: title, principal, rate, term, start date, the mapped account, the mapping confidence, Tamanda's tick, the fees, and the sheet and cell each value came from. `takeon_schedule_lines` holds one row per instalment line: due date, instalment, principal, interest, balance, serial, with its cell reference. Every figure traces to the cell in the workbook she signed; the Upload summary's formulas already point there.

**Gates.** Every block is mapped to one account that exists in the master, or is explicitly marked not matched or refused (the three equity positions); no account has two blocks; the block principal equals the take-on posting (exact on 77) or the difference is named; the schedule's balance at 31 July 2024 is compared with E-Banker's take-on balance and the difference flagged as arrears or prepayment at take-on; every date is unambiguous; serial numbers out of due-date order are sorted by date and the anomaly raised (F16); a mapped facility with no fee row is flagged, never assumed fee-free. A refusal names the block and the cell.

**The build** writes `contract_takeon` per account: origination date, original principal, contractual rate, fees, and the pre-migration cash flows. Those flows are contractual, not actual: nobody holds the receipts before E-Banker. How the engine treats them is a governed setting, `takeon_history_basis`:

| Option | Meaning |
|---|---|
| **Recompute from origination where the block and fees exist, else start at the take-on balance** (seeded) | Where a facility has a mapped block and its fees, solve the EIR on the original schedule and fees, assume instalments were paid as scheduled to 31 July 2024 except where the take-on balance says otherwise, and book that difference as arrears at take-on. Where it has not (the four unmatched facilities, the eight closed accounts without a block, any block still without fees), treat the 31 July 2024 carrying amount as the opening amortised cost with no day-one history, and flag the loan |
| Recompute from origination for every take-on loan | As above, and refuse a loan whose block or fees are missing until they are supplied |
| Start every take-on loan at its take-on balance | No pre-migration history for any of them; the EIR is solved from 31 July 2024 on E-Banker's flows |

The seeded option is the reasonable one: it uses the history wherever MAIIC can evidence it and is honest where it cannot, and every loan carries the basis it was built on so an auditor sees which. Changing the setting rebuilds the take-on population only.

**Where.** Data Foundation, Take-on Schedules: upload the workbook, see each block with its mapping, confidence, tick and fees, the gate results, and the Build with approval. Tamanda may confirm a mapping or enter a fee in the screen instead of in Excel; the screen's entry is the one that counts and is audit-logged. Re-uploading creates a new version; the previous build is kept.

### 6.10 The EIR as at any date

A requirement in its own right: **the system shows the EIR computation for any loan as at any date the user names**, not only at month-ends and not only for the latest run.

- **What "as at a date" means.** The engine takes every posting in the landing zone dated on or before the date, the rate in force on the date, the governance values in force on the date, the schedule version in force on the date, and the take-on basis of 6.9, and produces for that date: the EIR in force and when it was last re-solved, the amortised cost, the gross carrying amount, the interest recognised to the date in the period and the year, the cumulative difference between the EIR interest and the contractual interest posted, the remaining expected cash flows, and the modification history. Each figure is shown with the inputs that produced it.
- **Any date, not only a period end.** A date inside a month uses the actual-day convention of section 3 (prior month-end balance, rate, days over 365) for the part-month. A date in a locked period reproduces the locked figures exactly, because a locked period keeps the settings and the schedule it was locked under; a date after the last loaded pack is refused with the last loaded date named, never estimated.
- **For the whole book.** The same view at portfolio level: the EIR interest, the contractual interest and the difference for the year to the date, by product, by GL and in total, which is the revenue shift Dr Thom asked for, at any date.
- **Where.** The Contract Profile carries a date picker ("View as at") beside the schedule; the Report Hub carries "EIR as at a date" for the book, with the Excel and PDF downloads; both read the same service (`EirAsAtService`), so a figure on the screen and in the download are the same computation.
- **The dates that matter now.** 31 December 2024, 31 December 2025 and 31 December 2026 are the year-ends Deloitte will ask for. Section 9 gains them as baselines once the first two are run.

'''
rep("## 7. The importers, reworked", takeon + "## 7. The importers, reworked")
rep("### 7.6 The take-on loader (new)\n\nReads the Upload summary sheet of the mapping workbook once Tamanda has ticked it: account number, principal, take-on balance at 31 July 2024, rate, term, instalment, first instalment date, fees from the template. Every figure on the sheet is a formula back to Tamanda's original schedule, so the load is auditable to its source.",
    "### 7.6 The take-on loader (new)\n\nAs section 6.9: the workbook landed as `takeon_blocks` and `takeon_schedule_lines`, the gates, the build of `contract_takeon` under the `takeon_history_basis` setting, and the Take-on Schedules screen.")
# settings table
rep("| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |",
    "| Pre-migration history of the take-on loans (`takeon_history_basis`) | Recompute from origination where the block and fees exist, else start at the take-on balance | Recommendation (section 6.9); the loan carries the basis it was built on | new |\n| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |")
# 11.3 screens
rep("| | E-Banker Feed (queries, loads, watermarks, build) | `eir-feed.index` (new, section 6.8) | eir.view; derive needs eir.govern | new |",
    "| | E-Banker Feed (queries, loads, watermarks, build) | `eir-feed.index` (new, section 6.8) | eir.view; derive needs eir.govern | new |\n| | Take-on Schedules (workbook, mapping, fees, build) | `eir-takeon.index` (new, section 6.9) | eir.view; build needs eir.govern | new |")
rep("| **Report Hub** (violet) | IFRS 9 Reports (the full catalogue of 30) | `ifrs9-reports.index` | | Reports |",
    "| **Report Hub** (violet) | IFRS 9 Reports (the full catalogue of 30) | `ifrs9-reports.index` | | Reports |\n| | EIR as at a date (the book, with downloads) | `eir-as-at.index` (new, section 6.10) | eir.view | new |")
# section 5: Tamanda row
rep("| Tamanda | The fee template for the take-on loans (O14) and the explanation of the 2024 arrangement fee of MWK 1.34 billion (O23) | Fee template of 25 Sep; the 2024 question in version 3 |",
    "| Tamanda | The fees of the take-on loans, in the yellow columns of the returned workbook (O14), and the explanation of the 2024 arrangement fee of MWK 1.34 billion (O23) | The workbook with fee columns of 7 Oct; the 2024 question in version 3 |")
# baselines
rep("| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked |",
    "| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked; principal equals the take-on posting on 77 |")
# P4b and P8
rep("the build by all three methods (the report importer landed and gated);", "the build by all three methods (the report importer landed and gated); the take-on schedules landed, gated and built (6.9); `EirAsAtService` (6.10);")
rep("| **P8 Month-end run and screens** | Pipeline, period lock, Contract Profile, Rate Resets, Restructures, Drawdowns, Month-end Run; help articles; the journal proposal reading the true-up account (O10) |",
    "| **P8 Month-end run and screens** | Pipeline, period lock, Contract Profile with the as-at date picker, Rate Resets, Restructures, Drawdowns, Month-end Run, the EIR-as-at-a-date report; help articles; the journal proposal reading the true-up account (O10) |")
# glossary
rep("- **Landing zone**:", "- **As at a date**: the computation of a loan's EIR and amortised cost using only what was posted, in force and locked on that date; any date, not only a month-end.\n- **Take-on basis**: whether a take-on loan's EIR is recomputed from its origination (schedule and fees) or started at its 31 July 2024 balance; recorded on every take-on loan.\n- **Landing zone**:")
open(p, "w", encoding="utf-8").write(s); print("6.9 take-on and 6.10 as-at added; setting, screens, baselines, phases, glossary updated")
