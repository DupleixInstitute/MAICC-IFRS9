<div class="cover">
<div class="k">MAIIC &nbsp;|&nbsp; IFRS 9 EFFECTIVE INTEREST RATE ENGINE</div>
<h1>Specification, version 3</h1>
<p>What we are building, what we have agreed, what is still open, and how the numbers are worked out</p>
<p>24 September 2026   |   Prepared by Dupleix Institute for the Malawi Agricultural and Industrial Investment Corporation plc (MAIIC)</p>
<p>Project sponsor: Dr Thomson Kumwenda, CFO   |   Engagement lead: Edward Mazibuko CA(SA)   |   Build lead: Kundai Muriwo</p>
<p>Status: working document for the meeting of 25 September 2026. Replaces the consolidated specification of 20 August 2026 as the single point of reference. It does not delete anything from that document; where this one is silent, the August document still applies.</p>
</div>

## 0. How to read this document

This specification is written for three readers at once: the CFO, who has to be confident that the numbers will stand up to Deloitte; the ICT manager, who has to supply the data and host the system; and the developers, who have to build it. So every section starts with the plain-language version and only then goes into detail. Words that a non-specialist might not know are explained in the glossary at the end (section 15).

Three conventions are used throughout:

- **"Agreed"** means a decision that has been taken, by whom and when. Section 3 lists them. They are not re-opened here.
- **"Not yet agreed"** means a choice that still has to be made. Section 4 lists them, each with the options and our recommendation. Nothing in section 4 is assumed anywhere else in the document without saying so.
- **"Proven"** means a fact that was tested against MAIIC's own data and reproduced. Where a figure is proven, the file and the count are given so that anyone can repeat the test.

One phrase is used deliberately every time: **the spread added to the prime rate (margin)**. In both of MAIIC's system manuals the bare word "margin" means something else (a collateral haircut in the loan origination manual, page 63; a grace concept in the core banking manual, pages 23 and 31). To avoid any confusion we never use the bare word.

## 1. Background, explained from the beginning

### 1.1 What an effective interest rate is, and why IFRS 9 insists on it

When MAIIC lends money, the borrower signs an offer letter that states an interest rate, a repayment pattern and, usually, an arrangement fee and legal fees that are deducted before the money is paid out. The rate on the letter is the **contractual rate**. But the borrower did not receive the full loan: fees came off the top. So the true return MAIIC earns on the cash it actually parted with is a little higher than the contractual rate. That true return is the **effective interest rate**, or EIR.

IFRS 9 (Appendix A) defines the EIR as the rate that exactly discounts the expected future cash payments over the expected life of the loan to the amount MAIIC actually advanced, net of fees that are an integral part of the loan. Interest income is then recognised at that rate on the loan's **amortised cost** (the amount advanced, plus interest recognised, minus cash received), not at the contractual rate on the outstanding principal. The difference between the two is small on any single month but it changes the pattern of income across the life of the loan, and it is what the auditors test.

Three IFRS 9 paragraphs do most of the work in this engine:

| Paragraph | What it says, plainly | Where it bites for MAIIC |
|---|---|---|
| Appendix A (definition) | Use contractual cash flows, ignore expected credit losses, include integral fees | Every loan |
| B5.4.5 | For a floating-rate loan, when the reference rate changes, re-estimate the future cash flows and carry on at the new rate. No catch-up adjustment | The 39 or so MAIIC loans that follow the Reserve Bank prime rate, 26 times since 2020 |
| B5.4.6 | If the estimate of cash flows changes for any other reason (for example, arrears or a changed repayment plan), recompute the amortised cost at the **original** EIR and take the difference to income now | Every loan in arrears; every restructured loan |
| 5.4.3 | When a loan is modified but not derecognised, keep the original EIR and book a modification gain or loss | The restructured loans |
| B3.3.6 (by analogy) | The "10 percent test": if the present value of the new cash flows differs from the old by 10 percent or more, treat it as a new loan | Deciding whether a restructure is a modification or a new loan |
| 5.4.1(b) | Once a loan is credit-impaired (Stage 3), calculate interest on the **net** carrying amount (after the loss allowance) | Stage 3 loans |
| 5.5.11 and B5.5.37 | 30 days past due is the backstop for a significant increase in credit risk; 90 days is the backstop for default | Stage allocation, which the existing ECL module already does |

### 1.2 What MAIIC has today

MAIIC's core banking system is **E-Banker**, from Virtual Galaxy, live since about October 2024 (the exact date is one of the questions for the meeting). Loans are originated in a separate **Loan Origination System (LOS)** and then pushed into E-Banker. E-Banker calculates and posts contractual interest every month; it does not calculate an EIR, an amortised cost or an expected credit loss. Dr Thom's own December 2025 assessment states this, and our search of the vendor's documentation found no IFRS 9 or EIR capability.

The IFRS 9 expected credit loss (ECL) calculation runs in the **MAICC-IFRS9** system that Dupleix built for MAIIC. That system already holds the monthly loan book, the client records, the staging rules and the ECL engine. The EIR engine described here is a module inside that same system (agreed decision D1), so that the ECL module and the EIR module read the same loan book and the same stage for every loan.

### 1.3 The three doors

The EIR enters IFRS 9 three times, and the engine has to serve all three:

1. **Measurement (door 1):** the amortised cost of each loan at each month-end.
2. **Revenue (door 2):** the interest income recognised each month, and the difference between that and what E-Banker posted contractually.
3. **Impairment (door 3):** the rate at which expected cash shortfalls are discounted in the ECL calculation. The ECL module used to use a placeholder; since August 2026 it reads the solved EIR.

### 1.4 What has happened so far (the short history)

| Date | Event |
|---|---|
| 21 Jul 2026 | Award letter from MAIIC's Internal Procurement Committee |
| 27 Jul to 18 Aug 2026 | Schema, import layer, solver, impairment rewiring and revenue engine built on branch `eir_revenue_recognition` |
| 31 Jul 2026 | Contract Effective Date (DUP/MAIIC/IFRS9/2026, USD 30,000, four milestones) |
| 3 Aug 2026 | Extracts A, B and C received from MAIIC |
| 4 Aug 2026 | Specification v1 |
| 19 Aug 2026 | Contract signed; trial-balance corpus and GL spools received |
| 20 Aug 2026 | Consolidated specification (v2.3) |
| 19 Aug to 2 Sep 2026 | The September build: trial-balance corpus, original-schedule governance (draft to approved version 1), date-sensitive solver with controlled reopening, time-phased ECL discounted at the EIR, standing fee rulebook, GL accrual base derived from the data |
| 3 and 10 Sep 2026 | Source-screen walkthroughs with MAIIC and the vendor: where each EIR input lives (LOS or E-Banker), the two schedules, fees posted by Finance, EMI-only repayment, restructure as a new sub-account |
| 10 Sep 2026 | Consolidated specification v2.5: Path E (source screens), the floating-rate reset design (Phase 5.1), the data request register (Appendix E) |
| 11 Sep 2026 | Consolidated Information Request to MAIIC: 15 numbered items |
| 17 Sep 2026 | Interest Rate Change History file received (dates later found to be corrupted in Excel, not in E-Banker) |
| 21 Sep 2026 | E-Banker and LOS user manuals received (request 14 closed) |
| 22 to 24 Sep 2026 | Teaching workbook, extracts research, field reference, reconstruction test, Extract B check, loan-book date scan |
| 25 Sep 2026 | Progress meeting with Dr Thom and Barry Makumba |

## 2. Where this document comes from: the references

Everything in this specification traces to one of the sources below. They are cited by their short name in square brackets.

| Short name | Document | Where |
|---|---|---|
| [Spec v1] | MAIIC EIR and Revenue Recognition Engine, Technical Specification, 4 Aug 2026 | OneDrive `3. Project Execution\specs\MAIIC_EIR_Revenue_Recognition_Engine_Spec_v1_2026-08-04.md` |
| [Spec v2] | Consolidated Technical Specification, v2.5 of 10 Sep 2026 (v2.3 of 20 Aug in OneDrive) | `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` in the repository; the OneDrive copy is v2.3 |
| [Inventory] | Source Screen Field Inventory from the 3 and 10 Sep 2026 walkthroughs | `2. Documents from clients\MAIIC EIR - Source Screen Field Inventory.docx` |
| [Analysis] | Branch inspection report, 24 Sep 2026 | `docs/EIR_Branch_Analysis_2026-09-24.md` in the repository; copy in the meeting pack |
| [Explained] | The engine explained, plain-language companion, 5 Aug 2026 | same folder, `..._Explained.md/.pdf` |
| [Build] | EIR Build (phases and acceptance bar) and Development of EIR (build log) | `docs/EIR_Build.md`, `docs/Development_of_EIR.md` |
| [Research] | MAIIC Data Extracts, Research Report, 24 Sep 2026 | `specs\MAIIC_Extracts_Research_Report_2026-09-24.md` |
| [Fields] | E-Banker and LOS field reference with manual screenshots, 24 Sep 2026 | `specs\MAIIC_EIR_Field_Reference_2026-09-24.pdf` |
| [Workbook] | Field reference workbook: every extract column, its manual name and page, which request asked for it | meeting pack, `MAIIC_Extracts_Field_Reference_Workbook_2026-09-24.xlsx` |
| [Recon] | Reconstruction test: the ten offer letters against the GL, 24 Sep 2026 | `Amortisation Analysis\offer_letter_reconstruction_test_2026-09-24.csv` |
| [ExtB] | Extract B against four offer letters, 24 Sep 2026 | `Amortisation Analysis\extract_b_offer_letter_check_2026-09-24.md/.csv` |
| [DateScan] | Loan-book date scan, 24 Sep 2026 | `specs\loan_book_date_scan_2026-09-24.md` |
| [PLR] | Repaired reference-rate series and the 20 rows for Barry to confirm | `Reference Rates\plr_reference_rates_corrected.csv`, `plr_dates_for_barry_to_confirm.csv` |
| [Teach] | IFRS 9 floating-rate EIR teaching workbook (10-year loan, fees, three missed instalments, year-6 restructure, 28 integrity checks) | `Documents\IFRS9_Floating_Rate_EIR_Illustration.xlsx` |
| [Manual] | E-Banker user manual (pages cited as "E-Banker p.NN") | MAIIC folder, `Ebanker MANUAL.pdf` |
| [LOS] | Loan Origination System manual (pages cited as "LOS p.NN") | MAIIC folder, `LOS MANUAL.pdf` |
| [Request] | Consolidated Information Request of 11 Sep 2026 (items 1 to 15) | `3. Project Execution\Correspondence - Information Requests\...UPDATED (to MAIIC).html` |
| [Contract] | Agreement DUP/MAIIC/IFRS9/2026, signed 19 Aug 2026 | `1. Engagement Contracting\Final Contract` |
| [Deck] | Progress deck for the 25 Sep 2026 meeting (20 slides with presenter notes) | meeting pack, `MAIIC_EIR_Progress_and_Data_Requests_2026-09-25.pptx` |

