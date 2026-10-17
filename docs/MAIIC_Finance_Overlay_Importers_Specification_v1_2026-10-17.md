---
kicker: MAIIC IFRS 9 and EIR system
title: Reconciling what MAIIC supplies against what the system rebuilds
subtitle: A plain-language spec for the arrears-ageing and gross-loan-amount overlay importers, for review before building
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT
status: Draft for review — not built
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this document is about, in one sentence

MAIIC sometimes supplies its own view of a month's loans — an ECL workbook, an audited financial statement — and our system separately rebuilds its own view from E-Banker's raw data; this document proposes two small, governed tools that let Finance record, loan by loan, where the two disagree and why, without ever changing what the system calculates on its own — and asks you to confirm the design, and one real consequence it has for how this system proves itself correct, before any of it is built.

## 2. Background, in plain terms

Our system does not take MAIIC's word for what a loan's balance or arrears position is. It reads E-Banker's raw extracts — the ledger, the loan master, the Loan Book Report — and works the figures out for itself, the same way every month. That is deliberate: it is the whole point of having a system rather than trusting a spreadsheet.

But MAIIC also, separately, produces its own figures from time to time: an ECL model built in Excel for a reporting date, or the audited financial statements (the "AFS") a firm of auditors has signed off on. These are not inputs our system reads — they are **a second, independent opinion**, and when our system's own rebuilt figure disagrees with that second opinion, that disagreement is worth recording properly rather than either ignoring it or silently overwriting our own calculation to match.

This has already happened once, for December 2025's arrears ageing: a long investigation (recorded in `MAIIC_Loan_Book_Report_Point_in_Time_Specification_v1` and `MAIIC_December_2025_Loan_Book_Investigation_Findings_v1`) found that for 36 loans, the system's own rebuilt arrears bracket disagreed with MAIIC's December 2025 ECL workbook. Most of that gap turned out to be real, fixable defects in how our system was reading E-Banker's data — but a genuine, unresolved residue is left, and a small Finance-facing tool (a CSV, filled in and re-imported) was built to let someone record a position on it, loan by loan, without it ever touching the system's own calculation.

This document proposes to do two things properly, rather than as a one-off: generalise that tool so it can be used for **any** period MAIIC supplies a reference for, and build a second, parallel tool for the loan's **gross amount** (the balance itself, not just how overdue it is) — because the same kind of disagreement can exist there too, and it matters more: a balance disagreement moves the loan book's total size, not just its stage.

## 3. What we can actually check this against today — read this before anything else

Before describing the tools, it matters what reference data genuinely exists to check against, because the honest answer shapes what these tools can do on day one.

**For December 2025, two real references exist:**
- MAIIC's own December 2025 ECL workbook (`docs/bootstrap/models/ECL model 14 Dec 2025 - portfolio report (values).csv`), loan by loan, with a `gross_loan` column and an arrears bracket. In that workbook, `gross_loan` is identical to `carrying_amount` for every one of its 108 facilities — the two never disagree, in what MAIIC supplied, within December 2025.
- The AFS bridge for December 2025 (`docs/bootstrap/trial-balances/afs-bridge-2025-12/`), which reconciles the trial balance — the general ledger level — to the audited financial statements. This is a GL-code-level bridge, not a loan-by-loan one: it tells us MAIIC's whole loan book GL balance against the AFS, not which individual loan drives a difference.

**For every other period, no loan-level external reference is currently committed to this repository.** There is no month-by-month "MAIIC's own loan book, as MAIIC sees it" file for January 2025, or June 2026, or any month besides December 2025. The monthly trial balances (`docs/bootstrap/trial-balances/monthly/`) exist for every month and could support a GL-level check for every period, but that is a different, coarser check than the loan-level one these two overlays are for.

**What this means for scope:** the loan-level overlay tools proposed here can be *built* generally, for any period — but they can only be *filled in* for December 2025 today, because that is the only month with a loan-by-loan external reference to compare against. Extending real coverage to another month needs MAIIC to supply a loan-level reference for that month first; this document does not assume one will appear. If what was meant by "all periods" is the monthly trial balances instead of a loan-level workbook, that is a materially different, GL-level tool, not the one described below — worth confirming before anything is built.

## 4. The two overlay importers, in plain terms

Both tools follow the exact same shape as the one already built and used for December 2025's ageing. Neither one ever **calculates** anything, and neither one ever **changes** what the build reads from E-Banker. Both only ever **record**, and the record is designed so it can never be misread:

- A CSV is generated from the system's own rebuilt figures for the period in question — "backfilled", so Finance never has to type in what the system already knows.
- Every row's own comment states, in words, what the system's own figure was at the moment the file was generated — not just in a separate column, in the sentence itself — so the comment can never be read without knowing what it is responding to.
- Finance fills in their own figure and a reason, for as many or as few rows as they want to comment on. Most rows need no change at all.
- The completed file is imported back in. Import is maker-checker: one person proposes, a different person approves, and every step — propose, approve, reject, withdraw — is written to the audit log with who, when, and what was decided.
- Nothing imported this way is ever treated as fact by the build on its own. A **separate**, explicit governance decision (section 5) decides whether, and for which specific loans, an imported position actually changes what gets booked.

### 4.1 The arrears-ageing overlay (already built for December 2025; generalised here)

