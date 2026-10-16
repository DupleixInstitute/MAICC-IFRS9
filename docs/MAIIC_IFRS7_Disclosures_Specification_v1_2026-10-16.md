---
kicker: MAIIC IFRS 9 and EIR system
title: MAIIC IFRS 7 Disclosures Specification v1
subtitle: Interest revenue (IFRS 7.20(b)) and sensitivity analysis (IFRS 7.35G) in the IFRS 9 note
date: 16 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance and Risk; companion to the EIR Engine Specification v4 (section 12, the report hub) and the Staging Module Specification v1 (section 6, what follows the stage)
status: Draft for review — not built
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this specification does

The IFRS 9 note for the annual financial statements (`Ifrs9NoteService`, `Ifrs9NoteDocuments`) already reconciles the loss allowance and gross carrying amount by stage (IFRS 7.35H, 35I), the position with its comparative (35M), the impairment charge (35H), the basis of measurement, write-offs (35L) and modified financial assets (35J, 20A) — sections (a) to (h) of the exported note. Two disclosures auditors ask for early in a file review are not yet assembled:

- **(i) Interest revenue calculated using the effective interest method (IFRS 7.20(b))**, with the Stage 3 net-basis treatment (IFRS 9 5.4.1(b)) shown rather than buried in the engine.
- **(j) Measurement uncertainty and sensitivity analysis (IFRS 7.35G)**: how the ECL would differ under reasonably possible alternative assumptions.

Both become two new sections of the existing note, built, checked and exported the same way as (a) to (h): same PDF/Word/Excel/CSV pipeline, same fail-closed rule that a disclosure is never shown on data that cannot support it. Neither needs a new engine. Section (i) reads a table the revenue engine already locks every month (`eir_amortisation`). Section (j) reads data the FLI month-end close already signs off (`fli_run_snapshots`, `scenario_shocks`) for its first table, and adds one new, small computation for its second.

Both disclosures gain **Governance Centre settings**, in the same pattern as the staging and FLI modules: proposed by one person, approved by a second, with an effective date and a comment, never fixed in code. Section (i) is always shown — IFRS 7.20(b) is mandatory wherever assets are measured at amortised cost — but its **basis and level of detail are governed choices**, defaulting to this specification's recommendation. Section (j)'s **first table is always available** when the underlying FLI close exists; its **second table, and the whole section, can be switched off** by Governance Centre decision, because the isolated stress table is new judgement (shock sizes) that MAIIC may not be ready to own on day one.

## 2. Section (i): Interest revenue (IFRS 7.20(b))

### 2.1 What it discloses

Total interest revenue calculated using the effective interest method for the period, split so the Stage 3 net-basis treatment is visible: Stage 1 and 2 accrue on the gross carrying amount; Stage 3 accrues on the carrying amount net of the loss allowance (IFRS 9 5.4.1(b)), and the unwind of the discount on that allowance is its own component of the Stage 3 figure, not hidden inside it.

### 2.2 Data source — nothing new is computed

`eir_amortisation` already holds one locked row per contract per period (`EirRevenueService`), with exactly the columns this disclosure needs:

| Column | Use |
|---|---|
| `interest_accrued` | The EIR interest for the month |
| `interest_basis` (`GROSS` / `NET`) | Which of the two bases accrued the month, already decided by the stage at the start of the month (section 2.3) |
| `unwind_amount` | The Stage 3 time-value growth of the allowance, kept apart from the rest of the net-basis interest |
| `modification_gain_loss` | Already disclosed in section (h); not repeated here, but reconciled against it as a check |

Grouped by `reporting_period` between the note's opening and closing months, joined to `loan_books.ifrs9stage_post_qualitative` for the stage, this is one query: no new table, no new column, no change to the revenue engine.

### 2.3 The settings

