---
kicker: MAIIC IFRS 9 and EIR system
title: Fixing a date problem in E-Banker's own arrears report
subtitle: Explained from first principles, for anyone, with the proof and the fix
date: 16 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT
status: Built and verified against a throwaway copy of December 2025 (16-17 October 2026); not yet approved as the new baseline (section 8, decision 4)
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this document is about, in one sentence

When our system asks E-Banker, MAIIC's core banking system, "how overdue was this loan as at 31 December 2025?", E-Banker does not actually answer that question honestly — it answers a different question, "how overdue is this loan **today**?", and our system has been taking that answer and filing it under December 2025 without knowing the difference. This document explains the problem so that anyone can follow it, proves it with a real example, and sets out how to fix it.

## 2. Some background, for anyone who has never worked with this kind of system

You do not need any computer or accounting background to follow this. We will build it up from nothing.

### 2.1 What E-Banker is

MAIIC does not keep its loan records on paper, and it does not keep them in a spreadsheet either. It keeps them in a piece of software called **E-Banker**, made by a company called Virtual Galaxy. Every loan MAIIC has ever given out, every payment a borrower has ever made, and every month's worth of interest charged, is a row in one of E-Banker's tables, somewhere inside a big database.

### 2.2 What our system is, and why it needs E-Banker's data

Separately, Dupleix Institute is building a system for MAIIC that works out, for IFRS 9 (the accounting rule for how banks must measure and report loan losses), three things every month:
- how much money MAIIC is likely to lose on loans that are not being repaid (called the **expected credit loss**, or **ECL**),
- how much interest MAIIC should recognise as having been earned on each loan (the **effective interest rate**, or **EIR**), and
- how the Reserve Bank of Malawi's own rules would classify each loan (**Pass**, **Special mention**, **Substandard**, **Doubtful**, or **Loss**).

Our system does not keep its own separate record of every loan from scratch. It reads E-Banker's own data, every month, and calculates on top of it. If what it reads from E-Banker is wrong, everything built on top of it is wrong too, even though the calculation itself is correct. This document is about exactly that: a problem in what our system reads, not in what it calculates.

### 2.3 What "as at a date" means, and why it matters so much here

Here is the single idea everything else in this document rests on.

Imagine you ask someone, "how late was Mary's rent in March?" There are two completely different ways to answer that:

1. **Look at what was true in March, and report that.** Maybe in March, Mary was two weeks late.
2. **Look at what is true today, and report that, but call it "March's answer" anyway.** If it is now December, and Mary never caught up, today she might be nine months late — and if you report "nine months" as the answer to "how late was she in March," you have given a technically-true-today answer to a question about the past, and the two numbers mean completely different things.

A loan classification system — including the one we are building for MAIIC — absolutely depends on answer (1), never answer (2). The whole point of asking "how overdue was this loan **as at 31 December 2025**" is to find out what was known and true on that one day, so that December's accounts, December's loan-loss provision, and December's regulatory return all describe December, not March 2026 or September 2026.

The question this document answers is: when MAIIC's own 2025 Loan Book Report workbook and our system disagree about how overdue a loan was in December 2025, which one is actually describing December 2025? We found the answer, and it is decisive.

### 2.4 What "ASONDATE" is, specifically, inside E-Banker

One more piece of background, because the whole problem lives inside this one field.

When Barry, or anyone at MAIIC, opens the Loan Book Report inside E-Banker, before it runs it asks for a date. On the screen it is simply a date you type in or pick from a calendar: the closing date you want the report for. In the computer's own language — the export file this project reads — that same date comes through under the field name **`ASONDATE`**, which is just "As On Date" written as one word, the way older banking systems often name their fields. Type in 31/12/2025, and `ASONDATE` carries that date through the whole report.

This is a genuinely good, ordinary feature, not a flaw. Without it, MAIIC's ICT team would have to write a brand-new query every single month, or every time an auditor asked to see a closing position from a year ago. Instead, one query answers "give me the loan book as at whichever date I ask for," which is exactly what Finance uses every month-end, what auditors use to check a prior period, and what this project's own system has relied on for every month it has ever pulled data for, December 2025 included.

