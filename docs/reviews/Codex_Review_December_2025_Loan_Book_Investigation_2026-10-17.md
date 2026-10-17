# Independent review: December 2025 loan book investigation

Date: 17 October 2026  
Reviewer: Codex  
Database examined: `maiic_ifrs9_bootstrap` (local MariaDB 10.4.32)  
Code revision examined: `fc67d4725cbf14d01e30c91e46baea2dfda453f4`

## Conclusion

**The proposed ledger-and-schedule investigation finds a real, partial lead. The claim that the remaining 26 are all clean and cannot be investigated further from the extracts is not supported.**

At 31 December 2025, allocating recognized ledger repayments against the instalment chart in force produces the workbook's bracket for **5 of the 26** facilities. Three of those five still owe a balance after maturity. Retaining the existing maturity floor leaves **2 bracket matches**, BenCal and JAT. Of the other facilities, **18 still disagree on the chart-only calculation and 3 have no chart in force available to the current chart reader**. Those three are unknown on this calculation, not zero days overdue.

This is a diagnostic comparison, not approval to replace staging inputs. Chart selection follows `InstalmentChartHistory`, including its existing corrections and dated-copy assumptions. FIFO application of prepayments is a stated assumption; a matching bracket does not prove the workbook used that method, nor prove a residual balance should be disregarded.

No database data, policy, baseline or application source was changed. Database work used SELECT/schema inspection and read-only service paths, with `SET SESSION TRANSACTION READ ONLY`. Findings below distinguish the stored baseline, a fresh in-memory method-B calculation, and a new diagnostic reconstruction.

## 1. What I independently confirmed

I read the point-in-time specification, the investigation findings, and the assistant background note in the requested order, then inspected the database directly.

- The application's configured database is `maiic_ifrs9`. It has `loan_books` but no `ebanker_raw_rows`. `maiic_ifrs9_bootstrap` has both. This was verified through `information_schema.tables`, not assumed from the note.
- `DESCRIBE` and real rows show that `ebanker_raw_rows` contains `load_id`, `query_id`, `source_key`, padded `account`, `row_date`, `payload`, `version` and `superseded_at`. The payload is physically `longtext` containing JSON. Report payloads use `ASONDATE`, `TRANSACTION_DATE`, `CARRYING_AMOUNT`, `ARREAS_TOTAL`, `OVERDUE_PRINCI_DATE` and the five `DAY_*` fields. Ledger payloads use signed `TRANSAMT`, `TRANTYPE`, `CUMVOUCH_DET_ID`, loan GL fields and `DELETE_FLAG`.
- `loan_books` has separate `overdue_days`, `staging_dpd`, `overdue_principal_date`, `build_basis` and five arrears columns. The database has a unique index on `(contract_id, reporting_period)`. I inspected the actual December Infraworks row and its ledger/build provenance.
- There are **795,100 raw rows**. The built book currently contains **83,248 rows**, spanning **2024-07 through 2026-09**. These are counts in today's database; I did not reproduce the earlier all-month rebuild's 393 changed rows or 73 flagged rows.
- The existing comparison command reproduces **108 workbook facilities, 107 matched, 71 same stages and 36 different stages**. Its cause table has **39 different arrears brackets**, because three bracket differences do not change stage. The documents should distinguish stage and bracket counts.
- Infraworks' January/September re-pull drift reproduces: source `108431` dated 16 January 2026 has zero in `DAY_1_30` and 105,873,319.52 in `DAY_271_360`; source `787121` dated 11 September has 11,326,727.74 and 33,980,183.22 respectively. Both retain total arrears 105,873,319.52 and overdue date 29 March 2025. The current selector chooses source `36926`, dated **1 January 2026**, earlier than the illustrative January 16 pull.
- There are 123 in-scope MAIIC/FInES accounts with December report rows, each with 7–30 distinct source rows after family de-duplication. **64** have more than one five-bucket vector in the current data, rather than the specification's 59. This confirms drift but not that earlier exact count.

A fresh **in-memory**, account-scoped `rowsFor('2025-12', 'B')` retains all 36 stage disagreements when applying the single-ladder thresholds. Some day counts differ from today's stored book: BenCal is stored at 271, recomputes to 181; Nature's Gift 104450000023 is stored at 254, recomputes to 271. These remain in the same Stage 3 bracket. I did not write these calculations back.

## 2. What the code actually does