## 3. What we have agreed

Each decision below has been taken. The date and the person are given so that nobody has to re-derive it. Where a decision was taken by Edward Mazibuko on 24 September 2026 in answer to the fourteen design questions, it is marked "EM 24 Sep".

### 3.1 Scope and shape of the build

| # | Decision | By | Why |
|---|---|---|---|
| D1 | The EIR engine is a **module inside the existing MAICC-IFRS9 system**, on branch `eir_revenue_recognition`. There is **no separate repository** and no separate application. | EM 24 Sep | One loan book, one stage per loan, one login for the finance team; the ECL module already reads the loan book |
| D2 | Users are the **MAIIC finance team**. The system is **deployed into MAIIC's own environment**. Runs are **month-end only**. | EM 24 Sep | Matches the contract deliverables (configured client system, source code, training) |
| D3 | **Arrears and restructuring are in version 1.** They are not deferred to a later phase. | EM 24 Sep | Both are common in the book and both change the EIR calculation |
| D4 | The third moratorium shape (pay capital, defer interest) is **not built**. E-Banker's `Moratorium Type` has exactly two options, "Principle Only" and "Both (Interest + Principle)" (LOS p.76), and no loan book in 33 months shows a third shape. | EM 24 Sep, confirmed by [Manual] | Do not build what the source system cannot express |
| D5 | FinES concessional loans (fixed 10 percent, funded by the Reserve Bank) are calculated **on their stated terms**. No day-one fair-value adjustment. | EM 24 Sep | Keeps the engine to what the auditors have asked for |
| D6 | The engine **does not post to the general ledger**. It proposes journals; Finance posts them in E-Banker. | [Spec v2] s.14 | Scope boundary agreed in August |
| D7 | MAIIC supplies the **whole population** of loans, with the spread added to the prime rate (margin), by CSV import or by integration. The ten offer letters are a test sample, not the data source. | EM 24 Sep | "I only asked for 10 samples. We expect the client to give us the 100 percent population" |

### 3.2 How the calculation works

| # | Decision | By | Why |
|---|---|---|---|
| D8 | **One EIR per rate period.** A new child table `eir_rate_periods` holds the EIR that applied from each reset date. `contract_eir` stays the origination record and is never overwritten. | EM 24 Sep | B5.4.5: a floating loan gets a fresh EIR at every reference-rate change; the history must survive. [Spec v2] s.7.5 chose the spread-lock method (new EIR = new contractual rate + the fee spread locked at origination) with a full re-solve as the test oracle; each rate period stores both and flags any disagreement beyond solver tolerance |
| D9 | **Day count is actual/365, compounding monthly**, as the native convention. 30/360 is available as a governed alternative. | EM 24 Sep; proven in [Recon] | E-Banker's own postings reproduce to the cent on this basis (section 7.7) |
| D10 | A capitalising moratorium **compounds monthly**: the balance rises each month by exactly the interest posted. | proven in [Recon] | Observed on every moratorium loan in the sample |
| D11 | **Stage 3 interest is recognised on the net carrying amount** (IFRS 9 5.4.1(b)) by default. Governable. | EM 24 Sep | Inherits the standard's rule; the ECL module supplies the stage |
| D12 | The **reference rate is the Reserve Bank prime lending rate (PLR)**. The rate typed in the body of an offer letter is the reference rate; the rate on the repayment schedule is the reference rate plus the spread added to the prime rate (margin); the schedule rate is the contractual rate. FinES loans are always fixed; MAIIC's own loans are usually PLR-linked. | EM 24 Sep, confirmed on 5 of 6 letters in [Recon] | The two rates on the letters disagreed in 6 of 10 and this explains why |
| D13 | The **spread added to the prime rate (margin) is derived** by the engine as the loan-book rate minus the PLR in force at the month-end, and checked for constancy across months. A margin supplied by MAIIC is used as a cross-check, not as a dependency. | EM 24 Sep, on the 8-of-8 evidence in [Research] | Removes the engine's dependence on a field E-Banker does not hold |
| D14 | **Actual cash received comes from the monthly Loan Book Report** by default: this month's cash is the increase in the cumulative `Repayments` column, which ties exactly to the fall in `Outstanding Balance`. Extract B (the transaction ledger) is an optional second source. | EM 24 Sep | The loan book is already supplied monthly; Extract B is not |
| D15 | Where instalments are not level (arrears, moratoria, restructures), the EIR is solved by **internal rate of return on the actual expected cash flows**, never by a closed-form annuity formula. | [Teach], 55 basis-point error demonstrated | A level-annuity formula understates the EIR whenever the stream is not level |
| D16 | E-Banker's own **codes are stored verbatim** (`Interest Policy`, `Floating Flag`, `Loan Interest Cal. Base On`, `Installment Based On`, `Moratorium Type`, status codes) and the engine's internal categories are derived from them. Nothing is inferred from the `Floating Flag` alone. | [Research] s.6 | `RATE_BASIS` in Extract A turned out to be the Floating Flag, not the PLR link |

### 3.3 Governance, data and control

| # | Decision | By | Why |
|---|---|---|---|
| D17 | A **Governance Centre** holds every calculation convention as a setting. Each setting has options, a default equal to Dupleix's recommendation, an effective date, an approver, and an audit log of who changed what, from what, to what, and why. A change never silently restates a locked period. | EM 24 Sep | MAIIC staff operate the system themselves; the auditor has to see every convention and every change |
| D18 | Import is **four files**: A, the contract master (with the spread added to the prime rate (margin)); B, the transaction ledger (optional); C, the reference-rate series; D, repayment schedules as issued (optional). Plus the monthly Loan Book Report, which the ECL module already loads. Single and bulk import, with a column-mapping screen. | EM 24 Sep | Matches what MAIIC can produce today |
| D19 | The reference-rate series is loaded from the repaired file [PLR] with **ISO dates (`yyyy-mm-dd`)**. The importer **rejects** any date column that is mixed-type or ambiguous; it never guesses day against month. | [Research], 1,134 wrong dates found | The Excel locale problem must not reach the engine |
| D20 | The **acceptance test for interest** is the reconstruction in [Recon]: on a clean month the engine's contractual interest must equal E-Banker's posting to the cent; every difference must be explained by a named cause (late disbursement, catch-up posting, mid-month tranche). | [Recon] | This is what "reconcile within the agreed tolerance" in the contract will mean in practice |
| D21 | Every build on the branch starts and ends with the EIR test suite **green** on the in-memory test database, never against the live development database. Baseline confirmed on 24 Sep 2026: 124 tests, 512 assertions, all passing. | [Spec v2] s.12; repo check 24 Sep | A `RefreshDatabase` test once wiped the dev database; the baseline is now proven, so a regression will show |

### 3.4 Facts established with MAIIC and the vendor at the September walkthroughs

These were confirmed on 3 and 10 September 2026 and recorded in [Spec v2] s.3.6 and [Inventory]. They are treated as settled.

| # | Fact | Consequence for the engine |
|---|---|---|
| F1 | LOS holds the rate build-up, the fees and the moratorium configuration; on acceptance it creates the account in E-Banker, which owns disbursement, transactions, accrual and the amortisation table. The LOS application number and process reference are the only join, and they reach us in no extract. | Section 5.1 asks for both identifiers |
| F2 | There are two schedules. The LOS origination schedule is the one the customer signs; it cannot be downloaded after commit, is not visible to Credit, and prints only as PDF. The E-Banker EMI chart exports to CSV but omits the opening (disbursement) row; MAIIC has a change request open with the vendor to add it. | File D is optional (5.4); the engine generates version 1 from terms and reconciles to E-Banker (7.7) |
| F3 | The EMI chart marks a missed instalment as paid and keeps amortising. | The chart is evidence of resets, never an actuals feed; cash comes from the loan book (D14) |
| F4 | Migrated loans (written before E-Banker) were migrated at carrying amount only and have no origination schedule in either system; their pre-migration Excel schedules sit with Finance. | The take-on schedules of 31 Oct 2024 are the only full-life vector for that cohort |
| F5 | FinES is fixed at 10 percent for life; MAIIC Industrial and Agricultural float with the Reserve Bank rate, repriced whenever it changes (roughly every three to four months). E-Banker holds a table of interest rates by effective date that can be spooled. | File C (5.3) is that table; the repaired copy is [PLR] |
| F6 | Arrangement and legal fees are captured manually in E-Banker by Finance at disbursement, read off the offer letter, deducted from the amount advanced, and posted to GL 4873 (arrangement), 4871 (legal) and 4872 (consultancy). No screen or schedule carries the fee as a cash flow. | Fees need their own feed (O14); the initial net investment is drawn amount less fees |
| F7 | Only EMI repayment is in use; straight-line and bullet are configurable but not used. | The level-instalment generator covers the whole current book |
| F8 | A restructure increments the sub-account number on the same main account (1, 2, 3) and E-Banker can produce a per-account audit report. Extract A nonetheless shows sub-account 1 and a blank restructure date on every row. | Lineage exists at source and is lost on export; the ask is the Reschedule Report and the sub-account sequence |

