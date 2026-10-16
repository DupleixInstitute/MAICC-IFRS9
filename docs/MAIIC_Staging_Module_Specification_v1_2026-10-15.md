---
kicker: MAIIC IFRS 9 and EIR system
title: MAIIC Staging Module Specification v1
subtitle: Governed staging and the staging comparison run
date: 15 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT; companion to the EIR Engine Specification v4 (section 3.6) and the FLI Module Specification v1
status: Draft for review
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this specification does

Staging decides whether each loan is in Stage 1, 2 or 3 under IFRS 9 at every month-end. It drives more of the system than any other single rule. The probability of default is measured on stage movements, the expected credit loss is twelve-month or lifetime by stage, interest on a Stage 3 loan is recognised on the net basis, and the Mega Farm provision, the restructure register, the forward-looking route and the IFRS 9 note all read the stage.

Today the staging rules sit in the code. This specification makes them **governed**: each rule becomes a setting in the Governance Centre, proposed by one person and approved by another, with an effective date and a comment, like every other policy setting in the system. It makes every change in the Governance Centre, and every other approval in the system, fully logged, with room for supporting documents (section 8). It also adds a **staging comparison run**, which stages any month under a second set of rules beside the booked one and explains every difference, without changing the booked figures.

Two sets of rules are defined:

- **Single-ladder staging**: MAIIC's staging policy as at 31 December 2025, as applied in the ECL workbooks of 14 December 2025. It is the **seeded default for every period**.
- **Directive-aligned staging**: the rules the system applies today, built on the Reserve Bank of Malawi's directive for development finance institutions and MAIIC's published accounting policy. It is **defined but not in force**. It takes effect only when MAIIC adopts it, through a governed proposal and approval with an effective date chosen by MAIIC.

## 2. Why

### 2.1 Three bases that do not agree

Three staging bases are in play, and they differ:

| Point | RBM DFI Directive 2018 | ECL workbooks of December 2025 | System today |
|---|---|---|---|
| When a loan becomes non-performing (Stage 3) | Short-term 91 days; medium and long-term 181 days | 181 days for every facility | By term, as the directive: 91 and 181 days; Mega Farm 91 |
| Stage 2 | Special mention from 31 days (short-term) or 91 days (medium and long-term) | 31 days for every facility | 31 days for every facility |
| How days past due are counted | Days the instalments are overdue | The highest arrears bucket in the loan book report | From the oldest overdue instalment, with a count from maturity and corrections against the instalment schedule in force |
| Missed-instalment default (note 22.8.1) | Not addressed | Not applied | 4 consecutive instalments |
| Qualitative triggers | Not addressed | None | Approved triggers on the SICR register |
| Leaving a stage | Back to accrual only when all arrears are paid (s.13(3)) | Re-staged afresh at each year-end | Held for 3 month-ends below the threshold |
| Frequency | Quarterly review (s.11) | Annual | Every month-end |

At 31 December 2025, the workbooks put the main book at 80, 10 and 18 facilities in Stages 1, 2 and 3. The system on 14 October 2026 put it at 50, 22 and 51. Neither figure can be explained without separating the rules from the data, which is what the comparison run does.

### 2.2 Why the 31 December 2025 policy is the default

MAIIC has not adopted a new staging policy. The system should therefore stage the way MAIIC staged until MAIIC decides otherwise. The improved rules are fully built and available, and the comparison run shows their effect every month, so the decision can be taken on evidence rather than on argument.

### 2.3 What this choice costs until the new policy is adopted

With the 31 December 2025 policy in force, the system will knowingly depart from the directive and from MAIIC's published policy in the ways below. Each is recorded in the Governance Centre against the setting that causes it, and each is measured by the comparison run every month:

1. **Short-term facilities 91 to 180 days overdue stay in Stage 2.** The directive classes them substandard and IFRS 9 presumes default at 90 days; a short-term facility has no rebuttal.
2. **Matured loans with an unpaid balance are counted from their maturity** (decided by Edward Mazibuko, 15 October 2026). Bucket ageing takes E-Banker's report as it stands, and that report leaves the arrears empty on many matured loans: on the system's data, the Mega Farm bullet loans that matured in 2025 read 0 days on about 47 billion kwacha. The December workbooks had them past due because their own loan book had the arrears filled in. The 31 December 2025 policy therefore counts a matured loan still owing from its maturity date when that is longer (setting 5), so the system reproduces the December result instead of reading those loans as current. On the bootstrap of 15 October 2026, leaving this count out would have taken the Mega Farm provision at September 2026 from about K44.7 billion to K0.09 billion. This is not a departure from the directive, which classifies by how long the amount has been overdue.
3. **The missed-instalment default is not applied**, although MAIIC's 2025 financial statements (note 22.8.1) describe it as the policy.
4. **Two proven E-Banker report defects are left uncorrected**: arrears aged from deleted instalment definitions, and collapsed schedules that show a paid-up loan as wholly overdue.
5. **A loan leaves its stage on its first good month-end**, with no probation.

These are accepted departures, not errors, for as long as MAIIC keeps the 31 December 2025 policy. They are the reasons to adopt the directive-aligned rules, and the comparison run measures what each one is worth.

## 3. The settings

Each setting below is a Governance Centre setting with options, a default, a description and a comment. The column "31 December 2025 policy" shows the option that reproduces the ECL workbooks of December 2025; its comment in the Governance Centre reads *"MAIIC's staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025)."*

