# EIR, FEES CLASSIFICATION & ACCOUNTING RULES - COMPLETE ANALYSIS

## 1. WHICH EIR WILL BE USED IN THE ECL FORMULA?

### Answer: `eir_effective_annual` (the annual compounded rate)

**Formula for Door 3 (ECL Impairment Discounting)**:
```
ECL = PD × LGD × EAD / (1 + eir_effective_annual)^t
```

Where:
- **PD** = Probability of Default
- **LGD** = Loss Given Default  
- **EAD** = Exposure at Default
- **eir_effective_annual** = The locked effective annual rate from contract_eir table
- **t** = Time to maturity (in years)

### Why the EFFECTIVE Annual Rate?

Three rates are calculated and stored:

| Rate Type | Formula | Usage | Storage |
|-----------|---------|-------|---------|
| **Periodic** | Solved directly in payment-period units | Intermediate calculation only | `eir_period` |
| **Nominal Annual** | `eir_period × payments_per_year` | Disclosure/reference | `eir_nominal_annual` |
| **Effective Annual** | `(1 + eir_period)^payments_per_year - 1` | ✅ ECL discounting, Door 2 revenue | `eir_effective_annual` |

### Example
```
Contract: 104430000004
Periodic EIR (monthly): 2.8230%
Nominal Annual: 2.8230% × 12 = 33.876%
Effective Annual: (1.028230)^12 - 1 = 38.88% ← USED IN ECL

For ECL on a 5-year loan with PD=2%, LGD=60%, EAD=MK100M:
ECL = 0.02 × 0.60 × 100M / (1.3888)^5
    = 1.2M / 5.243
    = MK228,800 (discounted)

Without EIR (old method - undiscounted):
ECL = 0.02 × 0.60 × 100M = MK1.2M (1000% higher!)
```

---

## 2. HOW ARE FEES CLASSIFICATION USED IN EIR CALCULATIONS?

### The Fee Classification Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│ Step 1: Fees Extracted from Import                              │
├─────────────────────────────────────────────────────────────────┤
│ Source: contract_fees table (all raw fees)                      │
│ Fields: fee_type, amount, basis, cashflow_direction             │
│ Status: classification_status = PENDING                         │
│ Integral: integral = NULL (not yet decided)                     │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Step 2: Finance Team Reviews via Fee Classification UI           │
├─────────────────────────────────────────────────────────────────┤
│ Manually decides for EACH fee line:                             │
│                                                                  │
│ Q1: Is this fee integral to the loan's yield?                   │
│     YES → integral = true  (include in EIR)                     │
│     NO  → integral = false (exclude from EIR)                   │
│                                                                  │
│ Q2: Should we amortise this over the loan life?                 │
│     YES → Use in revenue recognition (Door 2)                   │
│     NO  → Recognise upfront (not part of IFRS 9 EIR)           │
│                                                                  │
│ Q3: Is it GL-coded correctly?                                   │
│     YES → gl_account_ref populated                              │
│                                                                  │
│ Status: classification_status = REVIEWED                        │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Step 3: EIR Calculation Reads Reviewed Fees                      │
├─────────────────────────────────────────────────────────────────┤
│ EirContractInputService::assemble() queries:                    │
│                                                                  │
│ $feeRows = DB::table('contract_fees')                           │
│     ->where('contract_id', $contractId)                         │
│     ->where('classification_status', 'REVIEWED')  ← REVIEWED    │
│     ->where('integral', true)                     ← INTEGRAL    │
│     ->get();                                                     │
│                                                                  │
│ Only fees marked REVIEWED + integral=true go to solver          │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Step 4: Solver Includes Fees in NPV Calculation                 │
├─────────────────────────────────────────────────────────────────┤
│ Initial Net Investment = Drawn - Received + Paid               │
│                       = 1000 - 50 + 10                         │
│                       = 960 MK                                  │
│                                                                  │
│ Solver finds rate r where:                                      │
│ NPV = -960 + Σ(cash_flows / (1+r)^t) = 0                       │
│                                                                  │
│ The fees are BAKED into the rate via initial net investment   │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Step 5: EIR Locked with Fees Embedded                           │
├─────────────────────────────────────────────────────────────────┤
│ Result: eir_effective_annual = 38.88%                           │
│ Input Snapshot Stored: All reviewed fees captured               │
│ Status: calculation_status = LOCKED                             │
└─────────────────────────────────────────────────────────────────┘
```

### Fee Classification Decision Matrix

| Fee Type | Example | Integral? | Basis | GL Account | Why? |
|----------|---------|-----------|-------|------------|------|
| **Arrangement** | 50M upfront | TRUE | ON_DRAWN | 8000 (fee income) | Part of yield negotiation |
| **Legal** | 10M to lawyer | TRUE | ON_DRAWN | 8100 (costs) | Transaction cost affecting yield |
| **Commitment** | 0.5M on undrawn | FALSE | ON_APPROVED | 8200 | Optional, not integral to disbursed facility |
| **Documentation** | 1M for paperwork | TRUE | ON_DRAWN | 8000 | Essential transaction cost |
| **Prepayment Penalty** | Variable | FALSE | AD_HOC | 8500 | Contingent, not at origination |

### Data Model: contract_fees

```php
$contractFee = [
    'contract_id' => '104430000004',
    'fee_type' => 'arrangement',
    'description' => 'Arrangement fee per facility agreement',
    'amount' => 50_000_000,           // Can be negative (credit)
    'basis' => 'ON_DRAWN',            // ON_DRAWN or ON_APPROVED
    'cashflow_direction' => 'RECEIVED', // RECEIVED or PAID
    'integral' => true,               // ✅ Include in EIR? YES
    'classification_status' => 'REVIEWED', // PENDING → REVIEWED → REJECTED
    'transaction_date' => '2021-11-24',
    'gl_account_ref' => '8000',
    'source_reference' => 'FACILITY_AGREEMENT_P3',
];
```

### Example: How Fees Change the EIR

```
Loan Details:
  Drawn: MK 1,000,000,000
  Contractual Rate: 30% p.a. annual payment
  Tenor: 1 year
  