`LoanBookBuildService::rowsB()` (`app/Services/Ebanker/LoanBookBuildService.php:529`) rebuilds the balance from signed ledger postings. It then calls `arrears($p)` and `daysPastDue($p, ...)` using the selected stored report payload. There is no general receipts-versus-schedule ageing calculation for ordinary charts.

`againstTheChart()` (same file, line 899) reconstructs ageing for readable **collapsed** charts, or corrects reported ageing when nothing has yet fallen due. An ordinary chart with payments covering every due instalment does not receive that reconstruction. Merely selecting “Recount against the schedule in force and the ledger” therefore does **not** make every ordinary loan's DPD ledger-derived.

There is also a policy distinction: `StagingPolicy::PRESETS[PRESET_SINGLE]` (`app/Services/Eir/StagingPolicy.php:85`) uses bucket ageing, **Core-system report as given**, and **Count from maturity when longer**. `stagingDays()` (line 239) selects the report bucket count even where the build already knows a corrected schedule count. Consequently:

- BenCal's reconstructed zero DPD already exists in the build's corrected count/`overdue_days`; the single-ladder policy still selects the report bucket count.
- JAT's ordinary chart never gets generally reconstructed by `againstTheChart()`. A policy toggle alone would not supply the missing 69-day calculation.
- A new ledger-derived ageing basis would be a change requiring an explicit governed design, not a consequence of method B already being selected for carrying amounts.

## 3. Reconstruction method and independent checks

For the named 26, I read all available current ledger postings through **2025-12-31 inclusive**. I used the reader's LANDED-load and non-superseded filters, latest-load/source-key de-duplication, loan GL filter and deletion rules. Account identifiers were padded only when querying raw rows.

Receipt classification follows the existing classes: 305/306/343/900/901, plus settlement in kind 401, signed reversals netted and write-offs excluded. For these 26, the observed receipt types are only **305/306**, and no selected receipt is classified as a write-off. Actual reversals include MUSECO's **-200,000,000** on 30 November 2025, Msusa's **-7,079,204.84**, Dr Print's **-4,000,000** and FAVOA's **-1,602,549.64**. Counting positive credits alone would be wrong.

I obtained the schedule in force at cutoff from `InstalmentChartHistory::at()`, not the live October 2026 chart. That includes P2_10/IC_01, dated replacement rows in RS_02a and, where supported by the reader, the dated copies RS_02b/RS_02d/RS_02c. BenCal's collapsed monthly rows are re-dated using the reader's existing serial/monthly-definition rule; they are not treated as literally all due on 1 February 2025. 8 of the 23 available charts rely on dated copies. This is an existing model assumption, not independent proof that no unextracted amendment occurred.

I calculated cumulative instalments due and allocated net receipts oldest first, retaining prepayments. The oldest partly unpaid due date gives calendar DPD; no unpaid due instalment gives zero. I separately recomputed receipts and balances from decoded raw rows, and independently implemented the allocation in Python using decimal arithmetic. The independent amounts and DPD agreed with the PHP helper for all 23 chart-backed facilities. All 23 also pass the existing staging guard that instalments due must not exceed everything the ledger has debited to the loan.

I traced every available built month through December for all 26, not just November/December. The histories start in July 2024 or at later facility inception. Older facilities' take-on “Opening Recover Amount” credits represent cumulative earlier recoveries: the extracts do not provide the original dates of every pre-migration receipt. I have not presented a reconstructed pre-July-2024 cash history or treated an ordinary periodic date move as corruption.

## 4. Facility-by-facility result for the original 26

Workbook bracket codes: **A = 0–30 days** (workbook label “1-30 Days” includes current), **B = 31–90**, **C = 91–180**, **D = over 180** (workbook label “>181 Days”). “Book” is today's stored staging DPD; “Fresh B” is the current in-memory method-B staging DPD under the existing policy. “FIFO” uses receipts against the chart, before the maturity floor. Amounts are MWK. A dash means unavailable, not zero.