## 4. What has not yet been agreed

Each item below is a real choice. The options are listed with Dupleix's recommendation first. None of them blocks the build of the engine's core, but each one changes a number somewhere, so each must be settled and recorded in the Governance Centre before the first locked run.

| # | Open choice | Options (recommendation first) | Who decides | What it depends on |
|---|---|---|---|---|
| O1 | **Margin derivation basis.** Where does the spread added to the prime rate (margin) come from when the loan-book rate and the LOS rate disagree? | (a) `LIVE_AT_DRAWDOWN`: loan-book rate minus the PLR in force on the disbursement date, then held constant; (b) `AS_CAPTURED`: LOS reference rate plus the margin as typed at origination; (c) `SUPPLIED`: a margin column from MAIIC | Dr Thom, after Barry answers the refresh-button question | Whether the LOS reference-rate field is a stamped snapshot (refreshed by a button) or a live link. Ebenezer Midian was approved on 8 Aug 2025 with 25.10 percent typed, but the PLR had been 25.30 percent since early July |
| O2 | **When a PLR change takes effect in the calculation.** | (a) From the Reserve Bank effective date, pro rata by days within the month; (b) from the next month-end; (c) from the next instalment date | Dr Thom | How E-Banker itself applies a mid-month change. The month-end rate moves with the PLR; whether the posting is split within the month is not yet proven |
| O3 | **Rate source precedence** when the sources disagree. | (a) E-Banker `Interest Policy` code, then observed behaviour, then product family; (b) observed behaviour first; (c) product family only | Dupleix, confirmed by Dr Thom | Whether MAIIC sends `Interest Policy` per account (new ask 1) |
| O4 | **Treatment of accounts with `Interest Policy = Manual`.** | (a) Read the rate month by month from the loan book and treat each change as a reset; (b) treat as fixed until told otherwise | Dr Thom | Which accounts are Manual (Audit Trail Report, Menu ID 692) |
| O5 | **`Repayments` counter resets to zero** (23 resets in nine months, up to MWK 677 million). | (a) Treat a reset as a discontinuity and take that month's cash from Extract B; (b) treat as a settlement and rebook; (c) ask MAIIC per case | Barry to explain the cause; Dr Thom to decide | What causes a reset: settlement, restructure or re-booking |
| O6 | **Interest basis on partly drawn facilities.** E-Banker can charge on the sanctioned amount or on the balance (E-Banker p.30), and the instalment likewise (p.22). On Lake Malawi Aquaculture the two bases differ by MWK 22 million a month. | (a) Read the flag per loan from E-Banker and follow it; (b) assume balance-wise everywhere and flag exceptions from the reconciliation | Barry to supply the flags | New ask 1 (the six scheme settings per account) |
| O7 | **Re-basing the LOS schedule.** [ExtB] shows the offer-letter schedules start accrual on the 1st of the month while E-Banker starts on the disbursement day, and two of four sample loans are charged a different rate in E-Banker from the letter (31 vs 25.1; 33 vs 25.3 percent). | (a) Generate the expected cash flows from the core disbursement date and the core rate, and keep the LOS schedule as a reference only; (b) use the LOS schedule as issued and book the difference as a modification | Dr Thom, with an explanation from Barry of why the core rate differs | Why Ebenezer Midian and JAT Group carry a different rate in the core system |
| O8 | **The fourteen-month gap** (Nov 2024 to Nov 2025): no core-banking loan book exists for these months. | (a) MAIIC re-runs the Loan Book Report for each month (it has an AsOn Date parameter); (b) use the GL-wise Interest Posted Report monthly from Oct 2024 for the interest side and Finance's Excel books for balances, disclosed as a limitation; (c) start the EIR history at Dec 2025 and disclose | Dr Thom | Whether the report can be run for past dates |
| O9 | **Integration versus CSV, permanently.** | (a) Extend the Loan Book Report (Menu ID 3868) with the missing fields so the monthly file is the integration; (b) a scheduled database view; (c) an API | Dr Thom (authorises the vendor change request) | Vendor estimate for the change |
| O10 | **Which GL account absorbs the EIR-versus-contractual true-up** (needed before the engine can propose journals). | (a) A dedicated "EIR adjustment" income account; (b) the existing interest-income account per product | Dr Thom | [Spec v2] open item 16 |
| O11 | **Reconciliation tolerance.** | (a) 100 basis points on the annual EIR and MWK 1 per account-month on interest; (b) an amount threshold agreed with Deloitte | Dr Thom and Deloitte | [Spec v2] open item 11 |
| O12 | **Deloitte export format.** | (a) The Summary-tab shape; (b) the full 23-tab worked example | Kundai, Dr Thom, Deloitte | [Spec v2] open item 17 |
| O13 | **Phase 0 sign-offs still pending:** conventions memo; keyman insurance as an integral fee or not; Nascomex preference-share classification; staging rebuttal. | Sign as drafted | Dr Thom | Unchanged since August |
| O14 | **Fee attribution per loan.** The GL proves the fees exist (accounts 4871, 4873) but the per-facility allocation is missing; the 2026 NAME column was hand-typed. | (a) Fees as a column set on the extended Loan Book Report; (b) a separate fee extract per posting with the account number; (c) an interim mapping table by Dupleix, disclosed | Barry | Request 4 of 11 Sep, still the largest gap |
| O15 | **Roles and permissions** for the EIR screens and the auditor downloads. | Reuse the ECL module's role set with three new permissions: view, run, export | Wadzanai, Kundai | [Spec v2] open item 12 |
| O16 | **Modification threshold.** | (a) 10 percent by analogy to B3.3.6; (b) a lower internal threshold | Dr Thom | Whether MAIIC wants a stricter policy than the standard |
| O17 | **Reset or modification?** MAIIC can vary the spread at its own option where the contract permits. A change linked to the Reserve Bank rate is a B5.4.5 reset; a negotiated rate cut for a struggling borrower can be argued as a 5.4.3 modification with a gain or loss. | (a) Rate moves within the contract are resets; anything negotiated outside the contract is a modification, stated in the accounting policy note; (b) treat every rate change as a reset | Dr Thom, with Deloitte's written confirmation before the first reset is booked | [Spec v2] open item 27 |
| O18 | **Maker-checker on schedule approval.** Version 1 schedules now move from draft to approved, but one person can do both. | (a) Require a second person, as for fee classification and the EIR lock; (b) leave as is for the first run | Dr Thom | [Spec v2] s.12 phase 3.5 |

## 5. The data the engine needs, file by file

The engine runs on five inputs. Four are files MAIIC sends (D18); the fifth, the monthly Loan Book Report, the ECL module already loads every month. Every file is loaded through the same column-mapping screen, so a column can be renamed at MAIIC's end without a code change at ours. Every date must be written year first (`2026-09-24`). A file with an ambiguous date column is rejected with a message naming the column and the first bad row (D19).

### 5.1 File A: the contract master (one row per loan account, mandatory)

This is Extract A as MAIIC already produces it (36 columns, 181 accounts) plus the fields the manuals show E-Banker holds but the extract does not carry. The plain-language reason for each addition is in [Workbook], sheet "Fields We Are Asking For".

| Field | Required | Format | What the engine does with it |
|---|---|---|---|
| `LOAN_ACCOUNT_NUMBER` + `SUB_ACCOUNT_NO` | yes | 15-digit account, integer sub-account | The key. A restructure creates a new sub-account; the pair identifies one contract version |
| `CUSTOMER_NAME`, `CUSTOMER_ID` | yes | text | Display and the join to the ECL client record |
| `PRODUCT_CODE` / loan type, `SCHEME_CODE` | yes | codes | Scheme carries the six settings below; product family is the fallback prior for rate behaviour |
| `INTEREST_POLICY` | yes (new) | one of N M L P S U R W F (E-Banker p.19) | `P` = reprice at every PLR change; `F` = never; `M` = read the rate from the loan book each month (O4) |
| `FLOATING_FLAG` | yes (new) | N F I B (p.19-20) | Stored for cross-check only; never used alone to decide repricing (D16) |
| `LOAN_INTEREST_CALC_BASE` | yes (new) | N S B (p.30) | `S` charge interest on the sanctioned amount; `B` on the balance (O6) |
| `INSTALLMENT_BASED_ON` | yes (new) | sanction / disbursement (p.22) | Which amount the instalment is sized on |
| `MORATORIUM_TYPE`, `MORATORIUM_MONTHS`, `GRACE_PERIOD_MONTHS` | yes | "Principle Only" / "Both"; integers | The shape and length of the moratorium, measured from the first disbursement (p.31) |
| `REPAYMENT_FREQUENCY`, `TYPE_OF_REPAYMENT`, `EMI_CALC_TYPE` | yes | monthly / quarterly / annual; EMI or equal principal; E P F (p.30) | Shape of the contractual schedule |
| `APPROVED_AMOUNT`, `DISBURSED_AMOUNT` | yes | MWK, 2 dp | Undrawn commitment = approved minus disbursed |
| `LOAN_START_DATE`, `INTEREST_START_DATE`, `FIRST_INSTALMENT_DATE`, `MATURITY_DATE` | yes | ISO dates | Schedule anchors. The core system starts interest on the disbursement day, not the 1st (O7) |
| `INTEREST_RATE` (contractual, all-in) | yes | percent, 2 dp | The rate E-Banker is charging today |
| `REFERENCE_RATE`, `SPREAD_OVER_PRIME` (margin) | preferred | percent | Cross-check against the derived spread (D13) |
| `ACCOUNT_STATUS` | yes | code | Exclude closed accounts; status codes H and F still to be explained |
| `LOS_APPLICATION_NO`, `LOS_PROCESS_REF` | preferred | text | The only link back to the offer letter |
| `RESTRUCTURE_DATE`, `PREDECESSOR_SUB_ACCOUNT` | when restructured | ISO date, integer | Lineage for modification accounting |
| `CURRENCY` | yes | MWK | Single currency today; stored for completeness |