**So `ASONDATE` is not the problem, and Barry's team asking for it is not the problem either.** Every single one of the 27 pulls this document is about carried `ASONDATE = 12/31/2025`, correctly, without exception — the request going into E-Banker was never wrong. The problem, which section 3 shows directly, is that E-Banker does not apply that one date evenly across every field it hands back. Some fields obey it faithfully, no matter when you actually run the report. One group of fields — the "how many days late, split by age" fields — does not, and nothing on the report, or in the export file's field names, warns you of that difference. You have to go looking for it, which is what this document does.

## 3. The problem, shown with one real loan

Rather than describe this in the abstract, here is one actual MAIIC facility — Infraworks Development Limited, account 104460000098 — and exactly what happened to its figures.

### 3.1 What our system is supposed to receive

Every so often, MAIIC's ICT team (Barry) runs a query inside E-Banker called the **Loan Book Report**. You tell it a date — "give me every loan as at 31 December 2025" — and it is supposed to hand back, for every loan, its balance, how much is overdue, and how overdue it is, **frozen at that date**, the way a photograph freezes a single moment. ICT then exports that answer to a file, and that file is handed to our system.

### 3.2 What actually happened

Barry's team did not run that December 2025 report only once. Over the course of this engagement, between January and September 2026, the **same request** — "give me every loan as at 31 December 2025" — was run **27 separate times**, on 27 different real-world days, as different data packs were prepared and handed over. Every one of those 27 answers was kept by our system; none of them was thrown away, because the system has no way of knowing that 27 answers to the same question about the same month are not 27 different, equally valid facts.

Here is what two of those 27 answers said, for the same loan, for the same question ("as at 31 December 2025"):

| What was asked | The answer on 16 January 2026 | The answer on 11 September 2026 |
|---|---:|---:|
| Loan balance | MWK 402,574,690.35 | MWK 402,574,690.35 |
| Total amount overdue | MWK 105,873,319.52 | MWK 105,873,319.52 |
| The date the oldest unpaid instalment fell due | 29 March 2025 | 29 March 2025 |
| How much of that overdue amount is 1 to 30 days late | MWK 0.00 | **MWK 11,326,727.74** |
| How much of that overdue amount is 271 to 360 days late | **MWK 105,873,319.52** | MWK 33,980,183.22 |

Look at the top three rows: the balance, the total overdue, and the date the trouble started. **All identical.** E-Banker correctly remembered those facts about December 2025 and did not let them drift.

Now look at the bottom two rows. In January, E-Banker said "every kwacha of this is in the oldest, most severe bucket — 271 to 360 days late." In September, for the exact same loan and the exact same question, E-Banker said "some of it — K11.3 million — is only 1 to 30 days late." That cannot be a true description of December 2025 twice over. One loan cannot have been both "all 271+ days overdue" and "partly only 1-30 days overdue" on the same day, 31 December 2025. Something besides December 2025 is driving that second answer.

### 3.3 What is actually driving it

Every export carries two different dates on it, and it is easy to assume they are the same thing. They are not:

- **`ASONDATE`** — the date someone typed into the report before running it. This is the date the report is *about*. As section 2.4 explains, this was correctly `12/31/2025` on every single one of the 27 answers, with no exceptions.
- **`TRANSACTION_DATE`** — the real-world date the report was actually sitting there being run. This is the date the report was *run on*. For the January answer, `TRANSACTION_DATE` was 16 January 2026. For the September answer, it was 11 September 2026. These two pulls were separated by nearly eight months of real time, even though they share the identical `ASONDATE`.