| Contract | Customer | WB | Book / Fresh B DPD | Receipts to cutoff | Instalments due | FIFO DPD | FIFO with maturity floor |
|---|---|---|---:|---:|---:|---:|---:|
| 104420000005 | MUSECO | D | 0 / 0 | 677,255,167.26 | 602,519,305.00 | 0 | 0 |
| 104450000023 | Nature's Gift Limited | A | 254 / 271 | 28,629,918.41 | 28,636,478.33 | 274 | 274 |
| 104450000077 | Kwithu Kitchens 2 | A | 31 / 31 | 95,187,057.30 | 105,580,824.48 | 91 | 91 |
| 104460000059 | BenCal Technologies Ltd | A | 271 / 181 | 148,423,383.52 | 101,485,663.40 | 0 | 0 |
| 104450000063 | Msusa Investments Ltd | A | 31 / 31 | 182,410,097.50 | 197,462,103.60 | 91 | 91 |
| 104460000066 | Mphanga Hodings Limited | A | 228 / 228 | 95,239,858.07 | 95,219,858.16 | 0 | 228 |
| 104450000069 | Chisitu Tea Blenders and Packers Ltd | D | 0 / 0 | 159,000,000.00 | 137,349,076.08 | 0 | 0 |
| 104460000070 | Mchinji Civil Servants SACCO | A | 154 / 154 | 108,759,832.00 | 108,804,531.42 | 183 | 183 |
| 104450000072 | Jescal Enterprise | A | 154 / 181 | 14,657,300.00 | 14,857,209.15 | 183 | 183 |
| 104450000074 | Pamela Investments | A | 184 / 184 | 33,789,495.55 | 33,789,307.20 | 0 | 184 |
| 104460000080 | F&K Construction | A | 31 / 31 | 70,000,000.00 | 74,379,721.24 | 60 | 60 |
| 104450000060 | Chuma Mkandawire TA Chikoma Estates | A | 396 / 396 | 31,649,722.08 | 31,649,719.40 | 0 | 396 |
| 104460000083 | Dr Print | A | 31 / 31 | 60,555,745.00 | 67,261,103.95 | 60 | 60 |
| 104460000087 | Kings Foundationn Schools | A | 91 / 91 | 86,970,819.00 | 106,277,185.81 | 121 | 121 |
| 104460000092 | FAVOA Innovation & Investments Group | B | 1 / 1 | 27,327,946.57 | 28,892,419.20 | 30 | 30 |
| 104430000062 | Ifracon Limited | A | 91 / 91 | 321,536,893.33 | 410,158,051.00 | 140 | 140 |
| 104460000098 | INFRAWORKS DEVELOPMENT LIMITED | A | 271 / 271 | 61,596,363.30 | 167,469,682.82 | 305 | 305 |
| 104430000068 | Nyamunyamu processors limited | C | 271 / 271 | 64,560,059.53 | 122,956,478.00 | 261 | 261 |
| 104460000107 | Premium meat suppliers | C | 271 / 181 | 12,550,000.00 | — | — | — |
| 104430000070 | Ebenezer midian ltd | A | 91 / 91 | 146,913,944.15 | 196,995,944.00 | 113 | 113 |
| 104430000071 | CHIPALA INVESTMENT LIMITED | A | 31 / 31 | 226,193,371.25 | 269,716,666.00 | 45 | 45 |
| 104460000109 | JVD AGRO LIMITED | A | 271 / 181 | 0.00 | 37,182,327.00 | 213 | 213 |
| 104460000110 | MTHIRAKUWIRI FARM | A | 271 / 181 | 0.00 | — | — | — |
| 104460000112 | Elmaw Lodge | A | 91 / 91 | 0.00 | — | — | — |
| 104430000082 | PINNACLE FINANCIAL SERVICES LIMITED | A | 31 / 31 | 93,500,000.00 | 192,519,752.00 | 134 | 134 |
| 104430000087 | JAT GROUP LTD | B | 1 / 1 | 13,460,500.00 | 28,624,982.00 | 69 | 69 |

**Counts:** chart-only FIFO = 5 matching, 18 differing, 3 unavailable. FIFO plus the current maturity floor = 2 matching, 21 differing, 3 unavailable. These are counts within the original 26 only, not a restaged whole portfolio or an ECL impact estimate.

### 4.1 JAT: a clear additional lead

Contract **104430000087**, system label JAT GROUP LTD, workbook label JAT Investment, matches by exact balance **222,280,864.49**. Its first instalment due by cutoff is **28,624,982.00 on 23 October 2025**. A dated chart row in force is RS_02a source **132818**, definition **8811**, serial **4**, created **1 November 2025**, replaced **4 February 2026**. Earlier chart versions also carry the October 23 due date; this conclusion does not depend on reading the August 2026 live chart.

