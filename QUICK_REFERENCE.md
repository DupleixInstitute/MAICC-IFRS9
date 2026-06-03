# MAICC-IFRS9 Quick Reference Guide

## 🎯 What is This System?

**MAICC-IFRS9** is an **IFRS 9 Compliant Credit Risk Management System** that helps financial institutions:
- Calculate **Expected Credit Loss (ECL)** provisions
- Manage **loan portfolios** and **credit risk**
- Perform **credit scoring** and **risk assessment**
- Generate **regulatory reports**

---

## 📊 System Architecture (Visual)

```
┌─────────────────────────────────────────────────────────────┐
│                    MAICC-IFRS9 SYSTEM                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │   Clients    │  │    Loans     │  │   Scoring    │    │
│  │              │  │              │  │              │    │
│  │ • Individual │  │ • Products   │  │ • Attributes │    │
│  │ • Corporate  │  │ • Applications│  │ • Formulas  │    │
│  │ • Financials │  │ • Approvals  │  │ • Bands     │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │           IFRS 9 COMPLIANCE ENGINE                  │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │                                                     │  │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐         │  │
│  │  │    PD    │  │   LGD    │  │   EAD    │         │  │
│  │  │ (Prob of │  │ (Loss    │  │(Exposure │         │  │
│  │  │ Default) │  │  Given   │  │   at     │         │  │
│  │  │          │  │ Default) │  │ Default) │         │  │
│  │  └────┬─────┘  └────┬─────┘  └────┬─────┘         │  │
│  │       │             │             │                │  │
│  │       └─────────────┴─────────────┘                │  │
│  │                     │                              │  │
│  │              ┌──────▼──────┐                       │  │
│  │              │     ECL      │                       │  │
│  │              │ Calculation  │                       │  │
│  │              └──────────────┘                       │  │
│  │                                                     │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │    SICR      │  │  Macro/FLI   │  │   Reports    │    │
│  │              │  │              │  │              │    │
│  │ • Triggers   │  │ • Scenarios  │  │ • ECL        │    │
│  │ • Alerts     │  │ • Forecasts  │  │ • Portfolio  │    │
│  │ • Staging    │  │ • Regression │  │ • Regulatory │    │
│  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔢 IFRS 9 Calculation Flow

```
STEP 1: Data Input
├── Loan Book Import
├── Client Financials
└── Collateral Values

STEP 2: Staging (SICR)
├── Stage 1: Performing (12-month ECL)
├── Stage 2: Underperforming (Lifetime ECL)
└── Stage 3: Non-performing (Lifetime ECL)

STEP 3: PD Calculation
├── Transition Matrix (Historical defaults)
├── Cumulative PD (Multi-period)
└── Forward-Looking Adjustments (Macro)

STEP 4: LGD Calculation
├── Recovery Analysis
├── Collateral Valuation
└── Cure Rates

STEP 5: EAD Calculation
├── Outstanding Balance
├── Undrawn Commitments
└── Credit Conversion Factor

STEP 6: ECL Calculation
ECL = PD × LGD × EAD