What this tells us is that E-Banker's Loan Book Report freezes some fields correctly to `ASONDATE`, the date you ask for — the balance, the total overdue, the date trouble started — but for the "how many days late, split into buckets" fields specifically, it does not freeze anything to that date at all. It works those out using **`TRANSACTION_DATE`, the real day the report happens to be run on**, applied to whatever has happened to the loan between December 2025 and that real day. Ask it in January, and it tells you how things had aged by January. Ask it again in September, and it tells you how things had aged by September — and hands you both answers stamped `ASONDATE = 12/31/2025`, because that is still the date someone typed in, even though it is not the date the overdue-days calculation is actually anchored to.

Put plainly: `ASONDATE` tells the report *which loan* and *which balance* to talk about. It does not, for these particular fields, tell the report *which point in that loan's history* to measure the ageing from — the report measures ageing from `TRANSACTION_DATE` instead, silently, every time. This is exactly the photograph-versus-today's-reality mix-up from section 2.3, except it is happening inside one single answer from the bank's own system: part of the answer is the photograph (anchored to `ASONDATE`), part of it is today (anchored to `TRANSACTION_DATE`), and nothing on the report itself marks which fields are which — you only find it by comparing two pulls of the same request side by side, the way this document just did.

### 3.4 Why our system picked the wrong one of the 27

Our system was not built to know that 27 answers to the same "as at 31 December 2025" question could disagree. When it found more than one, it had to choose, and the rule it used — buried in a piece of code called `LandingZoneReader::latestRunsOn()` — was, in effect, "when in doubt, prefer whichever answer was loaded most recently." Because the most-recently-loaded answer is, in practice, also the one with the latest real-world `TRANSACTION_DATE`, our system has been systematically picking **the most contaminated answer available** — the one furthest in time from December 2025, carrying the most extra months of whatever happened to the loan afterwards — every single time there was a choice to make.

## 4. How big this is

This is not one unlucky loan.

- Of the **123 accounts** that have a December 2025 Loan Book Report answer at all, **59 of them — just under half — have an answer that changes depending on which of the 27 pulls you read.**
- The other 64 accounts happened to be asked about on a day that gave the same answer every time, usually because nothing changed about them between January and September 2026, or because they never had any arrears to split into buckets in the first place.
- This is not special to December 2025. The same "ask E-Banker the same historical question more than once" pattern will have happened for every other month this project has pulled data for. December 2025 is simply the one month we have checked in this much detail, because it is the month we are reconciling against MAIIC's own December 2025 workbook.

We already know, from a separate exercise comparing our system's December 2025 stages against MAIIC's own workbook, that 36 of 107 matched loans disagreed on stage, and 29 of those 36 disagreements moved a loan to a **more severe** stage in our system than in the workbook. This section explains why: the "more severe" reading was, in most of those 29 cases, not a true December 2025 fact at all — it was a later date's arrears pattern, filed under December's name.

## 5. Why this matters, not just as a technical curiosity

If December 2025's loan classification is wrong because it is secretly describing a later month, several things that depend on it are wrong too, by exactly the same amount:

- **The loan-loss provision for December 2025** — a loan wrongly shown as badly overdue carries a much bigger provision than it should, understating that month's profit for no real reason, or the reverse.
- **The Reserve Bank of Malawi return for December 2025** — the classification (Pass, Special Mention, Substandard, Doubtful, Loss) and the minimum provision the Bank requires both come from the same contaminated arrears reading.
- **Any comparison against MAIIC's own December 2025 ECL workbook** — which is exactly the exercise that uncovered this. Without knowing about this problem, the natural (wrong) conclusion would have been "our staging rules disagree with MAIIC's," when the real story is "we are reading two different months and calling them both December."
- **Every other historical month this affects**, whenever more than one pull exists for it — which we have not checked yet, but have every reason to expect, since the same ICT process produced every month's data the same way.

## 6. The fix, explained simply

The fix does not touch any of the calculations — not the loan-loss formula, not the staging rules, not the regulatory bands. All of those are fine. The fix is entirely about **which of the 27 answers our system chooses to trust** when more than one exists for the same question.

