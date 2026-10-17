# MAIIC Client Book Reconciliation Specification v1

17 October 2026. Dupleix Institute for MAIIC. Status: built (branch `client-book-reconciliation`).

## 1. Purpose

The system's loan book for a month is rebuilt from the E-Banker query outputs and the ledger (method B). That is the book the engines read. MAIIC also holds its own loan books for the same month-ends: the monthly E-Banker loan book report printed by E-Banker, and for December 2025 the audited ECL model. Where the two differ, the client must be able to see each difference and, where its own figure is the right one, take it, without overwriting what the system rebuilt and without anyone deciding alone.

Before this build, the comparison existed only for December 2025, on the command line (`staging:december-check`). It wrote a CSV nobody could act on from the screen. The overrides it led to (36 staging day counts and MUSECO's gross amount, 17 October 2026) were entered by scripts typed for that month (`apply_staging_overrides.php`, `apply_gross_amount_overrides.php`), and no screen showed or decided them.

This specification covers:

1. a register of the client's books, for any month;
2. a loan-by-loan comparison with a CSV of the differences;
3. a decision per difference: override, or keep the system's figure;
4. the overrides governed on a screen, with evidence and two people;
5. the bootstrap landing and comparing every client book it holds;
6. every PD segmentation basis measured each month beside the booked one (section 9).

## 2. Principles

- The rebuilt book stays the system's book. A client figure applies only through an approved override, only for its loan and month, and only when the month is rebuilt. The system's own figure stays on the loan beside it (`build_flag`).
- Nothing is invented. A day count is the one the client's book gives, read by a stated rule (section 3). Where the book gives none, the person proposing must enter one and say why.
- Fail closed. A report whose rows do not add up to its own printed totals is refused whole. A locked month is never overridden.
- Two people. The proposer of an override cannot approve it. An approval needs a document: the client's book is linked automatically when the override comes from a reconciliation.

## 3. The client's books and how they are read

| Kind | What it is | How a row is read |
|---|---|---|
| `ebanker_report` | MAIIC's monthly rpt_Loan_Book_Detail_Report (.xls, .xlsx or .csv) as E-Banker prints it, one row per account under "Loan Type : GL-name" | Columns by their header; account number without leading zeros; carrying amount; overdue principal and interest; the four arrears buckets. **Control:** each GL's rows must equal the report's "GL Wise Total" for that GL. The "Printed Grand Total" line repeats the last GL's carrying amount in that column (an E-Banker quirk), so it is not the control. **Day count:** the lower bound of the oldest bucket with an amount (1, 31, 91 or 181). The buckets end at 270 days, so overdue that no bucket holds is older than that and is read as 271 days, never as current. |
| `audited_model` | The values of the December 2025 ECL model's portfolio report (`docs/bootstrap/models/ECL model 14 Dec 2025 - portfolio report (values).csv`) | Customer name, carrying amount, the revised age bracket at its lower bound (0, 31, 91 or 181 days), and the stage the model gave |
| `template` | The reconciliation template the screen exports for a month, backfilled with the system's carrying amount, day count and stage | The client's columns only. A row left blank takes no position. |

**Matching:**
- The E-Banker report and the template match by account number.
- The audited model masks the accounts. It matches by a balance equal to the ngwee and unique on both sides, else by the normalised customer name and the nearest balance (the December check's rule).

**The file itself:** it is kept as received, with its SHA-256, as the supporting document of the book.

## 4. The comparison

The month's loan book (the loan GLs) is staged under the policy in force (`StagingService::evaluate`). Each loan is then compared on three fields:

- **balance:** differs if by more than K1,000 and half a percent;
- **arrears bracket:** the directive's brackets, 0 to 30 days (current included), 31 to 90, 91 to 180, over 180;
- **stage:** where the client gives one.

Causes, in order:

| Cause | Meaning |
|---|---|
| in the client's book only | No loan of the system's book matches. A book closed at nil in both is not a difference. |
| in the system's book only | Not for the template, which lists only the loans the client takes a position on |
| excluded account | For information: an account on the exclusion register |
| different arrears bracket | |
| different balance | |
| same bracket and balance, different stage | |
| agrees after the approved override | Reconciled |

- Every difference goes on the register (`client_book_differences`) and into `storage/app/client-book-reconciliation/{month} - {book} (book n) differences.csv`.
- A comparison run again keeps each decision.

## 5. Deciding a difference

**Propose the client's figure.** This creates:
- a staging basis override (source `client_book`, the client's day count), where the bracket or stage differs;
- a gross amount override (the client's carrying amount), where the balance differs.

Each override carries the reason, names the client's book, and links it as evidence. A person may enter a better-evidenced day count than the bracket's lower bound, and says why.

**Keep the system's figure.** This needs a reason, and can be opened again with a reason.

**Refused:**
- a locked month;
- a difference that matches no loan of the system's book (the client answers it);
- a reason under 10 characters.

## 6. The overrides

The overrides are approval workflows (`staging-override`, `gross-override`):
- they are on the approvals register;
- reject and withdraw follow the house rules: the proposer withdraws, a second person rejects;
- withdrawing an approved override needs a reason, and the month's next rebuild stops applying it.

**Approval needs a supporting document.**

**When an override takes effect:** only at the month's rebuild.
- The screen lists "Rebuilds due": each month with an approved override its book has not been rebuilt with since.
- It proposes the build from there; a second person approves it on Loan Book Builds.
- Then the month-end is run, and the book is compared again.

**What an override changes:**
- a **staging override** replaces the day count staging reads, for that loan and month only. The arrears buckets and the directive's overdue days stay as rebuilt.
- a **gross amount override** replaces the carrying amount the ECL reads.

`LoanBookBuildService` now applies an explicit day count for both explicit sources (`audited_workbook`, `client_book`) and names which one on the loan.

## 6a. The stage override

For a month the client does not stage on E-Banker's ageing, a governed override sets the loan's stage itself (`loan_stage_overrides`, workflow `stage-override`), proposed and approved by two people with a supporting document, as the other two. `StagingService` applies an approved one last, after the day count, the missed-instalment trigger, the qualitative triggers and the cure rule, records the reason `override` and keeps the stage the policy gave beside it. It needs the month staged again (its month-end), not rebuilt, and touches no other loan or month. Proposing a difference takes the client's stage as a stage override where its book gives a stage that differs, else its day count.

## 6b. The override schedule

The client downloads the month's template (every loan with the system's carrying amount, day count and stage), fills in its own stage, day count or carrying amount where it takes another, and uploads it on the Overrides tab. Each filled cell is proposed as its override with the file as its evidence: a stage as a stage override, a day count with no stage as a day-count override, a carrying amount as a gross amount override. A figure equal to the system's, a loan not in the month's book and a loan already overridden for the month are listed and not proposed. A different person approves the schedule as a whole (Schedules awaiting approval) or each override on its own. The same routine runs from the command line (`eir:client-book schedule`) and in the bootstrap: step 5a, straight after the build, applies every schedule of `docs/bootstrap/override_schedules/manifest.json` under the bootstrap label and builds its month again, so a clean install reproduces the client's overrides from the file itself. The committed December 2025 schedule (`2025-12 Override schedule - audited December 2025 model.csv`) carries the 36 audited day counts and MUSECO at nil, the decisions of 17 October 2026; the system's own carrying amount and day count stand beside each in the file.

## 7. The screen

**Customer & Loan Data, Client Book Reconciliation** has four tabs:
- **Client books:** land, compare, the file, the CSV, the template, withdraw.
- **Differences:** by book and status; propose one or many, keep, open again.
- **Overrides:** rebuilds due; the register; approve, reject, withdraw; propose directly.
- **Finance overlays:** import and list. An overlay records Finance's figure beside the system's and changes nothing.

**Permissions:** viewing needs `eir.view`; every action needs `eir.govern`.

**Command line:** `php artisan eir:client-book land|compare|manifest|list`.

## 8. The bootstrap

`docs/bootstrap/client_books/manifest.json` lists 22 books, each with its SHA-256:
- MAIIC's E-Banker reports of December 2024 to August 2026 (21 files, as received);
- the audited December 2025 model.

**Left out:** "Nov 2024 loan book .csv" from the 2025 engagement, a prepared listing with masked account numbers, not an E-Banker report.

**Step 6.13** lands and compares each book whose month the bootstrap built, and writes the CSVs. The golden values add:
- the books compared;
- the differences on the register;
- for each golden month's book: the loans matched, the same and the differences.

**Results on a copy of the current build (`maiic_ifrs9_fees`):**
- Balances: in every month the rebuilt balances agree with MAIIC's E-Banker report.
- Arrears brackets: these are the differences, 13 to 52 loans a month. E-Banker ages the arrears amount by bucket; the system counts from the oldest unpaid instalment (the directive, `dpd_basis`). Those are the differences to decide.
- The audited December 2025 model: 107 facilities matched, 64 the same after MUSECO's overrides. The rest:

  | Difference | Loans |
  |---|---|
  | Different arrears bracket | 38 |
  | Different stage under the policy in force | 5 |
  | In the system's book only | 9 |
  | In the model only | 1 |

**Example:** MUSECO's two overrides were proposed and approved on the screen, the month was rebuilt from "Rebuilds due", and it now shows as reconciled against the model. Against MAIIC's own December E-Banker report (138,135,680.80) it now shows as a difference. The two client books disagree with each other, and the screen shows both.

## 9. Every PD basis, every month

The PD engine books one basis, governed on the Governance Centre (`pd_segmentation_basis`). Since 17 October 2026 the seeded default is "Pooled book"; databases set up before then keep "By portfolio" until it is changed there.

**What changed:** `PdSegmentationService::compareBases()` now measures all four bases after each booked run, on the same loans, window, minimum and thin rule:
- pooled book;
- by portfolio;
- by RBM sector;
- by portfolio and RBM sector.

**How each basis is resolved and stored:**
- each stage is resolved as the booked run would be: its own PD, the parent's under the thin rule, or no PD;
- each basis is kept as a run with status `comparison`;
- no matrix is written, no loan carries it, and every reader of the booked run reads status `applied`;
- a month measured again replaces its earlier comparison.

**Where it runs:** after the booked run in `ifrs9:segment-parameters` (unless `--no-compare`), in the month-end, and in the bootstrap's PD step, at about 4 seconds a month.

**The screen:** IFRS 9 Model Setup, PD Model, PD Bases Compared shows:
- every basis by segment and stage;
- the cumulative PD of the booked segments over one to five years at a constant hazard, 1 - (1 - PD)^t, as the booked ECL reads it;
- the pooled book's Stage 1 and 2 PD and LGD for every month.

**What this changes:** before this build, the other cuts were worked out live on Reports > ECL by segment and never stored. Now each month keeps the record behind the basis chosen. A basis can be switched on the Governance Centre when a segment has the history, with the evidence of every month in hand. The December 2025 model's own method stays the S&P basis comparison (Internal Grades, S&P basis).

## 10. Also in this build

**Staging speed.** `StagingService::evaluate` read the staging threshold, and the table's columns, once per loan: 7,728 queries for one month. It now reads them once per class, tenor and month-end within an evaluation, and the cache is cleared at each evaluation. That is 100 queries, and 55 seconds becomes 10.5. The figures are unchanged. This speeds every `eir:stage`, the month-end and the bootstrap.

## 11. Open items

1. **Root scripts.** `apply_staging_overrides.php` and `apply_gross_amount_overrides.php` approve without a document. They are superseded by the screen: the 36 December 2025 day counts are now proposed from the audited model's differences on the screen, with the model as the evidence. Under this build the scripts' approvals are refused for want of a document.
2. **Different-bracket decisions.** The arrears-bracket differences against MAIIC's E-Banker reports are a basis difference (bucket against oldest instalment). They are for MAIIC Finance and Dr Thom to decide month by month, or as a policy: `dpd_basis` on the Governance Centre.
3. **Sector PDs.** These stay unreliable until Barry supplies the real codes for the 57 loans on E-Banker's default code 4290.