STEP 7: Reporting
├── ECL Provisions
├── Stage Migration
└── Regulatory Reports
```

---

## 🗺️ Module Map

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT MANAGEMENT                    │
├─────────────────────────────────────────────────────────┤
│ Controllers: ClientsController (9KB)                    │
│              ClientBalanceSheetController (18KB)        │
│              ClientIncomeController (19KB)              │
│              ClientRatioAnalysisController (27KB)       │
│ Models:      Client, Shareholder, RatioAnalysis        │
│ Routes:      /client/*                                  │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                    LOAN MANAGEMENT                      │
├─────────────────────────────────────────────────────────┤
│ Controllers: LoanApplicationsController (53KB) ⭐       │
│              LoanProductsController (21KB)              │
│              LoanBookController (18KB)                  │
│ Models:      LoanApplication, LoanProduct, LoanBook    │
│ Routes:      /loan_application/*                        │
│              /loan_product/*                            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                   CREDIT SCORING                        │
├─────────────────────────────────────────────────────────┤
│ Controllers: ScoringAttributesController (8KB)          │
│ Models:      ScoringAttribute, LoanApplicationScore    │
│ Routes:      /scoring_attribute/*                       │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│              TRANSITION MATRIX (PD)                     │
├─────────────────────────────────────────────────────────┤
│ Controllers: TransitionMatrixController (30KB)          │
│              TransitionMatrixCummulativeController      │
│ Models:      TransitionMatrix, TransitionMatrixEntry   │
│ Routes:      /transition-matrix/*                       │
│              /transition-matrix-cummulative/*           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│              LOSS GIVEN DEFAULT (LGD)                   │
├─────────────────────────────────────────────────────────┤
│ Controllers: LossGiveDefaultController (27KB)           │
│              LossGivenDefaultCummulativeController      │
│ Models:      LossGivenDefault, CollateralRegister      │
│ Routes:      /loss-given-default/*                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│           EXPECTED CREDIT LOSS (ECL)                    │
├─────────────────────────────────────────────────────────┤
│ Controllers: ExpectedCreditLossController (10KB)        │
│ Models:      ExpectedCreditLoss, ReportingPeriods      │
│ Routes:      /expected-credit-loss/*                    │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                        SICR                             │
├─────────────────────────────────────────────────────────┤
│ Controllers: SicrGroupController, SicrItemController    │
│              SicrTriggerController (8KB)                │
│ Models:      SicrGroup, SicrItem, SicrTrigger          │
│ Routes:      /sicr-groups/*, /sicr-items/*             │
│              /sicr-triggers/*                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│            FORWARD-LOOKING INFORMATION                  │
├─────────────────────────────────────────────────────────┤
│ Controllers: MacroStatsController                       │
│              ScenariosController                        │
│              RegressionController                       │
│ Models:      MacroStatsDefinition, Scenarios           │
│              RegressionModel                            │
│ Routes:      /macro-statistics/*                        │
│              /scenarios/*                               │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                   REPORTS & ANALYTICS                   │
├─────────────────────────────────────────────────────────┤
│ Controllers: ReportsController (227KB) ⭐⭐⭐            │
│              DashboardController (38KB)                 │
│ Routes:      /report/*                                  │
│              /dashboard                                 │
└─────────────────────────────────────────────────────────┘
```

---

## 📁 File Size Reference

### Largest Controllers (Top 10)
```
1. ReportsController.php          227 KB  ⭐⭐⭐
2. LoanApplicationsController.php  53 KB  ⭐⭐
3. DashboardController.php         38 KB  ⭐
4. TransitionMatrixController.php  30 KB
5. ClientRatioAnalysisController.php 27 KB
6. LossGiveDefaultController.php   27 KB
7. LoanProductsController.php      21 KB
8. InvoicesController.php          21 KB
9. ClientIncomeController.php      19 KB
10. ClientBalanceSheetController.php 18 KB
```

### Model Count
- **Total Models:** 83
- **Key Models:** Client, LoanApplication, LoanProduct, TransitionMatrix, LossGivenDefault, ExpectedCreditLoss

### Route Count
- **Total Routes:** 841 lines in web.php
- **Main Route Groups:** 15+

---

## 🔑 Key Concepts

### IFRS 9 Stages
```
Stage 1: Performing Loans
├── No significant credit deterioration
├── 12-month ECL
└── Most loans start here

Stage 2: Underperforming Loans
├── Significant increase in credit risk (SICR)
├── Lifetime ECL
└── Moved from Stage 1 when SICR detected

Stage 3: Non-Performing Loans
├── Credit-impaired (defaulted)
├── Lifetime ECL
└── Interest revenue on net carrying amount
```

### ECL Formula
```
ECL = PD × LGD × EAD

Where:
PD  = Probability of Default (%)
LGD = Loss Given Default (%)
EAD = Exposure at Default (Amount)

Example:
PD  = 5% (5% chance of default)
LGD = 40% (40% loss if default occurs)
EAD = $100,000 (loan amount)
ECL = 0.05 × 0.40 × $100,000 = $2,000
```