What it reconciles: the arrears bracket (current, 1-30 days, 31-90, and so on) the system's staging produces for a loan, against the bracket MAIIC's own supplied workbook shows for the same loan and period.

What changes from what already exists: today's version (`LoanAgeingOverlayService`) is hard-coded to the 36 facilities from the December 2025 investigation. The generalised version takes any period and any set of contract ids, so it can be re-run for a later month the moment MAIIC supplies a loan-level reference for it — without writing a new one-off script each time.

### 4.2 The gross loan amount overlay (new)

What it reconciles: the loan's carrying amount — its balance, as the system rebuilds it from the ledger — against the gross loan amount MAIIC's own supplied reference shows for the same loan and period.

Why this is a different, and in one sense more serious, question than ageing: a disagreement on arrears bracket moves which stage a loan sits in and how much allowance it attracts. A disagreement on the gross amount moves the size of the loan book itself — the total exposure the financial statements report. The same investigation technique applies (trace the loan's full history, rule out hypotheses with evidence, never guess), but the stakes of getting it wrong, or of acting on a match without understanding why it matched, are higher.

This tool does not exist yet. Building it is new work, not a generalisation of something already proven.

## 5. The one real consequence this has, which needs a decision before anything is built

This system proves itself correct, after any change, by rebuilding from a clean copy of every file in `docs/bootstrap/` and checking the result against a fixed set of numbers in `docs/bootstrap/golden.json` — the "golden numbers". That file already pins December 2025's loan book by stage: how many loans are in Stage 1, 2 and 3, and the ECL for each, to the cent. It was last set on 15 October 2026 and required Edward's written approval to change, because changing it means accepting a new set of numbers as the system's new baseline.

**If an overlay is ever allowed to actually change a booked figure — not just be recorded alongside it — the golden numbers for that period will move.** The December 2025 ageing investigation's own evidence (JAT and BenCal, found by the independent reviews) would, if acted on, move two loans' stage and the ECL by stage for December 2025 — exactly the numbers `golden.json` pins.

That means one of two things has to be true before the overlay CSVs are "covered by the bootstraps" in the sense of actually affecting a rebuild:

- **Either** the overlay's recorded positions are read and applied automatically during `eir:bootstrap`, in which case `golden.json` must be deliberately reset afterwards, with the same kind of explicit approval every earlier reset required — this is not a mechanical step, it is the same governance decision as any other change to what this system treats as true;
- **Or** the overlay CSVs are committed to the repository and loaded into the landing zone as **reference data only** (so anyone running the bootstrap can see Finance's recorded position, and the files are protected by the same gates and manifests as everything else here) **without being read by the loan book build**, in which case golden.json is untouched, and a later, separate, explicit decision is what actually acts on any of it.

**This document's recommendation is the second option.** It gives you the CSVs, committed, versioned, gated, exactly where the other bootstrap files live — which is almost certainly what "the CSVs are in the bootstrap folders and covered by the bootstraps" means in practice — without quietly deciding, as a side effect of a filing convention, that December 2025's golden ECL by stage should change. Section 7, decision 1, asks you to confirm this.

## 6. What "covered by the bootstraps" means concretely, if the recommendation above is accepted

1. The overlay CSVs live in `docs/bootstrap/models/`, beside the ECL workbook they were checked against — already true for the December 2025 ageing file.
2. They are added to the top-level `docs/bootstrap/manifest.json`, with their own SHA-256, the same way every other bootstrap file is registered, so the gates can prove the file a server lands is byte-for-byte the one committed here.
3. A new bootstrap step (after step 3d, the account exclusions) lands them into the landing zone as their own query family — read-only reference data, the same way the AFS bridge and the S&P grading sheets are landed today — and a line is added to `docs/bootstrap/README.md`'s table describing what they are and that nothing yet reads them for a calculation.
4. `eir:bootstrap --verify` continues to check exactly what it checks today. Nothing about the golden numbers changes because these files exist.

## 7. Decisions needed before this is built

| # | Decision | Who |
|---|---|---|
| 1 | Confirm the recommendation in section 5: overlay CSVs are bootstrap reference data only, read by nothing, until a separate decision acts on a specific loan | Edward |
| 2 | Confirm the scope correction in section 3: no other period has a loan-level external reference today; "all periods" means the tool is built generally, not that every month will have real data in it immediately | Edward |
| 3 | Confirm whether "gross loan amount" means the loan's carrying amount against MAIIC's supplied `gross_loan` figure (section 4.2), or something else (for example, the AFS trial-balance bridge at GL level, which is a different, coarser tool) | Edward |
| 4 | If and when a specific loan's override (like JAT or BenCal) is to actually change a booked figure, decide then — as its own, explicit, written decision — whether and how `golden.json` is reset to match, the same way every earlier reset was | Edward, when that day comes |

## 8. What gets built once these are confirmed

1. Generalise `LoanAgeingOverlayService` to take any period and contract list (small change: the December 2025 hard-coding moves out of the service and into a one-off script, as it already mostly is).
2. Build the new, parallel gross-loan-amount overlay service and importer, same shape, same governance, same comment-states-the-original-figure rule.
3. Register both tools' output files in the bootstrap manifest and README, and add the read-only landing step described in section 6.
4. No change to `golden.json`, to the loan book build, or to any engine. Nothing calculated by the system changes as a result of this work.

Nothing in this document builds anything yet. It is here for you to confirm sections 5 and 7 before any code is written.