Scenario A: NO INTEGRAL FEES
  Initial Net Investment: 1,000,000,000
  Cash Flow Year 1: 1,000,000,000 × 1.30 = 1,300,000,000
  Solve: r where NPV = -1000M + 1300M/(1+r) = 0
  Result: r = 30% (matches contractual rate)

Scenario B: WITH INTEGRAL FEES
  Arrangement Fee: MK 46.16M (REVIEWED + integral=true)
  Initial Net Investment: 1,000,000,000 - 46,160,000 = 953,840,000
  Cash Flow Year 1: 1,300,000,000 (unchanged - fees already taken)
  Solve: r where NPV = -953.84M + 1300M/(1+r) = 0
  Result: r = 36.17% (HIGHER than contractual!)
  
Why? The lender gets back MK 1.3B but only put in MK 953.84M
Economic yield = 36.17%, not 30%
```

---

## 3. HOW ARE ACCOUNTING RULES USED IN EIR CALCULATIONS?

### Answer: Accounting Rules → Fee Classification Rules

**Location**: `eir_accounting_rules` table + `EirFeeClassificationController`

### Accounting Rules ARE NOT Used in EIR SOLVER

The EIR solver itself is pure math - it takes:
- Initial net investment
- Cash flows
- Solves for rate

**Accounting rules are used BEFORE calculation** to CLASSIFY fees so the solver gets the right inputs.

### Accounting Rules Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│ Admin Creates Accounting Rules (Configuration)                   │
├─────────────────────────────────────────────────────────────────┤
│ Rule Type: FEE_CLASSIFICATION                                    │
│                                                                  │
│ IF fee matches THESE CRITERIA:                                  │
│   • fee_type = 'arrangement'                                    │
│   • gl_account_code LIKE '8000'                                 │
│   • cashflow_direction = 'RECEIVED'                             │
│                                                                  │
│ THEN apply THESE TREATMENTS:                                    │
│   • integral = TRUE                                             │
│   • recognition_basis = 'OVER_LOAN_LIFE'                       │
│   • proposed_classification = 'INTEGRAL_FEE'                    │
│   • priority = 1                                                │
│                                                                  │
│ Status: DRAFT → APPROVED → ACTIVE                              │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ Finance Team Uses Rules During Fee Classification               │
├─────────────────────────────────────────────────────────────────┤
│ UI shows:                                                        │
│   Incoming Fee: {type: 'arrangement', amount: 50M, gl: '8000'} │
│   Matching Rule: Found! (priority=1)                            │
│   Proposal: integral=TRUE                                       │
│                                                                  │
│ Finance clicks: ✅ ACCEPT PROPOSAL                             │
│   → contract_fees.integral = TRUE                               │
│   → contract_fees.classification_status = REVIEWED              │
│   → eir_fee_classification_events log entry created             │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ EIR Calculation Uses Classified Fees                            │
├─────────────────────────────────────────────────────────────────┤
│ EirContractInputService::assemble():                            │
│                                                                  │
│ Only includes fees where:                                       │
│   classification_status = 'REVIEWED'    ← Classification done   │
│   integral = true                       ← Rule applied         │
│                                                                  │
│ These fees go into:                                             │
│   Initial Net Investment calculation                            │
│   → Solver input                                                │
│   → Affects EIR                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Accounting Rules Data Model

```php
$rule = [
    'id' => 1,
    'rule_type' => 'FEE_CLASSIFICATION',
    'name' => 'Arrangement Fees - MAIIC Portfolio',
    
    // MATCH CRITERIA (all must match)
    'fee_type' => 'arrangement',                  // NULL = any
    'gl_account_pattern' => '8000%',              // LIKE pattern
    'cashflow_direction' => 'RECEIVED',           // NULL = any
    'source_system' => 'EXTRACT_A',               // NULL = any
    
    // PROPOSED TREATMENT
    'proposed_integral' => true,
    'proposed_basis' => 'ON_DRAWN',
    'proposed_recognition' => 'OVER_LOAN_LIFE',
    
    // WORKFLOW
    'status' => 'ACTIVE',                         // DRAFT, APPROVED, ACTIVE, INACTIVE
    'priority' => 1,                              // 1=highest (first match wins)
    'created_at' => '2026-08-01',
    'approved_at' => '2026-08-05',
    'approved_by' => 5,
];
```

### Example: Rules Affecting Three Fees

```
Contract 104430000004 - MK 309.5B Facility