| # | Setting | Options | 31 December 2025 policy (seeded default) | Directive-aligned staging | Comment |
|---|---|---|---|---|---|
| 1 | `staging_threshold_profile`: where Stage 3 starts | One threshold for every facility (181 days) · By facility term (short-term 91, medium and long-term 181) · IFRS 9 presumption for every facility (91) | One threshold for every facility | By facility term | The directive classes facilities by repayment period, and 181 days for medium and long-term loans rests on its rebuttal of IFRS 9's 90-day presumption. The IFRS 9 option is kept as a conservative benchmark |
| 2 | `stage2_threshold_medium_long`: where Stage 2 starts for medium and long-term facilities | 31 days · 91 days, with documented rebuttal evidence | 31 days | 31 days | Both policies keep IFRS 9's 30-day presumption. The 91-day option needs Dr Thom's decision O13 and documented evidence that late payment does not signal higher risk for this book |
| 3 | `staging_mega_farm_term`: how Mega Farm loans are classed | Same threshold as every facility · By tenor · Always short-term (91) | Same threshold as every facility | By tenor | The directive classes by repayment period, and some Mega Farm loans run five years. "Always short-term" rests on the seasonal-finance argument, which Finance has not confirmed (spec v4, section 16) |
| 4 | `dpd_basis`: how staging counts days past due | Bucket ageing of the overdue amounts · Oldest overdue instalment | Bucket ageing | Oldest overdue instalment | Exists today. Buckets cannot tell 200 days from 900. **This setting governs staging only; the RBM return always uses the directive's count (section 5)** |
| 5 | `dpd_after_maturity`: a matured loan still owing | Core-system ageing only · Count from maturity when longer | Count from maturity | Count from maturity | The December workbooks' loan book showed matured loans as past due; E-Banker's report leaves their arrears empty, so without the count they read current (section 2.3, item 2; decided 15 October 2026) |
| 6 | `dpd_schedule_correction`: when the loan book report contradicts the schedule in force | Core-system report as given · Recount against the schedule in force and the ledger | Report as given | Recount | Corrects two proven E-Banker defects; every corrected row is flagged with the reason |
| 7 | `stage3_missed_instalments`: default on missed payments | Not applied · 3 · 4 · 6 consecutive | Not applied | 4 consecutive | Exists today. MAIIC's published policy (2025 statements, note 22.8.1) is 4 |
| 8 | `sicr_qualitative_register`: approved qualitative triggers | Not applied · Applied | Not applied | Applied | IFRS 9 B5.5.17 expects qualitative evidence beyond days past due. Only triggers approved on the register count |
| 9 | `sicr_pd_change`: the change in the probability of default since origination | Not applied · PD at least doubled since origination | Not applied | Not applied | The workbook template carries a PD-doubling test, but it never fires (the prior PD equals the current PD). Needs each loan's PD at origination, which the system does not yet keep. A later build |
| 10 | `stage_cure_months`: month-ends held before moving down | 0 · 3 · 6 · 12 | 0 | 3 | Exists today, seeded at 3; the seeded value changes to 0 under this specification |
| 11 | `stage_cure_test`: what counts as cured | Months below the threshold · All arrears cleared · Arrears cleared, then the months | Months below the threshold (no effect at 0 months) | Arrears cleared, then the months | The strictest option meets the directive's test for returning to accrual (s.13(3)) and IFRS 9's need for sustained performance |
| 12 | `staging_preset`: one choice that sets settings 1 to 11 | Single-ladder staging · Directive-aligned staging · Custom | Single-ladder staging | Directive-aligned staging | A change of preset is one governed change; any later change to a single setting turns the preset to Custom and is recorded with its reason |

### 3.1 How the defaults are seeded

- Every setting is seeded with the **31 December 2025 policy**, effective from inception (`1900-01-01`, as the other governed defaults), labelled as the seeded default and not as a MAIIC approval.
- **Directive-aligned staging is seeded as a defined preset only.** Nothing switches it on by date. It takes effect only through a proposal and a second person's approval in the Governance Centre, with the effective date MAIIC chooses. The bootstrap never approves it.
- The threshold rows on the Staging & SICR Rules screen stay as they are and are read through setting 1: the profile chooses which rows apply.

### 3.2 Adopting directive-aligned staging

When MAIIC decides to adopt it:

1. A person proposes the **Directive-aligned staging** preset with an effective date (normally the first day of a month not yet locked) and the reason, citing the board or committee decision.
2. A second person approves it.
3. From that month the booked staging follows the new rules. Locked months are never restated. Under IAS 8 the change is a change in accounting estimate, applied prospectively.
4. The comparison run for the first month under the new policy is kept as the record of its effect.

## 4. The staging comparison run

The comparison run answers one question: *what would this month's stages and ECL be under another set of rules, and why?*

**What it does.** For a chosen month and a chosen preset (or a custom set of settings), it stages every loan under those rules into a separate table, beside the booked stage. It never writes to the loan book, the ECL, the revenue or any report, and it is allowed in a locked month.

**What it reports**, per loan and in total:

| Column | Meaning |
|---|---|
| Booked stage, comparison stage | The two stages |
| Days past due, both ways | Under each rule set's counting basis |
| Reason for the difference | The setting that causes it: threshold profile, Mega Farm class, counting basis, maturity count, schedule correction, missed instalments, qualitative trigger or cure |
| Carrying amount, EAD | As booked |
| ECL effect | The twelve-month or lifetime ECL at the comparison stage, using the booked PD, LGD and EAD for that stage |
| RBM class | Unchanged by staging (section 5), shown for reference |

A summary gives the count and carrying amount moving between each pair of stages, the ECL effect in total and by reason, and the effect on Stage 3 interest. It is on the Staging & SICR Rules screen, it can be exported, and it is shown on each month's audit workbook.

**How the figures are worked out** (decided by Edward Mazibuko, 15 October 2026):

- **The ECL effect is an estimate.** The booked ECL exists only at the booked stage, so a loan that moves is given the booked coverage of its new stage: the ECL over EAD of the loans booked in that stage in the same portfolio (or in the whole book, where the portfolio has none), applied to the loan's own EAD. A loan that does not move keeps its booked ECL. This shows the direction and size of the change; it is not a full ECL run, and nothing booked is affected by it.
- **The cure reads the earlier months as booked.** The comparison answers what would change if the policy were adopted from the month compared, and months before adoption stay as booked.
- **The day counts are exact.** The build keeps each loan's component counts (oldest overdue instalment and bucket ageing, each as reported and recounted, and the days since maturity), so any mix of settings is counted exactly, and the reason for a difference is never given to the wrong setting.

**Uses.** It shows each month what adopting directive-aligned staging would change. It reproduces the December 2025 method on the system's data for comparison with the workbooks. And it lets a single setting be tested before it is proposed.

## 5. Staging and the RBM return stay separate

The RBM return classifies facilities under the directive's own bands: by term, on the count of days from the oldest overdue instalment. It must not change when the staging policy changes. So:

- The loan book build always computes the directive's day count, with the maturity count and the schedule corrections, and keeps it in `overdue_days`. The RBM return and the RBM classification report read only this.
- The days that staging uses are computed under the staging settings (4, 5 and 6) into a separate column, `staging_dpd`, with its basis recorded on the row.
- The comparison of the stage with the RBM class, already in the IFRS 9 vs RBM report, then shows any loan whose stage and class disagree, with the setting that causes it.

## 6. What follows the staging policy, and what does not

| Area | Follows the staging policy? | How |
|---|---|---|
| PD by segment and transition matrices | Yes | Measured on the stage movements |
| Time-phased ECL | Yes | Twelve-month or lifetime by stage |
| Mega Farm ECL | Yes | Its stage comes from setting 3 |
| EIR revenue | Yes | Stage 3 interest on the net basis |
| Forward-looking route and scenarios | Yes | Applied to the staged PD |
| Restructure register | Yes | Records the stage at the event |
| IFRS 9 note and reports | Yes | By booked stage |
| RBM return and RBM classification | **No** | Directive day count, always (section 5) |
| Golden numbers of the bootstrap | Yes, in part | The verify's 98 checks include the ECL by stage, the discounted allowance, the Mega Farm provision and the EIR revenue, which follow the stage; the ledger, take-on, GL and RBM ties do not. A change of staging policy moves the former, so the golden numbers are set again from the bootstrap of the merged phase once the policy is accepted |