The repayment credits are:

| Ledger source key | Posting date | Type | Amount |
|---|---|---|---:|
| 257710 | 25 November 2025 | 305 | 3,660,500.00 |
| 272447 | 12 December 2025 | 305 | 9,800,000.00 |
| **Total** | | | **13,460,500.00** |

The October instalment remains short by **15,164,482.00**, exactly the stored report's total arrears. October 23 to December 31 is **69 days**, matching the workbook's **31–90** bracket. The report-based bucket count is **1**, which produces Stage 1 rather than the workbook's Stage 2. The reported overdue date itself is October 23 and the build's oldest-date count is already 69; it is the selected bucket basis that loses that information.

The monthly reconstruction is coherent: nothing due July–September, 8 days in October, 38 in November after the first payment, 69 in December after the second. The chart reader also retains overlapping future definitions on this account; those affect dates after December, not the single instalment due in this calculation. This is evidence to investigate a distinct ageing basis, not a blanket endorsement of the account's future schedule.

### 4.2 BenCal: supported by receipts, but already a known chart/policy interaction

Contract **104460000059** has net receipts **148,423,383.52** against corrected chart instalments due **101,485,663.40**. FIFO DPD is **0**, matching the workbook's A bracket. Its loan does not mature until February 2028, so the maturity floor does not alter this.

The four receipt keys are **13452**, **13587**, **14417**, **242824**: opening recoveries **33,720,730.35** on 31 July 2024; **3,739,380.47** on 6 August; **100,963,272.70** on 31 August; **10,000,000.00** on 31 October 2025. The chart is paid ahead throughout 2025 under this FIFO assumption. The reader has a documented chart gap at August 2024; it must not be described as an uninterrupted authoritative schedule history.

RS_02c sources **12083/12084** demonstrate the collapsed monthly dates: serials 25 and 26 both say 1 February 2025, amount 3,739,380.47 each. The existing reader corrects these by serial. The current build already calculates zero corrected ageing for BenCal, but the single-ladder preset intentionally takes the uncorrected report bucket. This contradicts the finding that BenCal is clean and unambiguously overdue by every available signal; it is not a newly discovered general allocation algorithm.

### 4.3 Three matured residual balances: schedule agreement is not full reconciliation

| Contract / customer | Receipts less all chart instalments due | Remaining ledger carrying amount | Days since maturity |
|---|---:|---:|---:|
| 104460000066 / Mphanga | 19,999.91 | 647,495.70 | 228 |
| 104450000074 / Pamela | 188.35 | 260,713.98 | 184 |
| 104450000060 / Chuma/Chikomanya | 2.68 | 756,467.01 | 396 |

FIFO says zero schedule arrears on each, agreeing with the workbook's A bracket. But none has a zero accounting balance. For example, Mphanga's balance independently rebuilds as **84,923,700.00 disbursed + 10,963,653.77 interest − 95,239,858.07 receipts = 647,495.70**. A fully covered historical instalment chart need not cover all interest actually charged.

The receipt history explains when FIFO clears: Mphanga's **12,948,681.41** credit on **30 August 2025** (key **210341**); Pamela's **3,145,000.00** reallocated repayment on **30 November 2025** (key **258209**); Chuma's **21,800.00** credit on **31 August 2025** (key **210455**), following **31,627,922.08** on **12 November 2024** (key **28401**). Chuma's chart source **P2_10:29050** is a single positive bullet instalment **31,649,719.40 due 1 November 2024**.

These are legitimate leads about chart-versus-accrued-balance and workbook treatment of residual debt. They are **not three proven additional staging fixes**: the existing policy still ages the positive balance from maturity. Finance would need to explain the residual balances and the intended treatment, rather than code simply suppressing the maturity floor to improve a match count.

### 4.4 Infraworks: the new hypothesis does not close this disagreement

Contract **104460000098** has **61,596,363.30** receipts and **167,469,682.82** scheduled due. The shortfall is **105,873,319.52**, exactly the report's `ARREAS_TOTAL`. FIFO finds **1 March 2025**, giving **305 days**, against the report's **29 March 2025 / 277 actual days**, and the bucket-based staging count **271**. All are in bracket D; the workbook says A.