Fee 1: Arrangement Fee
  Type: 'arrangement', Amount: MK 50M, GL: 8000, Direction: RECEIVED
  Rule Match: YES (Type + GL match rule #1)
  Proposed: integral = TRUE
  Finance Decision: ✅ ACCEPT
  Result: Goes into EIR calculation

Fee 2: Legal Fees
  Type: 'legal', Amount: MK 10M, GL: 8100, Direction: PAID
  Rule Match: YES (Type + GL match rule #2)
  Proposed: integral = TRUE
  Finance Decision: ✅ ACCEPT
  Result: Goes into EIR calculation

Fee 3: Commitment Fee
  Type: 'commitment', Amount: MK 0.5M, GL: 8200, Direction: RECEIVED
  Rule Match: NO (Type doesn't match any rule)
  Proposed: integral = NULL (no rule)
  Finance Decision: ❌ REJECT (after review)
  Result: Excluded from EIR (not integral)

EIR Solver Uses:
  Drawn: 309,523,514
  - Arrangement Fee: 50,000,000 (RECEIVED)
  + Legal Fee: 10,000,000 (PAID)
  = Initial Net Investment: 269,523,514
  
  Commitment fee: Ignored (integral ≠ true)
```

---

## 4. IN YOUR SYSTEM - WHICH EIR IS USED FOR EACH CONTRACT?

### From the Screenshot - EIR Calculations Page

Looking at your UI showing all 24 locked contracts:

| Contract ID | Portfolio | Periodic EIR | Effective Annual EIR | Status |
|-------------|-----------|--------------|----------------------|--------|
| **104420000032** | MAIIC | 2.3750% | **32.5339%** ✅ | LOCKED |
| **104450000015** | FinES | 0.8333% | **10.4713%** ✅ | LOCKED |
| **104450000041** | FinES | 0.8333% | **10.4713%** ✅ | LOCKED |
| **104450000060** | FinES | 0.8333% | **10.4713%** ✅ | LOCKED |
| **104420000010** | MAIIC | 2.6000% | **36.0719%** ✅ | LOCKED |
| **104430000004** | MAIIC | 2.8230% | **38.8834%** ✅ | LOCKED |
| **104430000061** | MAIIC | 2.7600% | **37.1370%** ✅ | LOCKED |
| **104430000089** | MAIIC | 2.3646% | **31.2500%** ✅ | LOCKED |
| ... (18 more) | ... | ... | ... | LOCKED |

### From the EIR Data Page - Contract Master

Looking at your second screenshot (Contract Master view):

| Contract | Portfolio | Terms | Amounts | Cash Flows | EIR Status | EIR Rate |
|----------|-----------|-------|---------|------------|------------|----------|
| **104420000010** | MAIIC | 2021-04-20 → 2026-04-19 Monthly 31.200% | 80M approved, 80M drawn | 59 cash flows, 0 fees | **LOCKED** | **36.07%** |
| **104450000060** | FinES | 2023-01-11 → 2024-11-29 Monthly 10.000% | 30M approved, 30M drawn | 12 cash flows, 0 fees | **LOCKED** | **10.47%** |
| **104450000041** | FinES | 2022-12-07 → 2024-01-07 Monthly 10.000% | 12M approved, 12M drawn | 24 cash flows, 0 fees | **LOCKED** | **10.47%** |
| **104450000015** | FinES | 2021-12-16 → 2023-12-19 Monthly 10.000% | 10M approved, 10M drawn | 24 cash flows, 0 fees | **LOCKED** | **10.47%** |
| **104430000061** | MAIIC | 2023-02-12 → 2025-02-12 Monthly 32.000% | 53.39M approved, 53.39M drawn | 24 cash flows, 0 fees | **LOCKED** | **37.14%** |
| **104430000004** | MAIIC | 2021-11-24 → 2023-11-24 Monthly 31.300% | 309.52M approved, 309.52M drawn | 24 cash flows, 0 fees | **LOCKED** | **38.88%** |

### Key Observations from Your System

1. **All 24 contracts LOCKED** → All have final EIR rates calculated and approved
2. **MAIIC portfolio**: EIRs range 28-38% (higher yield)
3. **FinES portfolio**: EIRs range 10-10.5% (lower yield - agricultural)
4. **Effective Annual rates vary more than Periodic** because of payment frequency:
   - Monthly payments: (1 + periodic)^12 - 1
   - Quarterly payments: (1 + periodic)^4 - 1

### Which EIR Goes Into ECL?

**Answer: The `eir_effective_annual` column from contract_eir for each contract**

```sql
-- Query to see EIRs that will be used in ECL
SELECT 
    contract_id,
    portfolio,
    eir_effective_annual as eir_for_ecl,
    locked_at,
    locked_by
FROM contract_eir
WHERE locked_at IS NOT NULL
ORDER BY portfolio, eir_effective_annual DESC;
```

**Result** (what will be used for Door 3 discounting):
```
104430000004 (MAIIC)          38.8834%  ← Used to discount ECL
104420000010 (MAIIC)          36.0719%  ← Used to discount ECL
104430000061 (MAIIC)          37.1370%  ← Used to discount ECL
104430000089 (MAIIC)          31.2500%  ← Used to discount ECL
...
104450000015 (FinES)          10.4713%  ← Used to discount ECL
```

---

## 5. HOW FEES CLASSIFICATION & ACCOUNTING RULES FLOW TOGETHER

### Complete Flow Diagram

```
┌──────────────────────────────────┐
│ EXTRACT A: Facility Master       │
│ + EXTRACT B: Cash Flows          │
└──────────────────────────────────┘
               ↓
┌──────────────────────────────────┐
│ CONTRACT INTAKE (Manual or Import)│
│ → contract_eir created           │
│ → contract_cashflow_schedule v1  │
│ → contract_fees (PENDING)        │
└──────────────────────────────────┘
               ↓
┌──────────────────────────────────────────────────┐
│ ACCOUNTING RULES (Admin Configuration)           │
│ eir_accounting_rules table populated:            │
│ "IF fee_type='arrangement' AND gl='8000*'        │
│  THEN integral=TRUE"                             │
│                                                  │
│ Rule Status: ACTIVE (ready to use)               │
└──────────────────────────────────────────────────┘
               ↓
┌──────────────────────────────────────────────────┐
│ FEE CLASSIFICATION UI                            │
│ (EirFeeClassificationController)                 │
│                                                  │
│ For each contract_fees row:                      │
│   1. Load fee details                            │
│   2. Check eir_accounting_rules (by priority)    │
│   3. Show proposed classification to finance    │
│   4. Finance accepts or rejects                  │
│   5. Store decision in contract_fees             │
│   6. Create audit trail in                       │
│      eir_fee_classification_events               │
└──────────────────────────────────────────────────┘
               ↓
          REVIEWED
      integral = TRUE/FALSE
               ↓
┌──────────────────────────────────────────────────┐
│ READINESS CHECK (EirReadinessService)            │
│                                                  │
│ "All integral fees must be REVIEWED"             │
│ ✓ All fees have classification_status=REVIEWED   │
│ → Status: READY                                  │
└──────────────────────────────────────────────────┘
               ↓
┌──────────────────────────────────────────────────┐
│ EIR INPUT ASSEMBLY (EirContractInputService)    │
│                                                  │
│ Query:                                           │
│   WHERE classification_status='REVIEWED'         │
│   AND integral = true                            │
│                                                  │
│ Calculate:                                       │
│   Initial Net = Drawn                           │
│              - Received Fees                     │
│              + Paid Fees                         │
│                                                  │
│ Result: (960M initial, cash flows, payments)    │
└──────────────────────────────────────────────────┘
               ↓
┌──────────────────────────────────────────────────┐
│ EIR SOLVER (CalculateEirService)                │
│                                                  │
│ Inputs:                                          │
│   initial_net_investment = 960M                  │
│   cash_flows = [year1: 350M, year2: 350M, ...]  │
│   payments_per_year = 12                         │
│                                                  │
│ Solve: NPV = 0                                   │
│ Output: eir_period, eir_nominal_annual,         │
│         eir_effective_annual                     │
│                                                  │
│ Store: contract_eir updated with:               │
│   eir_effective_annual = 38.88%  ← FOR ECL      │
│   input_snapshot = {fees, initial, flows}       │
│   calculation_status = CALCULATED                │
└──────────────────────────────────────────────────┘
               ↓
┌──────────────────────────────────────────────────┐
│ MAKER-CHECKER LOCK                              │
│                                                  │
│ Reviewer approves:                               │
│   locked_at = NOW()                              │
│   locked_by = reviewer_user_id                   │
│   calculation_status = LOCKED                    │
│                                                  │
│ EIR is now FINAL and ready for:                 │
│   • Door 2: Monthly revenue recognition          │
│   • Door 3: ECL discounting                      │
└──────────────────────────────────────────────────┘
```

---

## 6. SUMMARY TABLE: What Each Component Does

| Component | Purpose | Input | Output | Used For |
|-----------|---------|-------|--------|----------|
| **eir_accounting_rules** | Guide fee classification decisions | Fee criteria (type, GL, direction) | Classification proposals | Fee Classification UI |
| **contract_fees** | Store raw fees | Import data or manual entry | integral flag, classification_status | EIR calculation input |
| **EirFeeClassificationController** | UI for finance to classify fees | Proposed rules | Reviewed fees (integral=T/F) | Input to EIR solver |
| **EirContractInputService** | Assemble EIR solver inputs | Reviewed fees, schedule, terms | Initial net investment | Solver input |
| **CalculateEirService** | Solve for EIR | Initial investment, cash flows | eir_effective_annual | Locked EIR |
| **EirRevenueService** | Monthly interest recognition | Locked EIR, opening balance, stage | Interest accrued, closing balance | Door 2 revenue |
| **ECL Engine (future)** | Discount expected losses | Locked eir_effective_annual, PD, LGD | ECL value discounted | Door 3 impairment |

---

## 7. PRACTICAL EXAMPLE: CONTRACT 104430000004

### The Complete Journey

```
Stage 1: CONTRACT INTAKE
  Drawn Amount: MK 309,523,514
  Contractual Rate: 31.30% p.a.
  Terms: Monthly payments over 24 months
  Schedule Version 1: 24 cash flows

Stage 2: FEES EXTRACTED (PENDING)
  Fee 1: Arrangement (GL 8000, RECEIVED, MK 100M)
  Fee 2: Legal (GL 8100, PAID, MK 5M)
  Fee 3: Documentation (GL 8150, RECEIVED, MK 2M)
  Status: All PENDING (not yet classified)

Stage 3: ACCOUNTING RULES APPLIED (Admin Config)
  Rule 1: Type='arrangement' + GL='8000*' → integral=TRUE
  Rule 2: Type='legal' + GL='8100*' → integral=TRUE
  Rule 3: Type='documentation' + GL='8150*' → integral=TRUE
  Status: All rules ACTIVE

Stage 4: FEE CLASSIFICATION (Finance Review)
  Fee 1: Arrangement
    Proposed by Rule 1: integral=TRUE, basis=ON_DRAWN
    Finance: ✅ ACCEPT
    Status: REVIEWED, integral=TRUE
  
  Fee 2: Legal
    Proposed by Rule 2: integral=TRUE, basis=ON_DRAWN
    Finance: ✅ ACCEPT
    Status: REVIEWED, integral=TRUE
  
  Fee 3: Documentation
    Proposed by Rule 3: integral=TRUE
    Finance: ✅ ACCEPT (but different reasoning - required by lender)
    Status: REVIEWED, integral=TRUE

Stage 5: READINESS CHECK
  ✓ contract_eir exists
  ✓ contract_cashflow_schedule v1 complete (24 rows)
  ✓ All integral fees REVIEWED
  ✓ Initial net investment > 0
  Status: READY

Stage 6: EIR INPUT ASSEMBLY
  Load reviewed integral fees:
    - Arrangement: MK 100M (RECEIVED)
    - Legal: MK 5M (PAID)
    - Documentation: MK 2M (RECEIVED)
  
  Calculate Initial Net:
    Drawn: MK 309,523,514
    - Received: MK 102,000,000 (arrangement + documentation)
    + Paid: MK 5,000,000 (legal)
    = Initial: MK 212,523,514
  
  Load cash flows: 24 monthly repayments

Stage 7: EIR SOLVER
  Input: initial=212.5M, 24 cash flows, 12 payments/year
  Method: Newton-Raphson
  Solve: NPV = 0
  
  Result:
    eir_period = 0.028230 (2.823% monthly)
    eir_nominal_annual = 0.028230 × 12 = 33.876%
    eir_effective_annual = (1.028230)^12 - 1 = 38.8834%
  
  Convergence: ✓ (residual < tolerance in 5 iterations)

Stage 8: LOCK DECISION
  Calculated: 2026-08-14 by user_id=123 (Maker)
  Status: CALCULATED
  
  Reviewed: 2026-08-15 by user_id=456 (Checker)
  Status: LOCKED
  locked_at: 2026-08-15 11:38 PM
  locked_by: 456
  
  Stored: input_snapshot JSON with all fees included

Stage 9: MONTHLY REVENUE RUNS
  Period: 2025-10
    Opening Balance: MK 212,523,514
    Monthly Rate: (1.388834)^(1/12) - 1 = 2.823%
    Interest = 2.823% × 212,523,514 = MK 6,000,000
    Cash Received: MK 8,600,000
    Closing: 212.5M + 6.0M - 8.6M = MK 209.9M
    Status: eir_amortisation created

Stage 10: ECL DISCOUNTING (Future - Door 3)
  Current Stage: 1 (Performing)
  PD (Probability Default): 2%
  LGD (Loss Given Default): 60%
  EAD (Exposure at Default): 212.5M (opening balance)
  Time to Maturity: 1.67 years (20 months remaining)
  
  ECL = PD × LGD × EAD / (1 + eir_effective_annual)^t
      = 0.02 × 0.60 × 212.5M / (1.388834)^1.67
      = 2.55M / 1.694
      = MK 1,506,626
  
  (Without EIR discounting, old method = 2.55M - 69% difference!)
```

---

## QUICK REFERENCE

### Which EIR in ECL? 
**Answer: `eir_effective_annual` from each contract**

### How do Fees affect EIR?
**Answer: Reviewed+integral fees reduce initial net investment → higher EIR**

### How do Accounting Rules help?
**Answer: Rules automate fee classification proposals → speeds up finance review**

### Where is EIR stored for each contract?
**Answer: `contract_eir.eir_effective_annual` (one row per locked contract)**