| # | Setting | Options | Recommended default | Comment |
|---|---|---|---|---|
| 1 | `ifrs7_interest_revenue_basis`: the headline figure | GL-posted interest income, with the EIR variance as supporting detail · EIR-run interest revenue (the locked engine figure) · Both, shown side by side | **GL-posted interest income, with the EIR variance as supporting detail** | The financial statements' income statement has to tie to the trial balance. The GL-posted figure is the one Finance can defend to the auditor without explanation; the EIR-run figure (and its cause-coded variance, from the existing `EirGlReconciliationService`) is shown underneath as the explanation for the difference, not as a competing headline. The "Both" option exists for a period where Finance wants the comparison foregrounded, e.g. the first year this note is used |
| 2 | `ifrs7_interest_revenue_detail`: how far the split goes | Total only · Split by stage and interest basis (gross vs net) | **Split by stage and interest basis (gross vs net)** | IFRS 7.20(b) only requires the total; the split is this specification's recommendation because it is what makes the Stage 3 net-basis treatment auditable without a separate request. "Total only" is kept as an option for a period where Stage 3 history is thin (a new book) and the split would be noise |

Open choice **O23**: Finance's confirmation of setting 1 (recommended: GL-posted) before this section is first issued. Until confirmed, the setting is seeded at the recommended default and the note's basis paragraph says so, exactly as the staging settings say "seeded default" rather than "MAIIC approval" (Staging Module Specification v1, section 3.1).

### 2.4 Behaviour

- Fails closed the same way as the rest of the note: a contract's month with no locked `eir_amortisation` row (the revenue run has not reached it) is named as missing, not estimated — the note's existing `checks()` gains an entry for this, at the same `fail`/`warn` severity as the other input checks.
- When `ifrs7_interest_revenue_basis` is GL-posted, the GL total is read from the same trial-balance source `EirGlReconciliationService` already reconciles against, so the note's figure and the reconciliation screen's figure are always the same number, never two queries that can drift apart.
- A check reconciles `eir_amortisation.modification_gain_loss` summed over the period against the modification gain/loss already disclosed in section (h) (`RestructureRegisterService::ifrs7Disclosures`); a difference is a `warn`, since the two are built from different tables (the roll-forward row vs. the restructure register) and are expected to agree, not assumed to.

## 3. Section (j): Measurement uncertainty and sensitivity analysis (IFRS 7.35G)

### 3.1 What it discloses

How the closing ECL would differ under reasonably possible alternative assumptions: **Table 1**, the governed macro-economic scenarios already run and signed off for the month; **Table 2**, an isolated stress of the probability of default and the loss given default, holding everything else — including the macro scenario — at its booked value.

### 3.2 Table 1: macro-economic scenarios (always available when a close exists)

**Data source — already computed and signed off**: the FLI month-end close (`fli_run_snapshots.per_scenario`, `FliBridgeService`) holds, per scenario in the governed scenario set, its weight, its ECL and its contribution to the probability-weighted ECL — this is the same data the `scenario-ecl` report (`Ifrs9ReportsController::scenarioEcl`) already shows on screen. The shock sizes behind each scenario (`scenario_shocks`: which macro variable, by how much, in which forecast year) are already governed and already captured.

**Table shape:**

| Column | Source |
|---|---|
| Scenario (base marked) | `governed_scenarios.name` |
| Probability weight | `governed_scenarios.weight` |
| ECL under the scenario | `fli_run_snapshots.per_scenario[].ecl` |
| Effect vs. the booked (weighted) ECL | `scenario ECL − snap.booked_ecl` |
| Key shocks applied | `scenario_shocks`, summarised (variable, year, direction, size) |

**Fails closed**: if no FLI close is signed off for the closing month, Table 1 says so — the same honesty rule the existing `macro-scenario` report already follows when there is no approved scenario set — and the section does not fabricate a scenario.

### 3.3 Table 2: isolated PD/LGD stress (new computation; governed on and off)

**What it is**: unlike Table 1, which moves the macro-economic scenario (and therefore the PD, through the FLI model), Table 2 answers a narrower, more traditional audit question — "what if PD or LGD alone were worse than booked, holding the forward-looking view constant?" This isolates the credit-risk-model assumption from the macro-economic one.