The schedule's day-of-month differs from the report, but the monetary arrears reconcile exactly. Ledger reconstruction therefore supports substantial arrears on this account under the existing chart/FIFO model; it does not substantiate the workbook's A bracket. The earlier “all future investigation is exhausted” conclusion is too strong, but the proposed hypothesis also does not explain everything.

### 4.5 Missing charts and narrow boundary/tail cases

For **104460000107 Premium**, **104460000110 Mthirakuwiri** and **104460000112 Elmaw**, `InstalmentChartHistory::at()` returns null at cutoff. Premium and Mthirakuwiri have no chart rows in the live/history/copy query families checked. Elmaw has superseded chart rows, but no chart survives to the cutoff under the reader's effective-date rules. Do not substitute the latest chart or interpret null as current.

There are further **plan-only** clues in P3_11, not used to claim chart-backed matches: Premium definition **1734** states monthly **2,420,039** from **1 January 2025**, 36 instalments; Mthirakuwiri definition **2143** states monthly **4,768,678** from **1 June 2025**, 60 instalments; Elmaw's live definition **7838** states monthly **2,307,246** from **1 August 2025**, 24 instalments. Expanding these definitions as a sensitivity would give approximately **213 / 213 / 152 DPD**, respectively, and would not match their workbook brackets. Their precise historical applicability must be established before treating that expansion as an authoritative schedule.

Some remaining gaps are small residual schedule arrears: Nature's Gift **6,559.92**, Mchinji **44,699.42**, Jescal **199,909.15**. FAVOA gives **30** days from 1 December, immediately below the workbook's 31–90 bracket. These merit questions about tolerances, cutoffs and allocation, not silent forgiveness or an invented day-count adjustment. MUSECO and Chisitu remain current by the receipt/chart calculation, while the workbook calls them D; the new method does not resolve those reverse-direction mismatches.

## 5. Corrections needed to the earlier investigation narrative

1. **“Every pull agrees” / “the 26 are clean” is false in the current data.** Fresh method B flags stable-field conflicts for six of the 26: **104430000062, 104430000068, 104430000070, 104430000071, 104430000082, 104430000087**. JAT alone has an intra-December-31 pull carrying **207,758,842.45** versus the later finalized **222,280,864.49**, with the same arrears and overdue date. Ifracon likewise has **665,034,062.46** versus **685,075,072.14**. The fallback is doing what it was designed to do; these are nevertheless conflicts, not all-identical clean evidence. They do not by themselves prove which workbook bracket is right.
2. **“107 matched by exact name and balance” overstates the matching evidence.** The actual command first uses unique exact balance, even when names differ, then falls back to normalized name and nearest balance. It reports 90 “name and balance”, 15 “balance (names differ)”, and 2 “name”. **MUSECO's workbook balance is 0 while the system balance is 138,135,680.80**, matched only by name. It should be isolated as a population/balance issue before treating it as a clean ageing reconciliation. The other name-only match, Malasha, differs by one ngwee and is outside the named 26.
3. **“10 explained” does not mean 10 reconciled.** Those flags identify defects worthy of review, but the unchanged 36-stage mismatch count does not prove those defects caused the workbook difference. A causal explanation requires a supported corrected value/method that reproduces the workbook. BenCal, a member of the allegedly clean 26, already demonstrates a further chart/report disagreement.
4. **Stable report fields do not validate ageing against payments.** Repeated snapshots can consistently repeat the same stale overdue allocation. The extracts still contain an independent test: receipts against obligations. That test was not exhausted by checking report self-consistency and date jumps.

## 6. Recommended next step

Keep the point-in-time selector and its conflict notices as protections against re-pull drift. Do not equate an earliest available pull with a fully reconstructed historical ageing result, and do not turn off the maturity rule solely to maximize workbook agreement.

Before changing any build/staging behavior, specify a separate ledger-derived ageing comparison with traceable chart identity, signed qualifying receipt keys, cumulative due/paid amounts, oldest unpaid date, chart coverage/conflict status and maturity treatment. Make **JAT** the principal new regression example, **BenCal** the existing correction/policy example, **Infraworks** the counterexample, the **three matured residuals** explicit policy cases, and the **three unavailable charts** unknown-result cases. Preserve prepayments and reversals; do not use carrying balance alone to infer receipts, or use the live schedule for a past date.

Take those concrete differences to Finance: the allocation of large prepayments; treatment of matured interest/residual balances; MUSECO's zero workbook exposure; and the workbook's source/formula for the still-disagreeing facilities. The supported conclusion is **partial reconciliation and additional testable evidence**, not a wholesale endorsement of either book and not proof of 26 unambiguously overdue facilities.