### 5.2 File B: the transaction ledger (one row per posting, optional)

Extract B as produced, re-supplied with ISO dates and a debit/credit indicator (the posting screen on E-Banker p.58 has one; the extract does not). Used for three things only: to fill the months where the loan-book `Repayments` counter resets (O5); to date disbursements within the month; and to separate principal from interest in a repayment, which the extract currently estimates rather than stores. It is optional because D14 takes cash from the loan book.

### 5.3 File C: the reference-rate series (one row per rate change, mandatory)

| Field | Format | Rule |
|---|---|---|
| `INDEX` | text, `PLR` | One series per index; only PLR exists today |
| `EFFECTIVE_DATE` | ISO date | Strictly increasing within an index; the importer refuses a file with any break |
| `RATE` | percent, 2 dp | 12.00 to 25.40 observed 2020 to 2026 |
| `SOURCE_ROW`, `AS_DELIVERED`, `INTERPRETATION` | text | Audit trail: what the file said and how it was read. The repaired file [PLR] carries 48 rows, 20 of them with day and month swapped back, and 26 genuine rate changes |

### 5.4 File D: repayment schedules as issued (one row per instalment, optional)

The LOS schedule printed on the offer letter: due date, instalment, principal, interest, balance. Loaded as `schedule_version = 1` and kept unchanged for ever. If it is not supplied, the engine generates version 1 from File A (`schedule_source = GENERATED`) and says so. [ExtB] shows the issued schedule is not the cash flow the core account produces (O7), so even when supplied it is the reference, not the expectation. The walkthroughs (F2, F3) settled that the LOS schedule cannot be exported after commit and that the E-Banker EMI chart, which can, omits the opening row and marks missed instalments as paid.

### 5.5 The monthly Loan Book Report (Menu ID 3868, mandatory, already loaded)

29 columns today: account, customer, National ID, value and maturity dates, tenure with the moratorium tag, moratorium months, interest rate, approved, disbursed, not yet disbursed, principal, collateral, interest to date, repayments (cumulative), carrying amount, overdue split, four ageing buckets, outstanding balance. The engine reads from it, every month: the rate in force (for the spread and for Manual accounts); cash received (the increase in `Repayments`); arrears (the overdue columns and buckets); the undrawn commitment; and the stage (through the ECL module). [DateScan] confirms this family carries no corrupted dates: 2,018 dates checked, 0 wrong.

If Virtual Galaxy adds the fields in 5.1 to this report (O9, recommended), File A becomes unnecessary and the monthly file is the whole integration.

### 5.6 Standard E-Banker reports the engine can use as evidence

Not inputs to the calculation, but sources the engine's reconciliation and audit pack can cite: Audit Trail (Menu ID 692, the rate change history from the system of record); Reschedule Report (2839, the restructure population); IFRS9 Detail Report (3863, compounding frequency, instalment dates, the arrears split); GL-wise Interest Posted Report (interest per loan account per month, the source of Extract C); Loan Inquiry (542, the on-demand EMI chart for a single account). The four questions to ask about each are on [Deck] slide 12.

### 5.7 Validation rules applied on import

1. Dates: ISO only; a column that mixes text and dates, or where any value would parse both ways, is rejected.
2. Amounts: numeric, 2 decimal places, non-negative unless the row is a reversal.
3. Keys: every File B, C and D row must match a File A account, or it is quarantined and listed.
4. Rates: the derived spread over prime must be constant per account across months to within 0.15 percentage points; accounts that drift are quarantined for review, not silently accepted (about 12 of 35 in the first pass).
5. Sums: the principal in File D must equal `DISBURSED_AMOUNT` to within 1 percent.
6. Reference rates: strictly increasing effective dates; no duplicate dates.
7. Every rejection names the file, the column and the row. Nothing is guessed.

## 6. What already exists in the system, and what has to change

This section comes from a line-by-line inspection of the repository on 24 September 2026 [Analysis], made on the 20 August state of the branch (commit `a8c8926`) and then brought up to the branch head fetched the same day (commit `08485e2`, which adds 27 commits made between 19 August and 10 September). Every statement names the file that proves it.

### 6.1 The branch today, in plain terms

The August build put in place the bones of the engine and they work: files can be loaded through a mapping screen; fees can be classified as integral or not by one person and approved by another; an EIR can be solved and locked per contract; a monthly amortised-cost roll-forward can be run for a period; interest posted per account (Extract C) can be reconciled to EIR interest on screen; and the ECL module discounts at the solved EIR. The EIR and ECL test suites run and pass at the branch head: 170 tests, 24 skipped, 772 assertions.