**Today's rule:** when in doubt, prefer the most recently loaded answer (which, in practice, means the one furthest in the future from the date being asked about).

**The corrected rule:** when in doubt, prefer the answer whose real-world `TRANSACTION_DATE` is **earliest** — the one closest to the date actually being asked about. If E-Banker was asked about December 2025 in January 2026 and again in September 2026, trust January's answer, because January's answer has had the least opportunity to be contaminated by anything that happened after December.

This is a small, precise change to one piece of code, `LandingZoneReader::latestRunsOn()`, which both of the two places that build the monthly loan book already call. No other part of the system needs to change. Because it lives in the one place every monthly loan book build already goes through, it is not something that has to be re-applied or remembered each month — it is a standing rule that protects every month, past or future, the same way, automatically, for as long as the system runs.

### 6.1 A safety check before trusting which pull is earliest

"Earliest wins" is safe here because of something the evidence in section 3 showed plainly: across all 27 pulls for Infraworks, the fields that should never move for a closed month — the balance, the total overdue amount, the date the oldest instalment fell due — **did not move.** Only the live-recomputed ageing split drifted. That tells us all 27 pulls are the same underlying December 2025 fact, re-read at different moments; none of them is a genuine correction to December's own figures.

That will not always be true, so the fix should not simply trust the earliest pull blindly. Picture a different, real possibility: E-Banker is asked for a month's report, and a few days later asked again because someone found and corrected an actual error for that same month. In that case the **later** pull is the right one, not the earlier one — "earliest wins" on its own would get that backwards.

So the rule is two steps, not one:

1. **First, check that the fields which should never move for a closed month — the balance, the total overdue amount, the overdue-instalment date — agree across every candidate pull.** If they agree, the only thing differing between the pulls is the live-recomputed ageing split, and the earliest pull is clearly the safest choice: take it.
2. **If those fields do *not* agree between pulls, that is not ageing drift — something genuinely changed about the month.** The build does not guess which pull is right. It names the disagreement on the loan's own record and leaves it for a person to look at, the same way this whole system already refuses to guess rather than invent an answer.

**A second, independent safety check was added once the fix was built and checked against MAIIC's own December 2025 workbook (see section 8).** On several accounts, the earliest pull passed both checks above — its own balance, total overdue and overdue-instalment date all agreed with every other pull — and the system still produced the wrong answer, because that *particular* pull's arrears-ageing buckets were all nil even though that same pull's own total overdue amount said otherwise. That is not a disagreement between pulls at all; it is a pull contradicting itself. No cross-pull check can catch it, because every pull involved can still agree with every other one.

So the rule gained a third step: **before trusting the earliest pull, check that its own buckets are not self-contradictory — not every bucket nil while its own total overdue amount is a real, positive number.** A pull that fails this is passed over in favour of the earliest pull that does not; if none of them pass, the build falls back to the pre-fix choice, flagged the same way as any other disagreement, rather than trusting a pull known to be internally broken.

### 6.2 What this fix does not try to do

- It does not try to recover a true December 2025 answer where **no pull was ever close to that date** — if the earliest available pull for some account was itself months after December, the earliest-available answer is simply the best this system can do, and that should be said plainly in the loan's own record, not hidden.
- It does not change how overdue-days, staging, or provisioning are calculated — only which raw E-Banker answer feeds into that calculation.
- It does not touch any other query family (account details, rates, status) where "prefer the latest" genuinely is the right rule, because those questions are legitimately about the current state of the account, not about a frozen historical date.

### 6.3 Noticing it happening again, instead of only surviving it

As the fix stands so far, if a 28th pull for `ASONDATE = 2025-12-31` lands tomorrow, the system will absorb it correctly under the rule above — but silently. Nothing would tell anyone it happened.

The fix should also record a small, plain notice whenever a new pull arrives for an `ASONDATE` that already has an earlier one on file: what changed, and whether the stable fields still agree (ordinary, expected drift) or disagree (something a person needs to look at, per section 6.1). This turns the fix from "we survive it when it happens" into "we would notice if E-Banker's own retrospective numbers started drifting," which is exactly the kind of thing Finance, Credit, or an auditor would want surfaced rather than buried in 27 rows nobody is looking at.

