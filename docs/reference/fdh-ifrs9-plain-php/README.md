# FDH Bank — IFRS 9 Credit Risk Model System

## Overview

This system is FDH Bank's in-house IFRS 9 Expected Credit Loss (ECL) computation platform. It ingests monthly loan-book data, applies forward-looking macro-economic adjustments, and produces fully audited ECL figures across all Basel/IFRS 9 stages (Stage 1, 2 and 3) for every portfolio segment.

---

## What the System Does

| Module | Purpose |
|--------|---------|
| **Monthly Data Imports** | Loads term loans, overdrafts, excess loans, collaterals, repayments, ageing reports and customers master from core banking CSV/TXT extracts |
| **Reporting Period Management** | Creates, locks and audits fiscal periods; prevents imports/edits on locked periods |
| **PD Modelling** | Calculates 12-month and lifetime Probability of Default via transition matrices (balance and count methods) |
| **LGD Modelling** | Computes Loss Given Default using recovery and cure rates per segment |
| **EAD Modelling** | Derives Exposure at Default from outstanding balances, limits and collateral values |
| **Forward-Looking Information (FLI)** | Applies macro-economic scenario weights (Base, Optimistic, Pessimistic) to adjust PD/LGD factors using regression analysis |
| **ECL Computation** | Combines PD × LGD × EAD × Discount Factor, weighted by scenario probabilities, to produce IFRS 9-compliant ECL |
| **Stage Assessment** | Classifies every account as Stage 1, 2 or 3 based on significant credit risk increase (SCRI) triggers |
| **Regression Analysis** | Builds statistical models linking macro variables (GDP, inflation, exchange rate) to credit loss proxies |
| **ECL Reports & Dashboard** | Delivers summarised and account-level ECL reports, charts and custom exports |
| **Audit Logs** | Records every import event, user action and system change with timestamps |

---

## System Architecture

```
Core Banking System
      │
      ▼  (CSV / TXT extracts)
┌─────────────────────────────────┐
│   Monthly Input Imports Module  │  ← Term Loans, OD, Excesses,
│   (import/ & monthly_input/)    │    Collaterals, Repayments, FLI
└─────────────┬───────────────────┘
              │
              ▼
┌─────────────────────────────────┐
│   Reporting Period Controller   │  ← Lock/Unlock periods
│   (reporting_periods table)     │    Audit trail
└─────────────┬───────────────────┘
              │
              ▼
┌─────────────────────────────────┐
│   Model Calculators             │
│   PD → LGD → EAD → FLI → ECL   │  ← calculator/
└─────────────┬───────────────────┘
              │
              ▼
┌─────────────────────────────────┐
│   ECL Reports & Dashboard       │  ← reports/ & dashboard/
│   Stage 1 / 2 / 3 Outputs      │
└─────────────────────────────────┘
```

---

## Folder Structure

```
ifrs9/
├── assets/
│   ├── css/                  UI stylesheets
│   ├── includes/             auth_check.php, connection_db.php,
│   │                         enforce_period_lock.php
│   └── js/                   DataTables, SweetAlert, Chart.js etc.
├── audit/                    Data dictionary, import logs
├── calculator/               PD, LGD, regression calculators
├── dashboard/                ECL reports dashboard
├── FLI/                      Forward-Looking Information results
├── import/                   All portfolio import scripts
├── maintenance/              Reporting periods, collateral types,
│                             SCRI triggers, industry codes
├── monthly_input/            Term loans, OD, excess loans,
│                             customers master, PD inputs
├── reports/                  Custom reports
├── admin/                    User management (Admin only)
├── index.php                 Entry point — auth + role injection
├── Index.htm                 Main navigation shell
├── get_reporting_period.php  Period selector (all imports)
├── check_period_lock.php     Period lock AJAX endpoint
└── logout.php                Session destroy + redirect
```

---

## ECL Formula

```
ECL = PD × LGD × EAD × Discount Factor
    weighted across macro scenarios:
    ECL_final = (w_base × ECL_base) + (w_optimistic × ECL_opt) + (w_pessimistic × ECL_pess)
```

Where:
- **PD** — Probability of Default (12-month for Stage 1, lifetime for Stage 2/3)
- **LGD** — Loss Given Default (1 − Recovery Rate − Cure Rate)
- **EAD** — Exposure at Default (Outstanding balance adjusted for CCF)
- **Discount Factor** — Based on effective interest rate
- **Scenario weights** — Assigned by credit risk team per macro outlook

---

## User Roles

| Role | Access |
|------|--------|
| **Admin** | Full access — user management, maintenance, lock/unlock periods |
| **User** | Import data, run calculations, view reports |
| **Viewer** | Read-only — view reports and dashboards only |

---

## Security

- **Authentication** — bcrypt password hashing via `password_hash()` / `password_verify()`
- **Role-based access** — Every page calls `auth_require_role()` before rendering
- **Period locking** — Server-side enforcement (`enforce_period_lock.php`) prevents any import to a locked period
- **Session management** — Session regeneration on login prevents fixation attacks
- **Input validation** — Reporting year/month validated server-side with range checks
- **XSS mitigation** — `htmlspecialchars()` applied to all GET/POST output to HTML

### Known Areas for Hardening (Roadmap)
- Replace `mysqli_real_escape_string()` with prepared statements across all import files
- Add CSRF tokens to all POST forms
- Move database credentials to `.env` outside web root
- Add server-side MIME-type validation on file uploads

---

## Data Flow for May 2025 (202505)

1. Admin creates reporting period `202505` in Reporting Periods Register
2. Data team imports: Term Loans → OD → Excess Loans → Collaterals → Ageing → Repayments
3. Macro team updates FLI scenario forecasts for the period
4. Calculator runs: Stage Assessment → PD → LGD → EAD
5. FLI adjustments applied via regression-linked multipliers
6. ECL computed and saved to results tables
7. Admin locks period `202505` — no further changes allowed
8. Reports generated: ECL by Stage, by Segment, by Product

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Server | PHP 8.x on Apache (XAMPP) |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 4/5, jQuery, DataTables, Chart.js |
| Auth | PHP Sessions with bcrypt |
| File Imports | CSV / pipe-delimited TXT |
| Exports | PDF, Excel, CSV via DataTables Buttons |

---

## Contact

System developed and maintained by Dupleix Institute  
For technical issues contact the System Administrator.