**Added in September** (commits `ac030a6` to `af7c6e2`, 19 August to 2 September, plus the manuals of 10 to 13 September): a governed life for the version 1 schedule (a DRAFT generated from Extract A terms, compared with Extract B's remaining flows to within 1 percent, reviewed, APPROVED and then never regenerated: `ScheduleWorkflowService`, `EirScheduleController`, `ScheduleShow.vue`, `contract_remaining_cashflow_schedule`, readiness blocker `SCHEDULE_NOT_APPROVED`); a date-sensitive solver on actual contractual dates under the contract's day count (`DateSensitiveEirService`), with admin-controlled reopening archived to `eir_calculation_history`; the trial-balance corpus (`gl_trial_balance_lines`, `gl_account_scope`, `TrialBalanceImportService`, `TrialBalanceMovementService`, `eir:import-trial-balances`, cumulative-YTD rule applied on read); a three-term variance bridge whose GL accrual base is derived from the data rather than assumed; a standing fee rulebook (25 seeded rules, priority-ordered sweep); time-phased, scenario-weighted ECL discounted at the EIR (`EclDiscountingService`, `TimePhasedEclService`, Projections screen); and the user, administrator, technical and installation manuals under `docs/manuals`.

**Added on 25 September 2026** (phases P1 and P2 of the plan in 12.1, merged into the branch and pushed): the **Governance Centre** (`governance_settings` and `governance_setting_history`, `GovernanceService`, a screen, the twelve settings of section 8 seeded as approved defaults effective 1 January 2025, maker-checker on every change, the reconciliation band and the Stage 3 basis now read from it rather than from constants); the **reference-rate series** (`reference_rate_series`, an ISO-only importer that refuses an ambiguous or out-of-order file and names the row, `eir:import-reference-rates`, and a Reference Rates screen); the **verbatim E-Banker code columns** on `contract_eir` (Interest Policy, Floating Flag, calculation base, instalment basis, moratorium type and the rest of 6.3) loaded from Extract A, with `reprice_flag` derived from the policy and a note where the Floating Flag disagrees; **`SpreadDerivationService`** and `eir:derive-spreads`, which derive the spread added to the prime rate (margin) from the loan books and flag any account whose spread drifts; the `schemes` table (written in P4); **`loan_books.repayments` and `commitments` now populated** by the loan-book importer, which is what arrears will be read from; and the four EIR permissions. The suite after both merges: 233 passed, 24 skipped, 1,260 assertions.

**Added later on 25 September 2026** (phases P3 and P4, merged and pushed): the **contractual-interest service** of 7.7, which reproduces what the core system actually posts (prior month-end balance times rate times days over 365, the month of drawdown counted from the disbursement day inclusive, a capitalising moratorium compounding monthly), with the reconciliation rewritten onto it so that **every difference carries a named cause** rather than sitting in one unexplained total, and Excel and PDF downloads of that reconciliation; **both moratorium shapes** as E-Banker expresses them, with the grace period kept as its own field and a contract whose shape is unstated refused rather than guessed; **EMI calculation types** (level instalment and equal principal, with the third refused until an example exists); the **sanction-versus-balance basis** for both interest and the instalment, taken from the contract, then the scheme, then refused; **schedules generated on actual dates** rather than ordinal periods, which on a short first period is worth several percentage points of EIR; and **`contract_disbursements`** with its importer, its service and a Drawdowns screen reporting the undrawn commitment. The solver's day count is now read from the Governance Centre at origination instead of a basis written into the code. The suite stands at **306 passed, 24 skipped, 1,763 assertions**.

What the branch still does not do is process a **floating rate reset**, or account for a **restructure**. Those are the subjects of the phases that follow.

**Tables in place** (migrations `2026_07_27` to `2026_08_18`): `contract_eir` (59 attributes: identity, classification, pricing, dates, amounts, profile, results, solver audit, workflow, lineage), `contract_cashflow_schedule` (versioned, unique on contract + version + due date), `contract_fees` (signed lines, integral flag, maker-checker fields), `eir_amortisation` and `eir_amortisation_history`, `rate_reset_events` (present, never written or read), `eir_actual_transactions` (Extract B actual rows), `gl_interest_postings` (Extract C), `import_mappings`, `staging_thresholds`, `eir_accounting_rules`, `eir_fee_classification_events`.

**Services in place** (`app/Services/Eir/`): `CalculateEirService` (Newton-Raphson with bisection, ACADES golden test), `ScheduleGeneratorService` (annuity, one moratorium shape), `EirReadinessService`, `EirContractInputService`, `EirCalculationService` (orchestration and lock), `EirRevenueService` (monthly roll-forward, Stage 3 net basis), `EirGlReconciliationService`, `EirCoverageService`, the five import services (contract master, transactions, GL interest, schedules, fees), `FeeRuleMatcher`, `StagingClassifier`, `MappedFileReader`, and `EclDiscountRateService` for door 3.

**Screens in place** (`resources/js/Pages/Eir/`): Intake (upload, map, result), Calculations (solve, approve and lock), Data (contracts, cash flows, GL postings), Reconciliation (bridge tiles and per-facility table), Coverage (blockers by exposure), Fee Classification, Accounting Rules. Sidebar group "EIR and Revenue Recognition". The intake page is reachable only from a button on the Data page. Every EIR route sits behind the single broad permission `permission:settings`.

### 6.2 Capability map

| Capability the engine needs | Today | Proof | Change |
|---|---|---|---|
| Solve an EIR by IRR, with an audit trail | Exists, date-sensitive since September | `CalculateEirService.php`, `DateSensitiveEirService.php` | Carry the same date basis into the revenue roll-forward |
| Fees netted off proceeds, maker-checker | Exists | `EirContractInputService.php` line 80 | Respect `transaction_date` so a fee charged later in the life is not treated as at origination |
| Bulk import with column mapping and exception file | Exists | `MappedFileReader.php`, `ProcessEirImportJob.php` | Add the reference-rate, disbursement and restructure-schedule import types; ISO-only date rule |
| Monthly roll-forward with history, Stage 3 net basis | Exists, wrong convention | `EirRevenueService.php` lines 64-67 accrue (1+EIR)^(1/12)-1 per calendar month, no day count | Accrue at the EIR of the rate period in force, actual/365 (D9); look up the period's EIR from `eir_rate_periods` |
| Contractual interest as E-Banker posts it | Built (P3) | `ContractualInterestService` and `ContractualInterest`; the reconciliation's expected leg is built on it and every difference carries a named cause; Excel and PDF downloads | Extend `rateForPeriod()` when P5 brings resets |
| Floating loans and PLR resets | Rate series built (P2); the reset itself still missing | `reference_rate_series` + `ReferenceRateImportService` + `ReferenceRate::inForce` exist; `rate_reset_events` still has no writer; revenue accrues at the locked EIR for every period | Reset detector; `eir_rate_periods`; per-period solve (7.3), phase P5 |
| Interest Policy, scheme settings, interest basis | Built (P2) | Verbatim columns on `contract_eir`, loaded by `ContractMasterImportService`; `reprice_flag` derived from the policy; a note where the Floating Flag disagrees | Waiting on MAIIC to supply the codes (new ask 1); the `schemes` table is read in P4 |
| Spread added to the prime rate (margin) | Built (P2) | `SpreadDerivationService` + `eir:derive-spreads`; `spread_over_prime`, `spread_source`, `spread_drift_flag` on `contract_eir`; the supplied `markup` is compared, not relied on | Run it on the nine monthly loan books once the reference series is loaded |
| Moratorium "Principle Only" versus "Both", grace period | Built (P4) | The generator produces both shapes from `moratorium_type`, measured from the first disbursement; the grace period is carried separately; an unstated shape is refused | Waiting on MAIIC to supply the type per account |
| Interest on sanctioned amount versus balance | Built (P4) | The generator honours both flags, contract first then the scheme, then refuses; on one real facility the two bases are MWK 21,959,444 apart a month | Waiting on MAIIC to supply the flags |
| Partial disbursements, undrawn commitments | Built (P4) | `contract_disbursements`, its importer and command, `DisbursementService`, a Drawdowns screen; a marked later drawdown now enters the cash-flow vector; undrawn falls back to the loan book and says so, and is never reported as nil when unknown | Load the per-drawdown extract once MAIIC supplies it |
| Arrears from the loan book | Half built (P1) | `LoanBooksImport` now writes `repayments` and `commitments` | `LoanBookCashService` computes the monthly change and flags resets (D14, O5); B5.4.6 re-estimation in the roll-forward, phase P6 |
| Original schedule governance | Exists since September | `ScheduleWorkflowService.php`, `ScheduleShow.vue` | Maker-checker on approval (O18) |
| Trial-balance control totals | Exists since September | `TrialBalanceMovementService.php` | Wire into the month-end run as test T6 |
| Restructuring | Missing (version 1 is governed; version N+1 has no writer) | schedule version 2 has no writer (`ScheduleImportService.php` line 102 and `GenerateContractSchedules.php` line 108 hardcode 1); `modification_gain_loss` written as 0 (`EirRevenueService.php` line 81) | Version N+1 import; `contract_modifications` table; 10 percent test; lineage on sub-account (7.5) |
| Governance Centre | Built (P1) | `governance_settings`, `governance_setting_history`, `GovernanceService`, `Governance.vue`, twelve settings seeded; the reconciliation band and the Stage 3 basis read from it; a missing setting throws rather than defaulting silently | Point the remaining conventions at it as each phase touches them |
| Month-end run | Partial | `RunEirRevenueJob` runs a period for locked contracts; not scheduled, no screen, no period lock, no ordered pipeline; `RunEirRevenue.php` line 31 records a null user from the console | Ordered pipeline with a screen and a period lock (7.9); user recorded on every run |
| Exports | Missing | no download action on any EIR page (grep); the IFRS 9 hub has `Ifrs9ReportExport.php` | Excel and PDF for reconciliation, the revenue table and the audit pack, using the hub's export pattern |
| Single-contract view and edit | Missing | no route or form for `contract_eir` | Contract profile screen with rate periods, schedule versions, drawdowns and modifications |
| Permissions | Built (P1) | `eir.view`, `eir.run`, `eir.export`, `eir.govern` seeded; the read-only EIR screens and the Governance Centre now use them | Apply to the remaining routes as each phase touches them; note that the permissions seeder must run before anyone opens those screens |

### 6.3 Data model changes

New tables (all with `created_by`, timestamps, and an `audit_logs` entry on every write):

| Table | Grain | Columns | Keys |
|---|---|---|---|
| `reference_rate_series` | one row per rate change per index | `index_code` (PLR), `effective_date`, `rate` decimal(8,5), `source_row`, `as_delivered`, `interpretation`, `import_id` | unique (index_code, effective_date); effective dates strictly increasing, enforced on import |
| `schemes` | one row per E-Banker scheme | `scheme_code`, `product_code`, `interest_policy` char(1), `floating_flag` char(1), `interest_calc_base` char(1), `installment_based_on`, `emi_calc_type` char(1), `default_moratorium_type`, `effective_from` | unique (scheme_code, effective_from) |
| `eir_rate_periods` | one row per contract per rate period | `contract_id`, `sub_account_no`, `period_no`, `effective_from`, `effective_to` null, `reference_rate`, `spread_over_prime`, `contractual_rate`, `opening_amortised_cost`, `eir_period`, `eir_nominal_annual`, `eir_effective_annual`, `solver_method`, `solver_iterations`, `solver_residual`, `input_snapshot` json, `rate_reset_event_id`, `locked_at`, `locked_by` | unique (contract_id, sub_account_no, effective_from) |
| `contract_disbursements` | one row per drawdown | `contract_id`, `sub_account_no`, `tranche_no`, `disbursement_date`, `amount`, `reference`, `source_system`, `external_transaction_id` | unique (source_system, external_transaction_id) |
| `contract_modifications` | one row per restructure | `contract_id`, `sub_account_no`, `modification_date`, `old_schedule_version`, `new_schedule_version`, `carrying_amount_before`, `pv_new_flows_at_original_eir`, `change_pct`, `threshold_pct`, `treatment` enum(MODIFICATION, DERECOGNITION), `gain_loss`, `approved_by`, `approved_at` | unique (contract_id, new_schedule_version) |
| `eir_cash_receipts` | one row per contract per month | `contract_id`, `reporting_period`, `repayments_cumulative_open`, `repayments_cumulative_close`, `cash_received`, `source` enum(LOAN_BOOK_DELTA, EXTRACT_B, SCHEDULE), `reset_flag`, `reset_note` | unique (contract_id, reporting_period) |
| `governance_settings` | one row per setting | `key`, `value`, `options` json, `effective_from`, `set_by`, `approved_by`, `approved_at`, `reason` | unique (key, effective_from) |
| `governance_setting_history` | append-only | copy of the row plus `superseded_at`, `superseded_by` | index (key) |

Columns added to `contract_eir`: `scheme_code`, `interest_policy`, `floating_flag`, `interest_calc_base`, `installment_based_on`, `emi_calc_type`, `moratorium_type` enum(PRINCIPAL_ONLY, BOTH) stored beside the verbatim text, `grace_period_months`, `moratorium_from` date, `interest_start_date`, `first_instalment_date`, `spread_over_prime` decimal(8,5), `spread_source` enum(DERIVED, SUPPLIED), `spread_drift_flag`, `reprice_flag` (derived from `interest_policy`), `account_status_code`, `los_application_no`, `los_process_ref`, `predecessor_sub_account`. The existing `markup` keeps the value MAIIC supplies; `spread_over_prime` holds what the engine derived; the two are compared on screen.

Columns populated that exist but are empty: `loan_books.repayments` (the loan-book importer writes it from the report's Repayments column); `loan_books.commitments` (approved minus disbursed).

Tables used that exist but are idle: `rate_reset_events` (written by the reset detector, one row per contract per PLR change).

### 6.4 Repairs the inspection found

Items 2 and 5 were made in P1 on 25 September 2026 and are marked below. The rest stand.

1. Readiness is implemented twice (`EirReadinessService::assess` and `EirCoverageService::assess`; since September a test asserts the two agree) and reconciliation three times (the service, and raw SQL in `EirDataController` lines 68-115, MySQL-only). One implementation each; the controller calls the service.
2. **Done (P1).** `ContractMasterImport.php` mapped WEEKLY to 52 and FORTNIGHTLY to 26, which the readiness gate then rejected. They are now refused at import with a named reason. A numeric `payments_per_year` of 52 or 26 still reaches the gate.
3. The moratorium meaning conflicts between the import (principal grace) and the generator (full holiday with capitalisation). Resolved by `moratorium_type`.
4. `ScheduleImportService.php` line 55 refers to a restructure flow that does not exist. Built in 7.5.
5. **Done (P1).** `RunEirRevenue` now takes a required `--user=<id>`, checks the user exists, and refuses to run otherwise. `eir:import-reference-rates` follows the same rule.
6. The GL bridge assumed contractual/12 on the original drawn amount; September derives the base from the data. 7.7 completes it with the day-count rule and the disbursement-day start.
7. `docs/Development_of_EIR.md` says phases 4 to 7 are not started; they are. The log is brought up to date with this specification.
8. `storage/superseded-scratch-2026-08-04/` holds an abandoned schema. Deleted from the working tree (it is untracked) so no future `git add -A` picks it up.
9. `reporting_period` on `loan_books` is a free string read with `SUBSTR` and `REPLACE` tricks in three places. One helper normalises it.

## 7. How the numbers are worked out

This section explains each calculation the way it would be taught, using the figures from the teaching workbook [Teach] and from MAIIC's own data where a figure has been proven.

### 7.1 The contractual schedule

The engine first writes down what the borrower promised to pay, month by month, from the terms in File A. For a level-instalment (EMI) loan the instalment is the annuity payment on the drawn amount at the contractual rate over the remaining term. For an equal-principal loan the principal share is fixed and the interest share falls. The frequency can be monthly, quarterly or annual.

A moratorium changes the start of the schedule. "Principle Only" means the borrower pays interest but no principal for the moratorium months. "Both" means nothing is paid; the interest is added to the balance each month (D10) and the instalment is then sized on the enlarged balance. Both shapes are measured from the first disbursement date (E-Banker p.31). No third shape is built (D4).

Where E-Banker charges interest on the sanctioned amount rather than the balance (O6), the schedule follows that flag once MAIIC supplies it. On Lake Malawi Aquaculture (approved MWK 1,055 million, drawn 297 million, 34.75 percent) the two bases differ by MWK 22 million a month, so the flag is not optional.

### 7.2 The EIR at origination

The borrower receives the drawn amount less integral fees. In the teaching example a loan of 2,000,000 with 27,300 of arrangement and legal fees puts 1,972,700 in the borrower's hands. The EIR is the monthly rate that makes the present value of all the promised instalments equal to 1,972,700. The solver is Newton-Raphson with a bisection fallback, in payment-period units; the result is reported three ways and always labelled: the periodic rate (0.734967 percent a month in the example), the nominal annual rate (12 times that, 8.8196 percent) and the effective annual rate.

Which fees are integral is decided by the fee-classification rules already built (maker-checker, [Spec v2] phase 2.5). Only reviewed integral fees reach the solver.

### 7.3 Floating-rate resets (IFRS 9 B5.4.5)

When the Reserve Bank moves the prime rate, every loan with `Interest Policy = Link with PLR` reprices. The engine does three things at the effective date: it re-estimates the remaining cash flows at the new contractual rate (the new PLR plus the loan's unchanged spread over prime); it solves a fresh EIR on those flows against the carrying amount at that date; and it writes a new row in `eir_rate_periods`. There is no catch-up adjustment: the carrying amount does not jump. The previous EIR stays on record.

The spread over prime is derived (D13): the loan-book rate minus the PLR in force at that month-end. Over December 2025 to August 2026 the month-on-month change in rate on MAIIC Agricultural and Industrial loans equalled the PLR change exactly in every one of the eight months, across 17 to 37 accounts at a time, so the spread is constant per account and lands on clean values (4.90, 5.00, 6.00, 7.70 percentage points). FinES loans (0 of 74 moved while the PLR fell 4.5 points) and MAIIC Term Loans (0 of 12 moved) do not reprice. About 39 contracts do.

When a change falls inside a month, the timing rule is a governed setting (O2). The recommended rule is pro rata by days from the effective date, because that is how E-Banker accrues (7.7).

### 7.4 Arrears (IFRS 9 B5.4.6)

When an instalment is missed, the promised cash flows are no longer the expected ones. The engine keeps the EIR unchanged and recomputes the amortised cost as the present value of the **revised** expected flows at the **original** EIR; the difference goes to income in the month it is found. Interest keeps accruing at the EIR on the amortised cost, and the missed amounts stay in the expected flows until they are paid or written off.

The trap this engine is built to avoid: at the next reset date, if the cash-flow stream is no longer level (missed instalments now sit as a lump, or a moratorium has bent the stream), a closed-form annuity formula (Excel's RATE) gives the wrong answer. In [Teach] the loan misses instalments 10, 11 and 12 and cures at 15; at the third reset the true EIR by internal rate of return is 9.5502 percent nominal while RATE returns 9.0012 percent, an understatement of 55 basis points. The engine therefore always solves by IRR on the actual expected flows (D15).

Cash actually received is read from the loan book (D14): this month's cash equals this month's cumulative `Repayments` minus last month's, and that increase ties exactly to the fall in `Outstanding Balance` (Mach Milk: 41,750,000 to 45,750,000 to 48,750,000 to 51,250,000 to 53,650,000 across the months, each step matching the balance). Twenty-three accounts show the counter reset to zero in nine months; a reset is a discontinuity, never a negative payment, and is handled under O5.

Stage 3 accounts accrue on the net carrying amount (D11), with the stage read live from the ECL module.

### 7.5 Restructuring (IFRS 9 5.4.3 and B3.3.6)

A restructure gives the loan a new schedule. The engine loads it as `schedule_version` N+1; version 1 is never overwritten. It then runs the 10 percent test: the present value of the new cash flows at the original EIR, compared with the carrying amount before the change. If the difference is under 10 percent (O16), the loan is modified, not derecognised: the carrying amount is reset to that present value, the difference is a modification gain or loss, and the original EIR continues. In [Teach] the year-six restructure moves the carrying amount from 1,224,225.82 to 1,192,314.49, a 2.6 percent change, so it is a modification with a loss of 31,911.33; the revised EIR after the fee on modification is 8.0400 percent nominal.

E-Banker records a restructure as a new sub-account on the same account (vendor statement; the Reschedule Report, Menu ID 2839, lists them). The engine keys each contract on account plus sub-account and stores the predecessor so the lineage survives.

### 7.6 Partial disbursements and undrawn commitments

A facility can be drawn in tranches. Interest accrues on what has been drawn (or on the sanctioned amount, per O6), and the undrawn balance is a loan commitment reported separately under IFRS 9. At 31 August 2026 eight facilities carried MWK 3,472,435,397 undrawn, and the loan book's own `Not Yet Disbursed` column ties to approved minus disbursed to the kwacha. The engine needs one row per drawdown (date, amount, reference) in a small separate extract, because Extract A carries exactly one tranche per loan even where three were drawn.

### 7.7 The interest posting convention, proven against E-Banker

This is the finding that lets the engine reconcile to the general ledger. On a clean month, E-Banker's interest posting equals:

> **prior month-end outstanding balance x annual contractual rate x days in the month / 365**

JVD Agro, April 2026: posted 4,692,858.90; reconstructed 4,692,859.03. May 2026: 4,889,144.85 against 4,889,144.82. Malawi Police SACCO, May 2026: 85,386,066.75 against 85,386,066.84. Dividing the annual rate by 12 instead is wrong by 8 to 9 percent every February.

In the month of disbursement, interest runs from the disbursement day **inclusive** to the month-end: Ebenezer Midian, drawn 8 August 2025, posted 6,418,801.92 against 314,900,900 x 31 percent x 24/365 = 6,418,801.91. Microloan Foundation, drawn 18 December 2025, ties to the cent on 14 days. A capitalising moratorium compounds monthly: the balance rises each month by exactly the interest posted.

Where the reconstruction did not match, the reason was always one of three and each names a data item: a loan paid out late in the month (needs the disbursement date); a catch-up posting (Promenade Medical Centre: nothing posted in November or December 2025, four months posted at once in February 2026); a tranche drawn mid-month (Milele: the month-end balance cannot match without the per-drawdown extract). Across the ten offer letters, 32 of 47 account-months are within 2 percent and every clean month is exact [Recon].

### 7.8 Reconciliation to the ledger (door 2)

Every month the engine produces, per loan: contractual interest (7.7), EIR interest (the amortised cost times the EIR for the period), and the difference. The contractual figure is reconciled to Extract C (or the GL-wise Interest Posted Report) per account, and the sum by GL code and month is reconciled to the trial balance. The difference between EIR and contractual interest is the proposed true-up journal (O10). Fees and interest cannot be attributed to a loan from the GL spools alone ([Spec v2] limitation 9 and 10), which is why Extract C at full coverage and a fee extract by account remain requests.

### 7.9 The month-end run

1. Load the Loan Book Report (ECL module) and any new File A/B/C/D rows.
2. Apply validation (5.7); stop on any rejection.
3. Detect reference-rate changes since the last run; open new rate periods for the affected loans (7.3).
4. For each loan: update expected cash flows (arrears 7.4, restructures 7.5, drawdowns 7.6); roll the amortised cost forward one month; compute EIR interest and contractual interest.
5. Reconcile (7.8); produce the exceptions list with a named reason per exception.
6. Present the run for review; on approval, lock the period. A locked period is never restated by a later settings change (D17).

## 8. The Governance Centre

Every convention the engine uses is a setting, not a line of code. The screen shows each setting with its options, the option in force, the effective date, who approved it and the full history. The defaults are Dupleix's recommendations; MAIIC can change any of them with an approver's sign-off, and the change applies from its effective date forward only.

| Key | Setting, plainly | Options | Default (recommended) | Status |
|---|---|---|---|---|
| `plr_mid_period` | When a PLR change inside a month takes effect | pro rata from the effective date / next month-end / next instalment | pro rata from the effective date | O2, not yet agreed |
| `reset_trigger` | When the EIR is re-solved for a floating loan | at the PLR effective date / at the next instalment date / at month-end | at the PLR effective date | agreed (D8) |
| `moratorium_capitalisation` | How a "Both" moratorium compounds | monthly at posting / at the instalment frequency | monthly at posting | agreed (D10), proven |
| `rate_source_precedence` | Which source decides whether a loan reprices | Interest Policy, then observed behaviour, then product family / observed first / product family only | Interest Policy first | O3 |
| `margin_basis` | Where the spread added to the prime rate (margin) comes from | LIVE_AT_DRAWDOWN / AS_CAPTURED / SUPPLIED | LIVE_AT_DRAWDOWN | O1, depends on the refresh-button answer |
| `stage3_interest_basis` | Interest on Stage 3 loans | net carrying amount / gross with allowance unwind | net (5.4.1(b)) | agreed (D11) |
| `day_count` | Day count | ACT/365 / 30/360 | ACT/365 | agreed (D9), proven |
| `cash_source` | Where actual cash received comes from | loan book (change in Repayments) / Extract B / contractual schedule | loan book | agreed (D14) |
| `manual_policy_handling` | Loans with Interest Policy = Manual | read the rate monthly and treat each change as a reset / treat as fixed | read monthly | O4 |
| `modification_threshold` | The derecognition test | 10 percent / other | 10 percent | O16 |
| `recon_tolerance` | When a difference is an exception | 100 bp on the EIR and MWK 1 per account-month / amount threshold | 100 bp and MWK 1 | O11 |
| `counter_reset_handling` | A Repayments counter that falls | discontinuity, cash from Extract B / settlement / manual | discontinuity | O5 |

Each change writes an audit row: setting, old value, new value, who changed it, who approved it, when, why (free text), and the first period it applies to. A locked period keeps the settings it was locked under.

## 9. Screens and the monthly workflow

### 9.1 Screens to add

| Screen | Who uses it | What it shows and does |
|---|---|---|
| Governance Centre | Finance manager (govern), approver | Every setting in section 8 with its options, value in force, effective date, approver and history; propose a change with a reason; approve; a locked period shows the settings it was locked under |
| Reference Rates | Finance | The PLR series as a table and a step chart; import a new file; the 20 repaired rows with "what the file said, how we read it"; rejection messages for any ordering break |
| Contract Profile | Finance, auditor | One loan: terms as E-Banker holds them (codes verbatim, plain meaning beside each), the derived spread and the supplied spread side by side, drawdowns, schedule versions, rate periods with their EIRs, modifications, the amortisation roll-forward month by month, the interest reconciliation month by month with the named cause of each difference |
| Rate Resets | Finance | PLR changes detected since the last run, the loans affected, the new contractual rate per loan, the re-solved EIR, and the status (solved, blocked with reason) |
| Restructures | Finance (maker), approver | Load a version N+1 schedule for a loan; see the 10 percent test result; approve the modification; lineage to the predecessor sub-account |
| Drawdowns | Finance | Import per-drawdown rows; undrawn commitment per facility and in total |
| Month-end Run | Finance (run), approver (lock) | The pipeline of 7.9 as numbered steps with status, counts and an exceptions list per step; run; review; lock the period |
| Exports | Finance, auditor | Excel and PDF for the reconciliation, the revenue table (EIR interest, contractual interest, difference, by loan and by stage), the audit pack (settings in force, rate periods, exceptions with causes) |

Existing screens change as follows: Intake gains the three new import types and appears in the sidebar; Reconciliation gains the cause column and a download; Calculations shows the rate period being solved, not just the contract.

### 9.2 In-app help

Every new screen ships with its help-centre article and glossary entries in the same commit (`HelpContentSeeder.php`), written in the same plain language as this document. The user manual generator (`Ticket #010`) is re-run so the downloadable manual matches.

### 9.3 The monthly workflow, as the finance team will see it

1. **Load** the month's Loan Book Report (as today) and any new reference-rate, drawdown or restructure files. The Intake page shows what loaded, what was held and why.
2. **Check** the Rate Resets page: if the PLR moved, the affected loans are listed with their new rates and re-solved EIRs.
3. **Run** the Month-end Run: the pipeline executes; exceptions are listed with causes.
4. **Review** the Reconciliation: every loan where contractual interest differs from E-Banker's posting shows the cause (late disbursement, catch-up, tranche, data gap).
5. **Lock** the period with an approver's sign-off. Export the audit pack and the proposed journal.

## 10. Reconciliation, testing and acceptance

The contract makes acceptance turn on EIR calculations that "reconcile within the agreed tolerance". This section says what that will mean.

| Test | What passes | Evidence today |
|---|---|---|
| T1 Interest reconstruction | On a clean month, contractual interest equals E-Banker's posting to the cent for every account; every difference has a named cause | 32 of 47 account-months within 2 percent, clean months exact [Recon]; first-month accrual to the cent on 2 of 2 testable [ExtB] |
| T2 Offer-letter reproduction | The generated schedule reproduces the printed instalment on the offer letter | 5 of 6 where the two rates differ (Milele's annual structure still to model) |
| T3 Workbook integrity | The engine reproduces every figure in [Teach] (EIR at origination, at each reset, after arrears, after modification; total EIR income 1,049,024.58 against contractual 982,313.25; final carrying amount 0.00) | 28 of 28 checks pass in the workbook; engine fixtures to be written from it |
| T4 Spread constancy | The derived spread over prime is constant per account | 23 of 35 stable to within 0.15 pp in the first pass; the rest quarantined |
| T5 Cash tie-out | Change in Repayments equals the fall in Outstanding Balance | Exact on every non-reset month tested |
| T6 GL control total | Contractual interest summed by GL code and month equals the trial balance | Requires Extract C at full coverage (61 accounts missing) |
| T7 Test suite | The branch's PHPUnit suite is green on the in-memory database before and after the build | Green on 24 Sep 2026 at commit `08485e2`: 170 passed, 24 skipped, 772 assertions across the EIR and ECL suites |
| T8 Date integrity | No imported date is ambiguous | Importer rule (D19); [DateScan] on the loan books |

## 11. Data requests: where each one stands

The full request-by-request record, with the wording of each email and the reason behind it, is in [Workbook], sheet "Requests". In summary, of the fifteen items of 11 September: three are closed (2 loan books, 3 take-on schedules, 14 manuals); three are partly received (7 rate history, 8 extra fields, 12 restructured loans); nine are outstanding, of which fees per facility (4), LOS schedules after go-live (5) and the EMI chart with a drawdown row (6) are critical. Two outstanding items are now less critical because the engine derives the spread (D13) and takes cash from the loan book (D14).

Six new asks were added on 24 September: Interest Policy and the other five scheme settings per account; the three documented reports never requested (Audit Trail 692, Reschedule 2839, IFRS9 Detail 3863); ISO dates on every extract and re-supply of Extracts A and B; the IFRS9 Detail Report's full column list and a sample; confirmation of the go-live date and a re-run of the Loan Book Report for November 2024 to November 2025; and the change request to extend the Loan Book Report. [Deck] slides 10 to 16 carry them.

The register behind the 11 September email, field by field with the source screen, the target column and the reporting period, is Appendix E of [Spec v2]. The three sample packs it requests (ten LOS-originated loans, ten migrated loans, and every restructured loan, each with its documents, schedules and screenshots) remain outstanding and are the fastest route to T1 and T2 at full coverage.

## 12. Build plan and who does what

### 12.1 Phases, in order

| Phase | What is built | Depends on | Acceptance |
|---|---|---|---|
| P1 Foundations **(delivered 25 Sep 2026)** | Governance Centre tables, service and screen; the four permissions; repairs 6.4 items 2 and 5; `loan_books.repayments` and `commitments` populated | nothing | Met: 197 tests green at the merge, settings resolve by effective date, a missing setting fails closed, a change never restates an earlier period |
| P2 Reference rates and spread **(delivered 25 Sep 2026)** | `reference_rate_series` import (ISO-only, ordering check); `SpreadDerivationService` with the constancy check; `schemes` and the verbatim code columns; `reprice_flag`; Reference Rates screen | [PLR]; Interest Policy per account (new ask 1) or observed behaviour as fallback | Met in test: the repaired file loads as 48 rows, 26 rate changes, 4 Mar 2020 to 3 Sep 2026, current rate 21.20 percent; a dd/mm file and an out-of-order file are both refused. Still to run against the live loan books for T4 |
| P3 Contractual interest and reconciliation **(delivered 25 Sep 2026)** | `ContractualInterestService` (7.7); the reconciliation rewritten on it with eight named causes; Excel and PDF downloads; the duplicated reconciliation SQL removed | P2 | Met: all seven proven figures reproduce, five within 0.13 and the two first-disbursement months exactly; rate over 12 is shown to overstate February by 8.63 percent |
| P4 Schedules **(delivered 25 Sep 2026)** | Generator: both moratorium shapes, grace period, EMI calculation types, sanction versus balance basis, dated periods; `contract_disbursements` with its importer, service and screen | P2; the six scheme settings per account | Met in test: both shapes, both bases (21,959,444 apart a month on Lake Malawi), a dated 16-day first period at 34.84 percent against 31.47 percent ordinal, and the eight facilities totalling MWK 3,472,435,397 undrawn |
| P5 Floating resets | The Phase 5.1 order in [Spec v2] s.7.5, extended: readiness blocker `FLOATING_TERMS_MISSING`; reset detector from `reference_rate_series` writing `rate_reset_events`; reset intake with maker-checker; a reset inside a locked period refused unless the period is superseded with a reason; `eir_rate_periods` holding the spread-lock EIR and the re-solved EIR; revenue roll-forward reads the period's EIR and accrues actual/365; ECL discounts floating loans at the current EIR | P2, P3, P4; O17 confirmed by Deloitte before the first reset is booked | T3 reset figures from [Teach]; spread-lock equals re-solve within tolerance; a reset changes no prior period; every PLR-linked loan has one rate period per PLR change since its start date |
| P6 Arrears | `eir_cash_receipts` from the loan book; reset handling; B5.4.6 re-estimation in the roll-forward; IRR on actual expected flows | P4, P5 | T5 cash tie-out; T3 arrears figures (the 55 basis-point case) |
| P7 Restructuring | Version N+1 import; `contract_modifications`; 10 percent test; lineage | P6 | T3 modification figures (2.6 percent change, loss of 31,911.33) |
| P8 Month-end run and screens | Pipeline, period lock, Contract Profile, Rate Resets, Restructures, Drawdowns, Month-end Run, help articles, manual regenerated | P1 to P7 | A full month runs end to end on the Dec 2025 to Aug 2026 loan books with every exception explained |
| P9 Acceptance and UAT | T1 to T8 on MAIIC's data; UAT with the finance team | MAIIC data (section 11) | Contract milestone 3 |
| P10 Deployment and training | Install in MAIIC's environment; training; manuals; source code handover | P9 | Contract milestone 4, Go-Live, 90-day warranty |

### 12.2 What each phase needs from MAIIC

| Phase | Needs | Status |
|---|---|---|
| P2 | The repaired PLR file confirmed (20 rows); Interest Policy and Floating Flag per account or the scheme code | File ready; confirmation and the codes outstanding |
| P3 | Extract C for all accounts to the latest month; disbursement dates | 61 accounts missing; dates in Extract B need ISO re-supply |
| P4 | The six scheme settings per account; per-drawdown extract; File D where it exists | Outstanding |
| P6 | Loan Book Reports monthly (have Dec 2025 to Aug 2026); explanation of counter resets | Nine months held; resets unexplained |
| P7 | Reschedule Report; restructured loan pack | Four in the take-on workbook; the report never run |
| P9 | Fees per facility; the fourteen-month gap decision (O8) | Outstanding; the largest remaining gaps |

### 12.3 Who does what

| Party | Responsibility |
|---|---|
| Dupleix, Kundai Muriwo | Builds P1 to P8 on branch `eir_revenue_recognition`, tests green at every commit, help articles in the same commit, commits only the files each change touches |
| Dupleix, Edward Mazibuko | Signs off each phase against the acceptance column; owns the conventions and this specification; leads the MAIIC meetings |
| MAIIC, Barry Makumba | Supplies the data in 12.2 as CSV with ISO dates; runs the standard reports; raises the Loan Book Report change request with Virtual Galaxy |
| MAIIC, Dr Thomson Kumwenda | Decides the open choices in section 4; approves the Governance Centre defaults; authorises the vendor change request; sponsors UAT |
| MAIIC, Finance team | Operates the monthly workflow from P8; UAT within five business days of notice (contract) |
| Virtual Galaxy | Prices and delivers the Loan Book Report extension (O9); answers how the rate file and the extracts were produced |

### 12.4 The immediate build order

P1 to P4 were built on 25 September 2026 and are merged into `eir_revenue_recognition`, because none of them waited on MAIIC. The next session starts at **P5**, the floating-rate resets, which is the last piece of the calculation and the one the reference-rate series in P2 was built to feed. It needs one answer from MAIIC first, O17: whether a rate change MAIIC makes at its own option is a reset or a modification, confirmed in writing by Deloitte before the first reset is booked. **P6** (arrears from the loan book) and **P7** (restructuring) follow.

Three housekeeping items carry forward. Run `eir:import-reference-rates` and `eir:derive-spreads` against the live loan books to complete test T4. Run the permissions and governance seeders on any environment before the EIR screens are opened, or they return 403. And run `npm run build` where that is allowed, because four new Vue pages have been added and not yet compiled. Nothing waits idle on a data request.

## 13. Risks and limitations

| # | Risk or limitation | Effect | Mitigation |
|---|---|---|---|
| R1 | The Excel date problem recurs on a future extract | Schedules shift by months; cash lands in the wrong period | ISO-only importer (D19); ask MAIIC never to re-save through Excel |
| R2 | Interest Policy is not supplied | Repricing decided from observed behaviour and product family | O3 precedence; the Audit Trail Report as a fallback source |
| R3 | The fourteen-month gap is not filled | EIR history for legacy loans starts at December 2025 or rests on Finance's Excel books | O8; disclose as a limitation in the audit pack |
| R4 | The LOS schedule differs from the core cash flow (start date, rate) | Wrong expected flows if the schedule is trusted | O7; generate from core terms; keep the schedule as reference |
| R5 | Tranche history and restructure lineage are lost on export | Interest on partly drawn loans cannot reconcile; modification accounting lacks its "before" | Per-drawdown extract; key on account plus sub-account |
| R6 | Fees cannot be attributed per loan from the GL | Integral fees missing from the EIR for most loans | O14; interim mapping table disclosed |
| R7 | Interest Policy = Manual accounts | No rule to follow | O4; read monthly from the loan book |
| R8 | Repayments counter resets | Cash for that month unknown | O5; Extract B for those months |
| R9 | Test suite not green | Regressions go unnoticed | D21 before any new build |
| R10 | Governance settings changed without approval | Locked periods restated | Approver role; effective dating; audit log (D17) |
| R11 | Real customer names and balances in the extracts | Confidentiality | Same care as all client data; never reused in training material |

## 14. Manual pages and files cited

| Item | Reference |
|---|---|
| Interest Policy (nine options) | E-Banker p.19 and p.27 |
| Floating Flag (four options; Fixed is "Applicable for Time Deposit Account") | E-Banker pp.19-20 and p.28 |
| Installment Based On | E-Banker p.22 |
| Margin Period Required Flag (a grace concept, not an interest margin) | E-Banker pp.23, 31 |
| Loan Interest Cal. Base On (N, S, B); EMI Calc Type (E, P, F) | E-Banker p.30 |
| Moratorium Period, from the date of first disbursement | E-Banker p.31 |
| Sanction types (Primary Appraisals Sanction, Enhancement of Limit, and others) | E-Banker p.12 |
| Transaction Posting and the debit/credit indicator | E-Banker pp.58-60 |
| Section 5 "MAIIC Report": Audit Trail 692, Reschedule 2839, IFRS9 Detail 3863, Loan Book 3868 | E-Banker pp.75-77 |
| GL-wise Interest Posted Report figure (a production run for 1 to 31 October 2024) | E-Banker p.76 |
| Moratorium Type: "Principle Only" and "Both (Interest + Principle)" | LOS p.76 |
| Grace Period (In Month) | LOS p.77 |
| Margin % on the collateral screen (collateral haircut) | LOS p.63 |
| Funding Proposal: Interest Rate %, Margin %, Effective Rate %, Rate Type | LOS p.74 |
| Repaired reference-rate series | [PLR] |
| Reconstruction and Extract B check | [Recon], [ExtB] |
| Loan-book date scan | [DateScan] |
| Teaching workbook | [Teach] |

## 15. Glossary

| Term | Plain meaning |
|---|---|
| Amortised cost | What a loan is carried at: the amount advanced, plus interest recognised, minus cash received, minus any impairment. |
| Contractual rate | The all-in rate on the repayment schedule: the reference rate plus the spread added to the prime rate (margin). |
| EIR, effective interest rate | The rate that exactly discounts the expected cash flows to the amount actually advanced net of integral fees. Slightly above the contractual rate whenever fees were deducted. |
| E-Banker | MAIIC's core banking system, by Virtual Galaxy. Holds the loan accounts and posts interest. |
| Extract A, B, C | The three hand-written queries MAIIC ran from E-Banker in August 2025: A is the loan account register at two dates; B is every posting to every loan in 2025; C is interest posted per loan per month. |
| Floating Flag | An E-Banker field (N, F, I, B) that says whether a rate may vary. Not the field that links a loan to the PLR. |
| Governance Centre | The screen where every calculation convention is set, approved, dated and logged. |
| Integral fee | A fee that is part of the cost of the loan (arrangement, legal) and so is netted off the amount advanced when solving the EIR. |
| Interest Policy | The E-Banker field (N, M, L, P, S, U, R, W, F) that says where a loan's rate comes from. P = Link with PLR; F = Fixed; M = Manual. |
| IRR, internal rate of return | The discount rate that makes the present value of a set of cash flows equal to a given amount. The EIR is an IRR. |
| ISO date | A date written year-month-day, `2026-09-24`. It cannot be misread under any country setting. |
| Loan Book Report | E-Banker Menu ID 3868, the monthly 29-column report of every loan account. A custom report Virtual Galaxy built for MAIIC. |
| LOS | The Loan Origination System, where applications and offer letters are prepared before the account is created in E-Banker. |
| Menu ID | The number that identifies a screen or report in E-Banker. |
| Modification | A change to a loan's terms that is not large enough to count as a new loan; the original EIR continues and a gain or loss is booked. |
| Moratorium | A period at the start of a loan when principal (Principle Only) or both principal and interest (Both) are not paid. |
| PLR | The Reserve Bank of Malawi's prime lending rate, the reference rate for MAIIC's floating loans. 26 changes since March 2020. |
| Rate period | The stretch between two reference-rate changes during which one EIR applies to a floating loan. |
| Reference rate | The PLR. The rate typed in the body of an offer letter. |
| Reset | The date a floating loan's rate changes because the reference rate changed. |
| Sanction, sanctioned amount | The approved facility amount, which may be more than what has been paid out. |
| Scheme | E-Banker's level between product and account. The six settings that decide rate behaviour and interest basis live here. |
| Spread added to the prime rate (margin) | The fixed percentage MAIIC adds to the PLR to arrive at the contractual rate. Constant per loan. Never called just "margin" in this document. |
| Stage 1, 2, 3 | The IFRS 9 credit-risk stages: performing; significantly deteriorated (30 days past due backstop); credit-impaired (90 days backstop). |
| Sub-account | E-Banker's numbering of a restructured loan under the same account number. |
| Tranche | One drawdown of a facility that is paid out in parts. |
| Undrawn commitment | The approved amount not yet paid out. Reported separately under IFRS 9. |