## Appendix: reproducible read-only calculation

The following standalone PHP script runs against this checkout and prints the chart-backed diagnostic for the exact original 26. It does not rebuild or stage the database. It explicitly sets the database and restricts transactions to read-only. The independent decimal raw-row cross-check described above was a second implementation; the compact script below reproduces the primary result using the existing receipt classifier and historical chart reader.

```php
<?php
$repo = 'C:/xampp/htdocs/MAICC-IFRS9';
require $repo . '/vendor/autoload.php';
$app = require $repo . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.mysql.database' => 'maiic_ifrs9_bootstrap']);
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\DB::statement('SET SESSION TRANSACTION READ ONLY');
$zone = new App\Services\Ebanker\LandingZoneReader();
$charts = new App\Services\Ebanker\InstalmentChartHistory($zone);
$ids = explode(' ',
    '104420000005 104450000023 104450000077 104460000059 104450000063 ' .
    '104460000066 104450000069 104460000070 104450000072 104450000074 ' .
    '104460000080 104450000060 104460000083 104460000087 104460000092 ' .
    '104430000062 104460000098 104430000068 104460000107 104430000070 ' .
    '104430000071 104460000109 104460000110 104460000112 104430000082 ' .
    '104430000087');
$cutoff = '2025-12-31';
foreach ($ids as $id) {
    $account = str_pad($id, 15, '0', STR_PAD_LEFT);
    $chart = $charts->at($account, $cutoff);
    $paid = $charts->receiptsTo($account, $cutoff);
    $due = 0.0; $cumulative = 0.0; $oldest = null;
    if ($chart !== null && !$chart['unreadable']) {
        foreach ($chart['instalments'] as [$date, $amount]) {
            if ($date > $cutoff) break;
            $due += $amount;
            $cumulative += $amount;
            if ($oldest === null && $cumulative > $paid + 0.005) $oldest = $date;
        }
        $dpd = $oldest === null ? 0 :
            (new DateTimeImmutable($oldest))->diff(new DateTimeImmutable($cutoff))->days;
    } else {
        $dpd = null;
        $due = null;
    }
    $book = Illuminate\Support\Facades\DB::table('loan_books')
        ->where('contract_id', $id)->where('reporting_period', '2025-12')->first();
    $maturity = $book->due_date;
    $floor = $maturity !== null && $maturity < $cutoff && (float)$book->carrying_amount > 0.005
        ? (new DateTimeImmutable($maturity))->diff(new DateTimeImmutable($cutoff))->days : 0;
    echo json_encode([
        'contract_id' => $id, 'customer' => $book->customer_name,
        'book_dpd' => $book->staging_dpd, 'receipts' => round($paid, 2),
        'due' => $due === null ? null : round($due, 2),
        'oldest_unpaid' => $oldest, 'fifo_dpd' => $dpd,
        'fifo_with_maturity' => $dpd === null ? null : max($dpd, $floor),
        'chart_source' => $chart['source'] ?? null,
        'chart_collapsed' => $chart['collapsed'] ?? null,
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
```

For raw verification, begin with `DESCRIBE ebanker_raw_rows`, `DESCRIBE loan_books`, and `SHOW INDEX FROM loan_books`. Read only current LANDED rows, and de-duplicate each family's `source_key` in ascending load/id order before summing. For example:

```sql
SELECT r.query_id, r.source_key, r.account, r.row_date, r.load_id, r.payload
FROM ebanker_raw_rows r
JOIN ebanker_loads l ON l.id = r.load_id AND l.status = 'LANDED'
WHERE r.superseded_at IS NULL
  AND r.account = '000104430000087'
  AND r.query_id IN ('P1_01','M09_01','MF_03','GL_03','ZF_01')
  AND r.row_date <= '2025-12-31'
ORDER BY r.load_id, r.id;
```

Decode JSON, retain loan GLs, drop deleted rows, classify signed receipts, and compare cumulative due-date-ordered obligations with those receipts. `INST_RECOVERED` and other live chart recovery fields were not used as historical cash evidence. Receipt classification, carry-forward of opening recoveries, historical chart validity and the contractual application of prepayments remain explicit dependencies of the diagnostic, rather than facts proved by its agreement with the workbook.