### Transition Matrix
```
A matrix showing loan migrations between risk grades:

From/To │  AAA  │  AA   │  A    │  BBB  │ Default
────────┼───────┼───────┼───────┼───────┼────────
AAA     │ 90%   │  8%   │  1%   │  0.5% │  0.5%
AA      │  5%   │ 85%   │  7%   │  2%   │  1%
A       │  2%   │  5%   │ 80%   │ 10%   │  3%
BBB     │  1%   │  2%   │  5%   │ 80%   │ 12%

PD = Probability of migrating to Default column
```

---

## 🚀 Common Tasks

### Create a New Loan Application
```
1. Navigate to /loan_application/create
2. Select client
3. Select loan product
4. Enter loan amount
5. Complete scoring attributes
6. Submit for approval
```

### Calculate ECL for a Period
```
1. Ensure loan book is imported
2. Calculate PD (Transition Matrix)
3. Calculate LGD (Loss Given Default)
4. Run ECL calculation
5. Generate reports
```

### Import Loan Book
```
1. Navigate to /loan_application/loan-books/import
2. Download sample template
3. Fill in loan data
4. Upload Excel file
5. Review validation errors
6. Confirm import
```

### Configure Scoring Attributes
```
1. Navigate to /scoring_attribute
2. Create attribute groups
3. Add attributes to groups
4. Define scoring rules
5. Link to loan products
```

---

## 🛠️ Development Workflow

### Making Changes
```bash
# 1. Pull latest changes
git pull origin main

# 2. Create feature branch
git checkout -b feature/your-feature

# 3. Make changes
# Edit files...

# 4. Test locally
php artisan serve
npm run dev

# 5. Commit changes
git add .
git commit -m "Description"

# 6. Push to remote
git push origin feature/your-feature

# 7. Create pull request
```

### Running Migrations
```bash
# Create migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration (WARNING: Deletes all data)
php artisan migrate:fresh --seed
```

### Debugging
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# View routes
php artisan route:list

# Tinker (Laravel REPL)
php artisan tinker
```

---

## 📚 Learning Resources

### IFRS 9 Concepts
- **Official Standard:** https://www.ifrs.org/issued-standards/list-of-standards/ifrs-9-financial-instruments/
- **ECL Guide:** Search for "IFRS 9 ECL calculation guide"
- **PD/LGD/EAD:** Search for "credit risk parameters"

### Laravel
- **Documentation:** https://laravel.com/docs/10.x
- **Eloquent ORM:** https://laravel.com/docs/10.x/eloquent
- **Inertia.js:** https://inertiajs.com

### Vue.js
- **Documentation:** https://vuejs.org
- **Composition API:** https://vuejs.org/guide/extras/composition-api-faq.html

---

## ⚠️ Important Notes

### Performance
- Always use eager loading (`->with()`)
- Never put database queries in `$appends` accessors
- Use caching for expensive calculations
- Monitor query count with Laravel Debugbar

### Security
- All routes require authentication
- Permission checks on sensitive operations
- Never expose sensitive data in responses
- Validate all user inputs

### Data Integrity
- Use database transactions for multi-step operations
- Implement soft deletes for important data
- Maintain audit trails (activity log)
- Regular database backups

---

## 🆘 Troubleshooting

### Common Issues

**Issue:** "Maximum execution time exceeded"  
**Solution:** Check for N+1 queries, use eager loading

**Issue:** "Settings not found"  
**Solution:** Run `php artisan db:seed --class=SettingsTableSeeder`

**Issue:** "Permission denied"  
**Solution:** Check user roles and permissions

**Issue:** "Class not found"  
**Solution:** Run `composer dump-autoload`

**Issue:** "Vite manifest not found"  
**Solution:** Run `npm run build` or `npm run dev`

---

## 📞 Quick Links

- **Codebase Index:** `CODEBASE_INDEX.md`
- **Performance Fix:** `PERFORMANCE_FIX.md`
- **Database Seeding:** `DATABASE_SEEDING_FIX.md`
- **Complete Fix Summary:** `COMPLETE_FIX_SUMMARY.md`

---

**Last Updated:** 2025-11-26  
**Quick Reference Version:** 1.0