**Computation**: for each stress line, re-base every loan's booked ECL by the ratio of the stressed PD (or LGD) to the booked one — the same technique `EclBuildUpService::lineage()` already uses to re-base booked ECL to the PD before FLI (`ecl_pre = booked * horizon_pd_pre / horizon_pd_post`), generalised to an arbitrary multiplier instead of a second saved PD. This is arithmetic on figures the ECL run already saved (`ecl_value`, `pd_post_fli`, `lgd_value`), not a new ECL run: no new PD/LGD model, no re-staging, no change to anything booked.

**Stage treatment**: Stage 3's PD is 100% by definition and is not shocked by the PD setting; a PD stress line therefore only moves Stage 1 and Stage 2. An LGD stress line moves all three stages, since LGD applies regardless of stage. This is a modelling consequence, not a governed choice, and the table says so against the Stage 3 column.

**Mega Farm programme loans** are measured by their own engine on their own PD and LGD (decision D30) and are kept out of Table 2 for the same reason `EclBuildUpService` already keeps them out of the forward-looking build-up: shown as their own line, not blended into a ratio that does not apply to them.

**Table shape:**

| Column | Source |
|---|---|
| Stress line (e.g. "PD +50%", "LGD +20pp") | The governed shock, section 3.4 |
| ECL at the stress (by stage and total) | Booked ECL rebased by the shock ratio, per loan, summed |
| Effect vs. booked ECL | Stress total − booked total |
| Loans/EAD the line could not rebase | Any loan without a saved PD/LGD that ties to its booked ECL (`EclBuildUpService::lineage()`'s own `ties`/`ties_pre` test), named, never assumed at zero |

### 3.4 The settings

| # | Setting | Options | Recommended default | Comment |
|---|---|---|---|---|
| 3 | `ifrs7_sensitivity_disclosure`: whether this section is shown, and how much of it | Not disclosed · Macro scenarios only (Table 1) · Macro scenarios and isolated PD/LGD stress (Tables 1 and 2) | **Macro scenarios and isolated PD/LGD stress (Tables 1 and 2)** | This specification's recommendation is both tables: Table 1 is close to free (the data is already signed off every month); Table 2 adds the traditional credit-risk stress auditors expect beside the macro view. "Macro scenarios only" is for a period where Risk has not yet agreed the PD/LGD shock sizes (setting 4/5 below); "Not disclosed" is for a period before any scenario set is signed off, or while this disclosure is still under review with Risk and the CFO |
| 4 | `ifrs7_sensitivity_pd_shock`: the stress applied to the probability of default (Stage 1 and 2 only) | +25% relative to the booked PD · +50% relative to the booked PD · +100% relative to the booked PD (doubling) | **+50% relative to the booked PD** | A judgement for Risk, not a figure the system should assume; set as a discrete, governed choice in the same style as the staging thresholds (Staging Module Specification v1, section 3), not a free-typed number, so every value ever used is one of a small, auditable set |
| 5 | `ifrs7_sensitivity_lgd_shock`: the stress applied to loss given default (all stages) | +10 percentage points · +20 percentage points · +30 percentage points | **+20 percentage points** | Same reasoning as setting 4. Expressed in percentage points, not a relative multiple, because LGD is already a percentage of exposure and a relative shock on a low LGD (e.g. secured lending) would understate the stress Risk usually intends |

Open choice **O24**: whether MAIIC wants both tables from the first period this note is issued, or wants to start with Table 1 only while Risk agrees the shock sizes (recommended path: start at setting 3's "Macro scenarios only" and move to "Tables 1 and 2" once O25 is settled, both by governed proposal and approval, with no restatement of a locked month).

Open choice **O25**: Risk's and the CFO's sign-off on the PD and LGD shock sizes (settings 4 and 5), seeded at this specification's recommended defaults until decided.

### 3.5 Behaviour

- A change to setting 3, 4 or 5 is a governed change like any other: proposed, approved, effective from a date, never silently applied to a locked month, and recorded in the auditor's pack the same way every other Governance Centre change already is (`ComplianceAudits::pack()`, "Governance changes to {period}.csv").
- Because Table 2 is a re-basing of the booked ECL, not a new run, a change to the shock size (setting 4/5) is **not** a restatement of anything booked, in the same sense a change to a staging threshold is not a restatement of a prior locked month (IAS 8, applied prospectively) — it only changes what the *next* note shows as the stress line.
- If `ifrs7_sensitivity_disclosure` is "Not disclosed", section (j) is omitted from the note entirely (not shown empty), and the note's own basis paragraph does not claim a sensitivity analysis was performed.

## 4. How the defaults are seeded

- All five settings are seeded at their recommended defaults (section 2.3, settings 1–2; section 3.4, settings 3–5), effective from inception (`1900-01-01`), labelled as the seeded default and not as a MAIIC or CFO approval — the same convention as every other seeded Governance Centre setting (Staging Module Specification v1, section 3.1).
- The bootstrap never approves a change away from these defaults; only a governed proposal and a second person's approval does.

## 5. Data and code (for when this is built — not yet started)

**Settings.** Five settings added to `GovernanceService`'s settings array (`ifrs7_interest_revenue_basis`, `ifrs7_interest_revenue_detail`, `ifrs7_sensitivity_disclosure`, `ifrs7_sensitivity_pd_shock`, `ifrs7_sensitivity_lgd_shock`), each with its label, description, options and default exactly as drafted in sections 2.3 and 3.4, in the same array shape as every existing setting (e.g. `trueup_gl_account`, `keyman_insurance_treatment`).

**Note service.** Two new private static methods on `Ifrs9NoteService`, alongside the existing `modifications()`: `interestRevenue(string $opening, string $closing, ?int $portfolioId)` (section 2) and `sensitivity(string $closing, ?int $portfolioId)` (section 3), each returning `null`/an honest "not available" payload when its governed setting excludes it or its data is missing, added to `build()`'s returned array, and given their own entries in `checks()`.

**Note documents.** Two new sections in `Ifrs9NoteDocuments::payload()` (and the PDF/Word renderers), lettered `(i)` and `(j)`, immediately after the existing `(h) Modified financial assets`, following the same `heading`/`note`/`columns`/`rows` shape as every other section — no change to the export pipeline itself (PDF/Word/Excel/CSV all read the same `payload()`/`build()` output today).

**Sensitivity computation.** No new engine. `EclBuildUpService::lineage()`'s re-basing technique (currently fixed to the pre-FLI PD) is generalised to take an arbitrary PD multiplier and an arbitrary LGD add-on, so Table 2 and the existing pre-FLI build-up share one method rather than two copies of the same arithmetic.

**Auditor's pack.** No change needed: the pack already carries every governance-setting change and the note is already (implicitly) available for a period through the existing reporting screens; the new sections appear in the note the pack would reference once this is built.

## 6. Summary of what needs an answer before this is built

| Open choice | Question | Who | Seeded until answered |
|---|---|---|---|
| O23 | Confirm the interest-revenue headline basis (setting 1): GL-posted (recommended), EIR-run, or both | Finance (Tamanda) / CFO | GL-posted, with EIR variance as supporting detail |
| O24 | Start the sensitivity section at Table 1 only, or both tables from the first period | CFO / Risk | Both tables (setting 3) |
| O25 | The PD and LGD stress sizes (settings 4 and 5) | Risk / CFO | PD +50%, LGD +20 percentage points |

Nothing above blocks the build — every setting is seeded at this specification's recommendation — but O23–O25 should go to Finance, Risk and the CFO for confirmation at or before the first period this note is issued with sections (i) and (j), the same way the staging preset and the FLI shock sizes went to their owners after being seeded (Staging Module Specification v1, sections 3.1–3.2).