## 7. Data and code

**Settings.** Settings 1, 3, 5, 6, 8, 9, 11 and 12 are added to the Governance Centre with their options, descriptions and comments; settings 4, 7 and 10 exist and gain the comment and the new seeded value where it changes (10, from 3 to 0; 4 and 7 to the 31 December 2025 options).

**Staging service.** `StagingService` reads every rule from the settings as at the month-end, with no rule fixed in code and no silent default (decision D21). The loan book build writes `overdue_days` (directive count) and `staging_dpd` with its basis. The maturity count and the schedule correction become switchable in the staging count only; the directive count keeps both always.

**Comparison run.** A table `staging_comparison_runs` (period, preset or settings snapshot, who ran it, when) and `staging_comparison_rows` (per loan: both stages, both day counts, the reasons, the ECL effect). A command `staging:compare {period} {preset}` and a panel on the Staging & SICR Rules screen.

**Audit trail.** Every staging run records the settings in force, their effective dates and the preset, so any month can be traced to the policy it ran under.

## 8. Every governed change logged, with its supporting documents

Staging becomes governed through the Governance Centre, so the Centre's record has to be good enough for an auditor or the Reserve Bank to follow every policy choice: who proposed it, why, on what evidence, who approved or rejected it, when it took effect and what it replaced. Sections 8.1 to 8.5 cover **every setting in the Governance Centre**, not only the staging ones. Section 8.6 applies the same record to **every other approval in the system**: the rules, registers, models, locks and sign-offs that are proposed by one person and decided by another outside the Centre.

### 8.1 What is recorded today, and what is missing

| Today | Gap |
|---|---|
| A proposal writes an audit entry ("EIR Governance Setting Proposed") with the old and new values, the effective date and the reason | Only proposals and approvals are recorded. There is no reject or withdraw step: a proposal that is not wanted can only be left standing, with no record of why |
| An approval writes an audit entry ("EIR Governance Setting Approved"), and the setting it replaces is copied to the history table | The reason is cut to 500 characters on the setting |
| The proposer cannot approve their own change; an administrator override is itself a governed setting | The seeded defaults are written by the installation with no audit entry |
| | No document can be attached to a proposal or an approval |
| | The record is spread across the general Audit Trail screen; there is no single history per setting |
| | The audit log is an ordinary database table: a database administrator could change or delete an entry without trace |

### 8.2 What every change will record

Each of the following events writes one audit entry, linked to the setting and to the change it belongs to:

| Event | Recorded |
|---|---|
| **Seeded** | The setting, the seeded value, its effective date, and that it was seeded by installation (not a MAIIC decision) |
| **Proposed** | The proposer, the setting, the value in force and the proposed value, the effective date, the reason in full, and the documents attached |
| **Document added or removed** | Who, when, the document's name, type, size and fingerprint; a removed document is kept and marked removed, never deleted |
| **Approved** | The approver, the time, any comment, the documents attached at approval, and the setting the change replaces |
| **Rejected** (new) | The reviewer, the time and the reason (required). The proposal stays on record as rejected |
| **Withdrawn** (new) | The proposer, the time and the reason. The proposal stays on record as withdrawn |
| **Preset applied** | For a preset (section 3), one entry for the preset and one for each setting it changed, all linked to the same change |
| **Override used** | If an administrator approves their own proposal under the governed override, that fact, with the override setting in force |

### 8.3 Supporting documents

- **Where.** A proposal, an approval, a rejection and a withdrawal can each carry documents: for example a board or committee minute, a policy paper, an analysis, the comparison-run export (section 4), or correspondence with the auditors or the Reserve Bank.
- **Required evidence.** Each setting can be marked as needing a document to be approved. The staging presets, `staging_threshold_profile` and `stage2_threshold_medium_long` (a rebuttal under IFRS 9) are marked: adopting directive-aligned staging cannot be approved without the minute or decision that adopts it.
- **Kinds of file.** PDF, Word, Excel, CSV, PNG and JPEG, up to 20 MB each, with a short description and a document type (minute, policy, analysis, correspondence, other).
- **Stored safely.** Files go to the system's **private** storage on MAIIC's server, never to the public web folder, and can be opened only by users with the governance permission. Each file's SHA-256 fingerprint is recorded when it is uploaded and checked when it is opened, so a changed file shows. The existing `supporting_documents` table is reused for this, with its storage set to private.
- **Kept.** Documents are never deleted from a decided change. They are included in the backups (spec v4 and the integration document, storage and backup) and kept for the life of the system.

### 8.4 One history per setting

The Governance Centre gains a **History** view for each setting: every value it has held, with its effective dates, who proposed and approved or rejected each change, the reasons, and the documents, newest first. The view can be exported to PDF and Excel, and the same history is a sheet in each month's audit workbook.

### 8.5 A log that cannot be changed quietly