### 6.4 A third, separate defect: the overdue date itself sometimes jumps between months

The December 2025 workbook check (section 8) led to tracing every one of the disagreeing loans back through their whole history, as far as each loan's own first month on the book — sometimes back to 2020. That traced a third defect, distinct from both the ones above: on a small number of loans, the reported overdue-instalment date does not just drift with repeated same-month pulls (section 6.1) or go internally nil (section 6.1's third check) — it changes to an implausible value **from one month-end to the next**, with every pull of the new month agreeing on the wrong value, so neither of the first two checks has anything to catch.

Two shapes of this were found on real December 2025 data:

- **A date that could not possibly be right**, because it falls before the loan's own value date — the loan cannot have had an unpaid instalment before it existed.
- **A date that moves by far more than any genuine instalment schedule would move it in one month.** Checked against the ordinary bi-monthly and quarterly schedules in MAIIC's book, the most any legitimate month-to-month move was ever seen to be was 141 days. Anything larger is not a loan's instalments falling due on their normal rhythm; it is the reported date itself breaking.

The fix adds a second comparison, run once a month, after the ordinary ageing calculation: the newly reported overdue-instalment date is checked against the loan's own value date, and against what last month's own build already booked for the same loan. A date before the value date, or a move of more than 150 days since last month, is kept exactly as E-Banker reported it — never guessed at or corrected to last month's figure — but named on the loan's own record for a person to look at, the same way every other disagreement in this fix is handled. Of the 36 facilities the December 2025 workbook check disagreed with, 6 carried this defect; it is documented in full, loan by loan, in the companion investigation note (see section 8).

## 7. A habit that needs to change at MAIIC, not just a line of code

The fix in section 6 can only choose among the pulls that actually exist. It cannot invent a pull that was never taken close to the true month-end. So whether *future* months stay correct depends on more than this one code change — it depends on an operational habit at MAIIC.

The reason 27 copies of the same December 2025 request piled up in the first place is that every time a later, unrelated data pack was prepared — for a completely different purpose, months after December — the extraction process asked E-Banker for `ASONDATE = 2025-12-31` all over again, alongside whatever new month it actually needed. Nothing was wrong with doing that once. Doing it 27 times, every time, for a month that had already closed, is what created the pile of disagreeing answers for this fix to have to sort through.

Going forward, two things would stop this recurring:

- **Ask E-Banker for each month's Loan Book Report promptly after that month closes**, as close to the month-end as MAIIC's own process allows, so the pull that exists is already a good one.
- **Stop routinely re-asking for a month that has already closed and already been pulled**, just because a later data pack happens to be built on a process that re-requests every historical date for convenience or completeness. If a month genuinely needs to be re-pulled — because something about it is being corrected — that is fine, and section 6.1's safety check is there for exactly that case. What should not continue is re-pulling it as a side effect of unrelated work.

This is not a decision for Dupleix to make alone: it is a change to how MAIIC's ICT team runs its own extracts, which is why it is listed as its own item for ICT to own in section 10.

## 8. What happens after the fix is built, before anyone trusts the new numbers

Because this changes **which arrears figure is read**, not how it is used, re-running the system with this fix will move some already-published-looking figures: expected credit loss, staging counts, the Mega Farm provision, and the Reserve Bank return — for December 2025, and very likely for other months too, wherever more than one pull exists.

That is exactly the kind of change this project's own rule says must never happen silently. So, before this is treated as "done":

1. The system is rebuilt once with the fix in place.
2. Every figure that changed is listed, old value next to new value, so the size of the effect is visible, not just asserted.
3. The December 2025 comparison against MAIIC's own workbook is run again, to check that the disagreements genuinely shrink, rather than simply moving somewhere else.
4. Edward reviews the full list of changes and approves it in writing before the corrected figures are allowed to become the system's new baseline.