- The governance audit entries are **append-only**. The system has no screen or command to edit or delete them, and the build adds a database guard so that an update or delete on these entries is refused.
- Each entry carries the **fingerprint of the entry before it**, so the entries form a chain. A check (run with the bootstrap's verify step and on demand) recomputes the chain and names the first entry that does not follow. Anyone who changes or removes an entry directly in the database breaks the chain, and the check shows where.

### 8.6 The other approvals in the system

Outside the Governance Centre, about twenty other workflows decide something that changes a figure. They already have their own screens, and most already require a second person. Each now gets the same record as the Centre: the events of section 8.2, supporting documents as in section 8.3, a history, and the append-only chained log of section 8.5. The list below is from the code as at 15 October 2026.

**Policy, rules and models**

| Workflow | What it decides | Today | What changes | Evidence required to approve |
|---|---|---|---|---|
| Staging threshold rows (Staging & SICR Rules) | The day thresholds per facility class | Propose, approve, reject; second person; logged | Withdraw; documents; history | Yes: the rebuttal basis for any change to a threshold |
| Qualitative SICR register | A trigger that lifts a loan or customer to Stage 2 or 3 | Propose, approve, reject; second person; logged | Withdraw; documents; history | Yes: the credit report or event behind the trigger |
| **Fee rulebook** (Accounting Rules) | Whether a fee type is integral to the EIR | Create, edit, approve, switch on or off; logged. **The person who wrote a rule can approve it, no reason is asked, and switching a rule on or off needs no second person** | **Second-person approval with a reason; reject and withdraw; switching on or off becomes a proposal like any other change**; documents; history | Yes: the policy reference or the offer-letter evidence for the fee type |
| Fee classification review | Whether each fee line enters the EIR | Classify, then review by a second person; logged | Reject back to the classifier with a reason; documents; history | Only for a line classified against the rulebook's suggestion |
| Scenario sets | The economic scenarios and their weights | Propose, approve, lock; second person; logged | Reject and withdraw; documents; history | Yes: the calibration note (already required for a downside scenario) |
| Forward-looking overlays | A management adjustment to the forward-looking result | Propose, approve, reject; second person; logged | Withdraw; documents; history | Yes: the reason and evidence (already captured as text; now a document as well) |
| Forward-looking fits for the route | Which regression drives the adjustment | Propose, approve; second person; logged | Reject and withdraw; documents; history | Yes: the diagnostics export |
| External forward-looking models | A model built outside the system | Propose, approve, reject; second person; logged | Withdraw; documents; history | Yes: the model documentation |

**Loan-level decisions**

| Workflow | What it decides | Today | What changes | Evidence required to approve |
|---|---|---|---|---|
| Schedule approval | A loan's contractual schedule for the EIR | Approve; the preparer cannot approve; logged | Reject with a reason; documents; history | No |
| EIR lock and reopen | Fixing a loan's EIR; reopening it | Lock with the second-person rule or the governed override; reopen by request, approved or rejected; logged | The lock written to the audit log by the service itself, not only by the screen; documents on a reopen; history | Yes, for a reopen: the reason it was wrong |
| Restructure register | A confirmed restructure and its modification accounting | Propose, approve; second person; logged | Reject and withdraw; documents; history | Yes: **the Board approval the directive requires for a restructure (s.16(1))** |
| Rate resets | A floating-rate reset event | Propose, approve; second person; logged | Reject and withdraw; documents; history | Yes: the prime-rate notice or the agreement |
| Write-off register | Writing a loan off | Propose, approve, reject; second person; logged | Withdraw; documents; history | Yes: the write-off approval under MAIIC's credit policy |
| Take-on build | Building the take-on loans from Finance's workbook | Propose, approve; second person; logged | Reject and withdraw; documents (the returned workbook is attached); history | Yes: the workbook version used |

**Month-end controls and sign-offs**

| Workflow | What it decides | Today | What changes | Evidence required to approve |
|---|---|---|---|---|
| Loan book build | Accepting a month's built loan book | Approve, reject; logged | Documents; history | No |
| Period lock and unlock | Closing a month; reopening a closed one | Lock; unlock by request, approved or rejected; second person; logged | Documents on an unlock; history | Yes, for an unlock: the reason and the approval to restate |
| Compliance audit sign-offs | A reviewer's sign-off of an audit row | Sign and approve; logged | Reject with a reason; documents; history | No |
| Forward-looking month-end sign-off | Signing off a month's forward-looking record (route, scenarios, discounted ECL) | Sign off; the person who recorded the month cannot sign it; logged | Reject with a reason; documents; history | Only a commentary where the movement is outside tolerance (already required) |
| Macro data overrides | A manual value replacing an imported one | Entered with a reason; logged | Second-person approval; documents; history | Yes: the source of the value |

### 8.7 One register of approvals

The Governance Centre gains an **Approvals** view across all of the above and the Centre's own settings: everything awaiting a decision, with who proposed it and when; and everything decided, with the decision, the reasons and the documents. It filters by workflow, person, status and date, exports to PDF and Excel, and is included in the auditor's pack. One shared document store serves every workflow, so a minute attached once can be linked to the several decisions it covers.

As built (section 9, 16 October 2026):

- **What is on it.** Every item of the eighteen workflows of section 8.6 that waits for a decision or has been decided, and every Governance Centre change people made. A default seeded with the installation, and a staging threshold row seeded with it, have no proposer and no decider and are not decisions, so they are not listed. An item detected but not yet put forward (a restructure candidate, a detected rate move, a schedule not yet generated) is not listed either. An approval under the System Bootstrap label is listed and marked as such.
- **Reconciled to the audit log both ways.** Every decided item must have at least one entry in the audit log, and every decision entry in the audit log must name an item that exists. Either kind of finding is named on the view, at the end of the exports and in the auditor's pack. On a fresh bootstrap the register reconciles with no finding.
- **Linking.** A document already in the store is linked to another decision while that decision is pending, with a reason saying which part of it supports the decision. The file is not copied. A link counts as evidence wherever evidence is required, is shown on the decision's page as linked, and is recorded in the audit log. A removed document supports no decision, and a decided item keeps the documents it was decided on.
- **Approved before supporting documents were required.** The rule requiring documents comes into force on a database when the release that contains it is migrated, and that moment is recorded. A change to a setting that now requires a document, approved without one before that moment, is labelled *approved before supporting documents were required* on the setting's history, on this view and in its exports, and can be listed on its own with the status filter. It is never shown as an approval that skipped its evidence.

### 8.8 The record of what each month ran on

An auditor looking at a month a year later needs to know exactly what that month's figures were computed with: which settings, rules, models, data and code. Today that can only be partly reconstructed:

- The settings can be looked up by their effective dates, but **a setting can be back-dated into a locked month**. The only date rule is that a change must be later than the setting's last approved change, so a setting unchanged since installation could today be given an effective date of 1 March 2025. March 2025 stays locked and its figures do not move, but a lookup for March would now return the new value, not the one March ran on.
- A month can be built and run several times before it is locked, and **nothing marks which run produced the reported figures**.
- The figures also depend on inputs held outside the Governance Centre: the staging threshold rows, the approved fee rules, the scenario set, the overlays, the forward-looking model, the query versions, the data loads and the code. The loan book build records its data loads and the forward-looking module already snapshots its own inputs each month (`fli_run_snapshots`), but nothing brings them together.

The following closes those gaps.

| # | Change | What it gives the auditor |
|---|---|---|
| 1 | **A run record for every month-end run.** Who ran it, when, the code version (the repository commit) and a **frozen snapshot of every input**: each Governance Centre setting with its value, effective date, proposer and approver; the staging threshold rows; the fee rules in use with their approvals; the scenario set and version; the overlays; the forward-looking model version and the forward-looking snapshot; the query register versions; the data loads with their watermarks; and the loan book build. The snapshot is fingerprinted. | Exactly what the month ran on, in one place, as it was at the time |
| 2 | **Every result row carries its run number.** Loan book, stage, PD, LGD, ECL, revenue and Mega Farm rows record the run that wrote them. | A trace from any loan's figure to its run and that run's snapshot |
| 3 | **The lock names the run of record.** Locking a month records which run produced the reported figures. Earlier runs of the month are kept and marked superseded. | No doubt about which run the reported figures came from |
| 4 | **No back-dating into a locked month.** A change to any governed setting or rule cannot take effect inside a locked month unless it goes through the reopen-and-restate approval (section 8.6, period lock and unlock) with its documents. | The settings looked up for a locked month can never change |
| 5 | **"Settings in force" and "What changed".** For any month, a report of everything in its run record; and a comparison of two months showing every setting, rule, model, data load and code change between them. Both export to PDF and Excel and are sheets in the month's audit workbook and the auditor's pack. | The auditor reads the record instead of reconstructing it |
| 6 | **Re-performance.** A locked month can be re-run from its frozen snapshot into a separate workspace, with the comparison-run machinery of section 4, and the result checked against the stored figures, loan by loan. | Proof that the reported figures can be reproduced, not only stated |

**Where this applies.** Every engine of the month-end (build, staging, PD and LGD, ECL, forward-looking route, scenarios, Mega Farm, revenue, reports) writes into the one run record of the month-end job. A run outside the month-end job (an engine run by hand on a screen) gets a run record of its own, marked as such, and can never be the run of record.

**The bootstrap.** Each bootstrap run also writes run records, labelled as bootstrap runs, so the regression check can compare a rebuilt month with the record of the previous rebuild.

**As built (section 10a, 16 October 2026).**

- **One run per month of the month-end job.** `maiic:task month-end` opens the month's run before its first step and finishes it after its last. Every engine records its step in the open run of its month: staging, PD, LGD, the forward-looking route, projection and month record, the ECL (one step for all portfolios), Mega Farm and EIR revenue. An engine run with no run open for its month (from a screen, or a command run by hand) opens a run of its own, of kind *screen*, which is never the run of record. The bootstrap opens one run per month, labelled as a bootstrap run, around its engine chain.
- **At the end of a run.** The run number is written on the month's result rows: every loan book row of the month, and the ECL, PD and LGD runs and results, forward-looking records and projections, scenario results, Mega Farm runs and EIR revenue rows that the run wrote. The snapshot of item 1 is frozen with its SHA-256 fingerprint, with the month's headline results beside it (loans, loans by stage, ECL, discounted ECL, EIR interest). The code version is the repository commit, read from the server's `APP_COMMIT` setting where it is set, otherwise from the repository the code runs from.
- **Superseded, never deleted.** A month-end or bootstrap run that completes marks the month's earlier completed runs as superseded by it, unless one of them is the run of record.
- **The lock.** `eir:lock-period` names the latest completed month-end or bootstrap run of the month as its run of record, on the lock and on the run. It is refused if there is none, or if an engine run from a screen has changed the month since that run: the month-end job is then run again. A month computed before run records existed is locked with `--without-run-record` and a reason saying so; its lock names no run. On unlock the run stays on record as the run of record until that moment.
- **The reports.** Governance, Audit, *Run records* lists every run with its kind, status, code version, fingerprint and steps. A run's page shows its snapshot section by section, checks its fingerprint (*Verifies* or *Does not verify*), and shows its steps, results and how many rows carry its number. *Settings in force* for a month and *What changed* between two months, both in Excel and PDF, read the month's run of record while it is locked, otherwise its latest completed month-end or bootstrap run. Both are sheets of the month's auditor's pack, *What changed* against the month before.

### 8.9 UAT mode: maker-checker relaxed for administrators, safely

During user acceptance testing one administrator needs to move through the workflows without waiting for a second person each time, and at handover the two-person rule must be fully back on. The system already has the switch, but only part of the system honours it and nothing makes it safe to use.

**What exists today** (read from the code on 15 October 2026). The governed setting `maker_checker_admin_override` (*Not allowed*, the default, or *Allowed for administrators*) lets a user with the admin role approve their own proposal. Each workflow carries its own self-approval check, worded and coded separately:

| Honours the override today (6) | Refuses a self-approval whatever the setting (14) | No maker-checker yet (2) |
|---|---|---|
| Governance Centre settings; EIR lock; fee classification review; SICR triggers; rate resets; restructure register | Staging thresholds; EIR reopen; period unlock; write-offs; take-on build; loan book build (approve and reject); schedule approval; compliance audit sign-off; scenario sets; forward-looking overlays; forward-looking fits; external forward-looking models; forward-looking month-end sign-off; the legacy regression screen | Fee rulebook (phase 3); macro data overrides (phase 4) |

The bootstrap approves its own steps under the label "System Bootstrap (automated data-readiness, not a MAIIC approval)", so checking the month-end results needs no human approvals; the override matters only for testing the screens and workflows themselves. The bootstrap label stays as it is and is not the override.

**What is missing.** Switching the override on is itself a governed change and needs a second person the first time. Nothing stops it being left on in production. A self-approval is not marked as one. Nothing on screen shows that the two-person rule is relaxed. And the installation guide sets `APP_ENV=production` on MAIIC's server, which is where UAT is run.

**What UAT mode adds:**

| # | Piece | Why |
|---|---|---|
| 1 | **One maker-checker check for every workflow.** The 20 checks above call one shared check, which decides whether a self-approval is allowed. Each keeps its own message. The fee rulebook and the macro data overrides use the same check when they gain maker-checker (phases 3 and 4) | Today 6 of 20 honour the override, each in its own way |
| 2 | **A server guard.** The override can work only when the server's environment file declares a non-production environment (`APP_ENV` other than `production`, for example `uat`) **and** the governed setting allows it. Under a production environment a self-approval is refused whatever the setting says, and *Allowed for administrators* cannot be proposed or approved there. **During UAT, MAIIC's server runs with `APP_ENV=uat`; at handover MAIIC ICT sets `APP_ENV=production`** as the installation guide says (decision S11) | It cannot be left on, or switched on, in production |
| 3 | **Switched on for UAT without a second person, only from the server.** A fresh install under a non-production environment seeds the override as allowed, labelled as seeded for UAT. On an existing database (MAIIC's server, where the setting already exists) the console command `governance:uat-mode on` writes the approved value, labelled "Switched on for UAT from the server console", audit-logged with the person and the environment. It works only under a non-production environment. `governance:uat-mode off` works under any environment | Removes the first-switch problem; only someone with server access can do it |
| 4 | **Every self-approval recorded as one.** Each approval made under the override writes one audit entry, "Maker-checker override used", naming the workflow, the record, the person and the setting in force (the "Override used" event of section 8.2). Where the record has an approver label it also carries "UAT self-approval (administrator override)" | A test approval can always be told from a real one |
| 5 | **A banner on every screen** while the override is in force on that server: *"UAT mode: maker-checker relaxed for administrators"* | Nobody mistakes UAT for live use |
| 6 | **A self-approvals report** of everything decided under the override, read from those audit entries, by workflow, person and date, exportable | Shows at handover exactly what was approved without a second person |
| 7 | **Handover.** The production database starts clean, so UAT approvals never carry into it. If a UAT database were ever promoted to production, every item on the self-approvals report must be re-approved by two people first. Switching the override off is an ordinary governed change with a second person, recorded with the handover sign-off and its documents; **the override is never used to approve a change to its own setting**. Setting `APP_ENV=production` also stops it, whatever the setting says | The two-person rule is fully on from the first day of live use |

### 8.10 Decisions carried into a fresh database (the decisions pack)

**Why.** The bootstrap rebuilds a database from files: the E-Banker packs, the take-on workbook, the trial balances and the models. It cannot rebuild what people decided in the system, because those decisions are in no file. By 15 October 2026 MAIIC's database held decisions of that kind: 277 fee lines reviewed under maker-checker, 113 EIRs locked by Edward, Finance's 2025 fee workbook and its integral-column re-import, the fee source precedence set to Finance first (proposal 68), the correction of loans 077 and 110 (6 fee lines moved, loan 110 reopened and both EIRs locked again), and the user accounts. A fresh bootstrap would lose all of them. The decisions pack carries them.

**What it is.** A folder, `docs/bootstrap/decisions/<date>/`, committed beside the E-Banker packs. It holds one file per kind of decision and a manifest with each file's SHA-256 and a tie-out of what the source database held. Finance's fee workbook is carried as the lines it loaded, as they now stand: the system does not keep an uploaded workbook once it is loaded. A pack changed after export is refused. It is written by an export from one database and replayed into a fresh one by the bootstrap.

**Export.** `decisions:export` reads a database and writes the pack. It writes nothing to the database it reads. It is run on MAIIC's server by MAIIC ICT or Edward, never by Dupleix on MAIIC's database, once the server has the release that contains it (phase 3a). Every row is named by its business key, never by a database id, because a fresh load numbers its rows differently:

| Decision | Business key | What is carried |
|---|---|---|
| Fee line classified and reviewed | The line's source system and source transaction id; for a Finance workbook line without them, loan, fee type, amount, date and description | Integral or not, the cash-flow direction, the reason, the rule applied, who classified it and when, who reviewed it and when |
| Fee line moved to another loan | The line's key and its original loan and date | The new loan, the new date, the reason, who moved it and when |
| Overlapping fee lines kept separate or treated as one | Both lines' keys | The decision, the reason, who decided |
| Held GL fee tied to a loan, or dismissed | The GL posting's voucher and the loan | Who tied or dismissed it, the reason |
| Finance fee workbook loaded | Each line's key, as for a fee line | Every line it loaded, as it now stands, and the months it covers |
| Governance setting approved by people | Key, value and effective date | Who proposed it, who approved it, when, and the reason. The UAT mode setting is the server's own (section 8.9) and is not carried |
| Fee rule approved by people | The rule's name and wording | Who wrote it, who approved it, the reason |
| EIR locked by a person | The loan | Who calculated it, who locked it, when, the locked rate and a fingerprint of the cash flows it was solved on |
| EIR reopen request | The loan and the request date | The request and its decision, carried as history |
| User | E-mail | Name, roles and direct permissions; never the password or the two-factor secret |
| Supporting document | Its SHA-256 | The file itself, with its name, type, description, who uploaded it and when, and every carried decision it supports, attached or linked |

Every person is carried by e-mail and matched to the user of the same e-mail in the fresh database.

**Replay.** `eir:bootstrap --with-decisions=<date>` replays the pack in two places:

1. **After the fee build, before the schedules are approved:** the users; the governance settings; the fee rules; Finance's workbook, loaded through the normal import with its tie-out; the overlap decisions, held-fee ties and dismissals; the fee moves; then the classifications and reviews. The fee source precedence is then applied once to the result.
2. **In place of the bootstrap's own lock, for the loans the pack names:** the EIR is calculated and locked under the person who locked it in the source database, but only if the rate and the cash-flow fingerprint come out the same. Every other loan is locked under the System Bootstrap label as today.

A reopen request is not executed again. In the source database it reopened an EIR so that fee lines could be moved. In the replay the lines are moved before any EIR is locked, so the request is carried as history on the loan.

**Documents and the evidence rule.** The pack carries the supporting documents behind the decisions it carries, each file under its SHA-256, and the moment the supporting-document rule came into force on the source (section 8.7), or that it had not yet come into force there. On replay a document is stored once and attached to the first decision it supports; a further decision it supports gets a link, as on the source. A change to a setting that requires a document is replayed with its documents. If the pack carries none, the change is carried only if the source approved it before the rule came into force there; it is then labelled *approved before supporting documents were required* and counted apart on the replay report and in the tie-out. If the source approved it after that moment, it is refused and listed, and no proposal is left behind.

**Labelled as carried, never as new.** Each replayed decision keeps its original person and date on the record. Each writes one audit entry, *"Carried from <database> by the decisions pack of <date>"*, naming the original person and date. It never carries the System Bootstrap label, because it was a person's decision and not an automated one. A decision the source database recorded as a UAT self-approval is carried with that label, and section 8.9 item 7 still applies to it.

**What does not match is listed, never forced.** A decision whose row is not found in the fresh database, or whose row differs, is not applied and goes on the replay report with the reason. For example: a fee line the new code builds differently, a workbook whose tie-out fails, or an EIR whose rate or cash flows differ. The line stays pending, or the EIR stays calculated and unlocked, for a person to decide again. The report is written to the bootstrap output and to `storage/app/decisions/replay-<date>.csv`.

**Not carried, because the engines recompute them:** the stages, the Stage 3 history, the ECL, the revenue, the amortisation and every other figure derived from the data and the decisions.

**The checks.** The bootstrap without `--with-decisions` is unchanged, and its verify checks and golden values stay as they are. A bootstrap with a pack ends with a tie-out against the pack's manifest: reviewed fee lines, locked EIRs, each locked rate, the governance settings and the users. Each must equal the source database, or be named on the replay report. The verify checks of a bootstrap with a pack are recorded beside the golden values as a second set, since people's decisions move the EIR and the figures that follow it.

## 9. Decisions owed

| Ref | Decision | Owner | Recommendation |
|---|---|---|---|
| S1 | Adopt directive-aligned staging, and from which month | Dr Thom, with the board or its committee | Adopt from the first month not yet locked after the comparison runs have been reviewed; the effect is shown monthly until then |
| S2 | The Stage 2 threshold for medium and long-term facilities (O13) | Dr Thom | Keep 31 days unless documented evidence supports 91 |
| S3 | The Mega Farm term class | Finance, then Dr Thom | By tenor, as the directive |
| S4 | The cure test | Dr Thom | Arrears cleared, then 3 month-ends |
| S5 | Whether to keep each accepted departure of section 2.3 visible on the face of the monthly report until S1 | Finance | Yes |
| S6 | Which settings require a supporting document before approval, beyond those named in section 8.3 | Dr Thom, with Finance | Every setting that is a Phase 0 sign-off, and every staging setting |
| S7 | Who may view the supporting documents | MAIIC ICT, with Dr Thom | Users holding the governance permission, and the auditors through the auditor's pack |
| S8 | Which other approvals require a supporting document (section 8.6) | Dr Thom, with Finance and Credit | As marked in the tables of section 8.6 |
| S9 | Whether a fee rule already approved by the person who wrote it must be approved again by a second person | Dr Thom | Yes: every rule the matcher uses is re-approved under maker-checker before the fee review starts |
| S10 | Whether to apply the no-back-dating rule (section 8.8, item 4) ahead of the rest of this build | Dr Thom | Yes: it is a small change and protects the locked months now |
| S11 | Which environments may use UAT mode, and who switches it off at handover | MAIIC ICT, with Dr Thom | The UAT environment only: MAIIC's server runs `APP_ENV=uat` during UAT and `APP_ENV=production` from handover; the handover sign-off records the switch-off, approved by a second person |
| S12 | Where the decisions pack is kept | MAIIC ICT, with Edward | In the project repository beside the E-Banker packs, which already hold MAIIC's data; never with passwords or two-factor secrets |
| S13 | How carried users sign in for the first time | MAIIC ICT | Each sets a new password through the reset link; no password is carried |
| S14 | Who runs the export on MAIIC's server, and when | MAIIC ICT, with Edward | MAIIC ICT, read-only, before any fresh bootstrap that is to replace a database people have worked in |

## 10. Acceptance

1. **Seeded default.** On a fresh bootstrap every staging setting holds the 31 December 2025 option, effective from inception, and no directive-aligned setting is in force.
2. **No switch by date.** A month after any date is still staged on the seeded default until a preset is approved in the Governance Centre; the bootstrap never approves one.
3. **Adoption.** Approving directive-aligned staging from a month stages that month and later ones on the new rules; locked months are unchanged.
4. **Reproduction of the December ladder.** Under the 31 December 2025 policy, December 2025 is staged on one ladder (1 to 30 days Stage 1; 31 to 180 Stage 2; over 180 Stage 3) from the bucket ageing, and every difference from the workbooks' 108 facilities is listed with its cause: a facility not in one of the two books, a different arrears bucket, or a different balance.
5. **Comparison.** The comparison run for December 2025 and for September 2026 lists every loan whose stage differs between the two presets, each with its reason and ECL effect, and its totals reconcile to the two stagings.
6. **RBM return unchanged.** The RBM return for a month is identical whichever staging policy is in force.
7. **No silent default.** Removing a staging setting for a month stops staging with a named error, as today.
8. **Regression.** On a fresh bootstrap every verify check that does not follow the stage still passes (ledger, take-on, GL and RBM ties); the checks that follow the stage (ECL, discounted allowance, Mega Farm provision, EIR revenue) move only as the comparison run explains, and are set again from that bootstrap once Edward accepts them (section 6).
9. **Every change logged.** Seeding, proposing, adding or removing a document, approving, rejecting, withdrawing, applying a preset and using the override each write one audit entry with the fields of section 8.2, and the setting's History view shows them in order.
10. **Required evidence.** A staging preset or a Stage 2 rebuttal cannot be approved without at least one supporting document; the refusal names what is missing.
11. **Documents safe.** An uploaded document is stored outside the public web folder, cannot be opened without the governance permission, and a file altered on disk is reported as altered when opened.
12. **Append-only.** Editing or deleting a governance audit entry through the system is impossible, and doing it directly in the database is refused; a change made with the guard bypassed is found by the chain check, which names the first broken entry.
13. **Other approvals.** Every workflow of section 8.6 records its proposal, decision (approve, reject or withdraw) and documents in the same log, and refuses approval without the evidence its table marks as required.
14. **Fee rulebook control.** The person who wrote or changed a fee rule cannot approve it; approval and switching a rule on or off need a reason and a second person; the matcher uses only rules approved that way.
15. **Approvals register.** The Approvals view lists every pending and decided item across all workflows and reconciles to the audit log: a decided item with no audit entry, and an audit decision entry for an item not on record, are each named. A document linked to a second decision counts as that decision's evidence, and an approval made before supporting documents were required is labelled as such.
16. **Run record.** Every month-end run writes a run record with the frozen snapshot of section 8.8; its fingerprint verifies, and a snapshot altered afterwards does not; every result row of the month carries the run number; an engine run from a screen gets a run record of its own.
17. **Run of record.** Locking a month names the run whose figures were reported, and is refused when there is none or when a screen run has changed the month since; a later rebuild of an unlocked month supersedes earlier runs and keeps them.
18. **No back-dating.** A setting or rule change with an effective date inside a locked month is refused unless approved through reopen-and-restate with documents.
19. **Re-performance.** Re-running a locked month from its snapshot reproduces its stored stages, ECL and revenue loan by loan, or names each difference.
20. **One check.** Every workflow of section 8.6 and the Governance Centre honour the override through the same check; with the override not allowed, every one refuses a self-approval.
21. **Production guard.** Under a production environment a self-approval is refused even with the setting allowed, the allowing option cannot be proposed or approved, and `governance:uat-mode on` is refused.
22. **Labelled.** Every approval made under the override carries the UAT self-approval label on the record and in the audit log, and appears on the self-approvals report; the banner shows on every screen while the override is in force.
23. **Handover.** Switching the override off needs a second person (the override never approves a change to its own setting); afterwards every workflow refuses a self-approval.
24. **Export writes nothing.** `decisions:export` leaves the database it reads unchanged, and its pack holds no database id, no password and no two-factor secret.
25. **Replay reproduces.** A fresh bootstrap with a pack exported from a database ends with the same reviewed fee lines, locked EIRs and locked rates, governance settings and users as that database, or names each difference on the replay report.
26. **Carried, not new.** Every replayed decision keeps its original person and date, carries the decisions-pack audit entry, and never the System Bootstrap label.
27. **Nothing forced.** A decision whose row is missing or different in the fresh database is left undecided and listed with its reason.
28. **No pack, no change.** A bootstrap without a pack gives the same verify results as before the pack was built.

## 11. Build order

The work is done in five phases, in the order below. Governed staging comes first, because it is the purpose of this specification; the protection of locked months and UAT mode follow, then the fee rulebook control, then the rest of the governance record. The build is done on its own branch, and each phase is merged and released when all its sections are complete and confirmed (section 11.1). The sizes are Dupleix's estimates of the build.

| Phase | Work | Sections | Acceptance | Size |
|---|---|---|---|---|
| **1. Governed staging** | The settings, the presets and their seeding, with single-ladder staging as the default; `StagingService` reading every rule from the settings; `staging_dpd` beside `overdue_days`; the comparison run with its tables, command, panel, export and audit-workbook sheet; then, after the phase is merged and released, the comparison runs for December 2025 and September 2026 on MAIIC's server | 3, 4, 5, 6, 7 | 1 to 8 | About 3 days |
| **2. Protect the locked months, and UAT mode** | The no-back-dating rule: no change to a governed setting or rule takes effect inside a locked month except through reopen-and-restate with documents (decision S10). UAT mode: one maker-checker check for every workflow, the production guard, the UAT seeding, the self-approval label, the banner and the self-approvals report (decision S11). Both touch the governance service and share one release | 8.8 item 4, 8.9 | 18, 20 to 23 | About 2 to 2.5 days |
| **3. Fee rulebook control** | Second-person approval with a reason, reject and withdraw, switching a rule on or off as a proposal; then the re-approval of every rule the matcher uses (decision S9), so that the fee review can start on controlled rules | 8.6 (fee rulebook) | 14 | About half a day |
| **3a. Decisions pack** | The export and the replay of section 8.10, the replay report and the tie-out; then an export from MAIIC's database by MAIIC ICT and a bootstrap with it on a throw-away database, to show the decisions carried | 8.10 | 24 to 28 | About 1 to 1.5 days |
| **4. The governance and audit record** | The Governance Centre change log (reject and withdraw, seeding entries, supporting documents on private storage, required evidence, history view, append-only chained log); the other approvals on the same log and document store, and the Approvals view; the run record, run numbers on result rows, run of record at lock, the two reports and re-performance | 8.1 to 8.5, 8.6, 8.7, 8.8 | 9 to 13, 15 to 17, 19 | About 5.5 days |
| Later | Setting 9, once each loan's PD at origination is kept | 3 | | To be planned |

The total is about 12 to 13 days of build, with the decisions pack (phase 3a, added on 15 October 2026). Governed staging is merged and released after about 3 days; the protection of locked months and UAT mode about 2.5 days later, after which testing on MAIIC's server can run without waiting for a second person on each step; the controlled fee rulebook after about 6 days in all. Until phase 2 is released, a setting can still be back-dated into a locked month (section 8.8), so no governed change should be approved with an effective date inside a locked month in the meantime. The fee rulebook joins the shared maker-checker check of UAT mode when phase 3 gives it maker-checker. Not included: the time to resolve the bootstrap's slow final steps seen on 15 October 2026, which must be fixed before the first merge, since every merge and release depends on the bootstrap passing.

### 11.1 How the build is run

**One branch.** The build is done on a branch of its own, `staging-governance`, taken from the main development branch (`eir_revenue_recognition`) when the build starts. The branch is pushed to the repository after each section so that the work is backed up and visible. It is brought up to date with the main branch (rebased) at the start of each section, so that colleagues' work in the same files is taken in as it happens and each merge is clean.

**A confirmation before each section starts.** Before work on a section begins, Dupleix states what the section will build, the files it will change, the tests it will add and its estimated size, and waits for Edward's confirmation to start. A section can be deferred, reordered or narrowed at this point.

**A confirmation when each section ends.** When a section is built, Dupleix reports what was built, the commits, the tests and their results, the bootstrap result on the branch (the verify checks, section 6), and anything found along the way that changes the plan, and waits for Edward's confirmation that the section is complete. Only then does the next section start. A section that is not confirmed is reworked on the branch.

**A merge at the end of each phase.** When every section of a phase is confirmed, the branch is rebased onto the main branch, the full test suite and a bootstrap on a fresh database are run (the verify checks must pass, or move only as section 6 allows), and the phase is merged into `eir_revenue_recognition` and `master` with a merge note listing its sections, their confirmations and the decisions (section 9) they rest on. The phase is then released to MAIIC's server by the release steps of the integration document (section 2.2: push, pull, migrate, bootstrap with the verify checks passing). The next phase continues on the same branch, rebased onto the merged main branch.

**The sections and merges, in order.** Each section row is one start-and-end confirmation; each merge row is one merge and release:

| # | Section | Phase | Acceptance tests closed |
|---|---|---|---|
| 1 | 3: the settings and presets | 1 | 1 to 3, 7 |
| 2 | 5: the separate staging day count, the RBM return on the directive's count | 1 | 6 |
| 3 | 4: the staging comparison run | 1 | 4, 5 |
| **M1** | **Merge and release phase 1**, then the comparison runs for December 2025 and September 2026 on MAIIC's server | 1 | 1 to 8 |
| 4 | 8.8 item 4: no back-dating into a locked month | 2 | 18 |
| 5 | 8.9: UAT mode | 2 | 20 to 23 |
| **M2** | **Merge and release phase 2** | 2 | 18, 20 to 23 re-run on the merged code |
| 6 | 8.6: fee rulebook control, then the re-approval of the rules in use (decision S9) | 3 | 14 |
| **M3** | **Merge and release phase 3** | 3 | 14 |
| 6a | 8.10: the decisions pack | 3a | 24 to 28 |
| **M3a** | **Merge and release phase 3a**, then MAIIC's export and a bootstrap with it on a throw-away database | 3a | 24 to 28 |
| 7 | 8.1 to 8.5: the Governance Centre change log | 4 | 9 to 12 |
| 8 | 8.6: the other approvals | 4 | 13 |
| 9 | 8.7: the register of approvals | 4 | 15 |
| 10 | 8.8: the run record | 4 | 16, 17, 19 |
| **M4** | **Merge and release phase 4**: all 28 acceptance tests, the full test suite and a bootstrap on a fresh database with the verify checks passing | 4 | All |

**Why this way.** Each phase reaches MAIIC's server as soon as it is complete, so governed staging (phase 1), the protection of the locked months and UAT mode (phase 2) and the controlled fee rulebook (phase 3) are in use without waiting for the rest; and each merge is small enough to review on its own.

## 12. Glossary

| Term | Meaning |
|---|---|
| **Stage 1, 2, 3** | IFRS 9's three buckets: performing (twelve-month ECL), a significant increase in credit risk (lifetime ECL), and credit-impaired (lifetime ECL, interest on the net basis) |
| **Days past due** | How long the oldest unpaid instalment has been overdue at the month-end |
| **Arrears bucket** | A band of overdue amounts in E-Banker's loan book report (1 to 30, 31 to 90, 91 to 180, 181 to 270, 271 to 360 days) |
| **Single-ladder staging** | MAIIC's staging policy as at 31 December 2025: one set of day thresholds for every facility, read from the arrears buckets |
| **Directive-aligned staging** | Day thresholds by facility term under the RBM DFI Directive 2018, with MAIIC's missed-instalment policy, qualitative triggers, data corrections and a cure test |
| **Preset** | A named set of all the staging settings, chosen in one governed change |
| **Comparison run** | A staging of one month under another preset or set of settings, beside the booked staging, that changes nothing booked |
| **Cure period** | The month-ends a loan must stay below a threshold before it moves to a lower stage |
| **Rebuttal** | Setting aside an IFRS 9 presumption (30 days for Stage 2, 90 days for default) on documented, reasonable and supportable evidence |
| **Maker-checker** | One person proposes a change and a second person approves it |
| **Supporting document** | A file attached to a governance change as its evidence: a minute, a policy paper, an analysis or correspondence |
| **Append-only** | A record that can be added to but never changed or deleted |
| **Run record** | The frozen record of everything one month-end run used: settings, rules, models, data loads and code |
| **Run of record** | The run whose figures were reported for a month, named when the month is locked |
| **UAT mode** | The non-production setting in which administrators may approve their own proposals, every such approval labelled and reported, never possible in production |
| **Re-performance** | Re-running a locked month from its run record to show the reported figures can be reproduced |
| **Chain check** | A test that each audit entry still carries the fingerprint of the one before it, so that a changed or removed entry is found |