**Steps 1 to 3 were carried out on 16 and 17 October 2026, in a throwaway copy of the database — nothing was written to the figures currently relied on.** The full detail, loan by loan, is in a companion document, `MAIIC_December_2025_Loan_Book_Investigation_Findings_v1_2026-10-17.md`; the headline results:

- Rebuilding every month from July 2024 to September 2026 under the fix changes 393 loan-month rows out of roughly 100,000 — always the arrears-ageing figures (`staging_dpd` and the arrears buckets), never the RBM directive's own overdue-day count and never the carrying amount. The Reserve Bank return is untouched by this fix.
- 73 of those loan-months hit a disagreement the fix will not resolve silently, and are flagged on the row for a person to review, per sections 6.1 and 6.4.
- The December 2025 workbook check still disagrees with MAIIC's own workbook on 36 facilities after the fix — the same 36 as before it, with no facility moving either direction. Tracing all 36 back through their whole history found a real, fixable cause (the two defects in sections 6.1 and 6.4) for 10 of them. The other 26 show clean, self-consistent, often months- or years-old arrears in every figure E-Banker gives this system, with nothing in any extract explaining why MAIIC's own workbook calls them current. That gap is not this fix's to close; it needs an answer from whoever prepared the workbook, not more code.

Decision 2 below (Edward's written approval before this becomes the new baseline) has not yet happened.

## 9. The one question only MAIIC's ICT team, or the vendor, can answer

There is a real possibility that E-Banker's Loan Book Report **cannot** be made to freeze its ageing-bucket fields to a historical date at all — that asking it "as at December 2025" for the arrears split will always, by the way the report itself was built, actually mean "as of today, for an account that existed in December 2025." If that is true, then "prefer the earliest available pull, once the stable fields agree" (section 6) is not just the best fix our system can make — it is the *only* fix possible from our side, and it should be written down plainly as a permanent, known limitation, not something we keep expecting to fully close. It also makes the operational habit in section 7 more important, not less: if the report itself cannot be trusted to answer honestly about the past, the only real defence is to ask it about each month while that month is still current.

The question to put to ICT, in plain words: **"When we ask the Loan Book Report for a date in the past, does the arrears-ageing split (the DAY_1_30 to DAY_271_360 columns) genuinely reflect that past date, or does it reflect whatever is true on the day the report is actually run?"** Everything in section 3 above is the evidence that the honest answer is the second one, at least for the loans we have checked — but MAIIC's ICT team, or Virtual Galaxy directly, may know something about how that report was built that would explain it, or that would tell us it can be corrected at the source.

## 10. What needs deciding, by whom, before this is built

| # | Decision | Who |
|---|---|---|
| 1 | Approve the corrected rule in section 6 ("prefer the earliest pull, once the stable fields and the buckets' own arithmetic agree, not the latest") as the fix to build, including the drift notice in section 6.3 and the cross-month check in section 6.4 | Edward |
| 2 | Review the full before-and-after list of figures the fix changes, across every affected month, before it becomes the new baseline | Edward |
| 3 | Confirm with ICT, or with Virtual Galaxy, whether the Loan Book Report's ageing-bucket fields can be made to genuinely freeze to a historical date, or whether this is a permanent characteristic of the report | MAIIC ICT |
| 4 | Decide whether MAIIC wants this limitation disclosed anywhere in the financial statements or the regulatory return, given it can affect reported loan classifications for any historical month | Dr Thom |
| 5 | Adopt the operational habit in section 7 — pull each month's Loan Book Report promptly after close, and stop routinely re-pulling closed historical months as a side effect of unrelated extracts | MAIIC ICT |

Nothing above blocks starting the build in sections 6 and 8 — the fix itself does not depend on ICT's answer to section 9 — but decision 2 must happen before the corrected figures replace anything currently being relied on, and decision 5 is what keeps future months from needing this fix to work as hard as December 2025 did.
