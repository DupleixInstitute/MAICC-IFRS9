# MAICC-IFRS9 Codebase Index & Documentation

## 📋 Project Overview

**Project Name:** MAICC-IFRS9 (TNMMpamba Client Management System)  
**Purpose:** IFRS 9 Compliant Credit Risk Management & Expected Credit Loss (ECL) Calculation System  
**Tech Stack:** Laravel 10 + Vue.js 3 + Inertia.js + Tailwind CSS  
**Database:** MySQL 5.7  
**PHP Version:** 8.1+

---

## 🎯 Core Business Domain

This is a **comprehensive financial risk management system** specifically designed for:
- **IFRS 9 Compliance** - International Financial Reporting Standard 9
- **Expected Credit Loss (ECL) Calculation**
- **Credit Risk Assessment & Scoring**
- **Loan Portfolio Management**
- **Client/Customer Management**

---

## 🏗️ Architecture Overview

### Technology Stack

```
Frontend:
├── Vue.js 3 (Composition API)
├── Inertia.js (Server-side rendering)
├── Tailwind CSS (Styling)
└── Ziggy (Route generation)

Backend:
├── Laravel 10
├── Laravel Jetstream (Authentication)
├── Laravel Fortify (Backend auth)
├── Spatie Permissions (RBAC)
└── Spatie Activity Log (Audit trail)

Database:
└── MySQL 5.7

Additional Tools:
├── Laravel Debugbar (Development)
├── Laravel Telescope (Monitoring)
├── Maatwebsite Excel (Import/Export)
├── DomPDF (PDF Generation)
├── Pusher/WebSockets (Real-time)
└── Barcode Generation
```

---

## 📁 Directory Structure

```
MAICC-IFRS9/
├── app/
│   ├── Http/
│   │   ├── Controllers/      # 78+ controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form requests
│   ├── Models/               # 83 Eloquent models
│   ├── Services/             # Business logic
│   ├── Helpers/              # Helper functions
│   └── Events/               # Event classes
├── database/
│   ├── migrations/           # Database schema
│   ├── seeders/              # Data seeders
│   └── factories/            # Model factories
├── resources/
│   ├── js/
│   │   ├── Components/       # Vue components
│   │   ├── Layouts/          # Page layouts
│   │   ├── Pages/            # Inertia pages
│   │   └── Jetstream/        # UI components
│   ├── css/                  # Stylesheets
│   └── views/                # Blade templates
├── routes/
│   ├── web.php               # 841 lines of routes
│   ├── api.php               # API routes
│   └── channels.php          # Broadcasting
├── public/                   # Public assets
├── storage/                  # File storage
└── tests/                    # Test suites
```

---

## 🗂️ Core Modules

### 1. **Client Management**
**Purpose:** Manage corporate and individual clients

**Key Models:**
- `Client.php` - Main client entity
- `ClientLoginDetails.php` - Portal access
- `Shareholder.php` - Corporate shareholders
- `RatioAnalysis.php` - Financial ratios

**Controllers:**
- `ClientsController.php` - CRUD operations
- `ClientBalanceSheetController.php` - Financial statements
- `ClientIncomeController.php` - Income statements
- `ClientRatioAnalysisController.php` - Ratio calculations
- `ClientShareholdersController.php` - Shareholder management
- `ClientPotersController.php` - Porter's Five Forces analysis

**Features:**
- Individual & Corporate client types
- Financial data management (Balance Sheet, Income Statement)
- Ratio analysis & benchmarking
- Shareholder tracking
- Porter's Five Forces industry analysis
- Document management
- Notes & comments

---

### 2. **Loan Management**

**Purpose:** Manage loan applications, products, and portfolios

**Key Models:**
- `LoanApplication.php` - Loan applications
- `LoanProduct.php` - Loan product definitions
- `LoanBook.php` - Active loans
- `LoanPortfolio.php` - Portfolio groupings
- `LoanApprovalStage.php` - Approval workflow
- `LoanApplicationLinkedApprovalStage.php` - Application stages

**Controllers:**
- `LoanApplicationsController.php` (53KB - largest controller)
- `LoanProductsController.php`
- `LoanBookController.php`
- `LoanPortfoliosController.php`
- `LoanApprovalStagesController.php`

**Features:**
- Multi-stage approval workflow
- Credit scoring system
- Loan product configuration
- Portfolio segmentation
- Guarantor management
- Contract management
- Collateral tracking

---

### 3. **Credit Scoring System**

**Purpose:** Automated credit risk assessment

**Key Models:**
- `ScoringAttribute.php` - Scoring criteria
- `ScoringAttributeGroup.php` - Grouped attributes
- `LoanApplicationScore.php` - Application scores
- `LoanProductScoringAttribute.php` - Product-specific scoring
- `LoanApplicationBand.php` - Risk bands

**Controllers:**
- `ScoringAttributesController.php`

**Features:**
- Configurable scoring attributes
- Formula-based calculations
- Ratio-based scoring
- Shareholder analysis scoring
- Industry analysis scoring
- Weighted scoring system
- Risk band classification

---

### 4. **IFRS 9 Compliance - Transition Matrix**

**Purpose:** Calculate Probability of Default (PD) using transition matrices

**Key Models:**
- `TransitionMatrix.php` - PD calculations
- `TransitionMatrixEntry.php` - Matrix entries
- `TransitionMatrixData.php` - Calculated data
- `TransitionMatrixCummulative.php` - Cumulative PD
- `TransitionProfileDefinition.php` - Profile configurations
- `TransitionProfileOption.php` - Profile options

**Controllers:**
- `TransitionMatrixController.php` (30KB)
- `TransitionMatrixCummulativeController.php`
- `TransitionProfileDefinitionController.php`
- `TransitionProfileOptionController.php`

**Features:**
- Point-in-time (PIT) PD calculation
- Through-the-cycle (TTC) PD calculation
- Cumulative PD calculation
- Transition profile management
- Loan book integration
- Lock/unlock mechanism for approved calculations

---

### 5. **IFRS 9 Compliance - Loss Given Default (LGD)**

**Purpose:** Calculate recovery rates and loss severity

**Key Models:**
- `LossGivenDefault.php` - LGD calculations
- `LossGivenDefaultCummulative.php` - Cumulative LGD
- `CollateralRegister.php` - Collateral tracking
- `CollateralAllocation.php` - Collateral assignments

**Controllers:**
- `LossGiveDefaultController.php` (27KB)
- `LossGivenDefaultCummulativeController.php`
- `CollateralController.php`

**Features:**
- System-calculated LGD
- Manual LGD entry
- Cumulative LGD calculation
- Collateral valuation
- Recovery rate analysis
- Lock/unlock mechanism

---

### 6. **IFRS 9 Compliance - Expected Credit Loss (ECL)**

**Purpose:** Calculate final ECL provisions

**Key Models:**
- `ExpectedCreditLoss.php` - ECL calculations
- `ReportingPeriods.php` - Reporting periods
- `StageingRule.php` - Staging rules

**Controllers:**
- `ExpectedCreditLossController.php`
- `FinancialPeriodController.php`
- `StageingRulesController.php`

**Features:**
- 12-month ECL (Stage 1)
- Lifetime ECL (Stage 2 & 3)
- Staging automation
- Period-based calculations
- ECL reporting

---

### 7. **IFRS 9 Compliance - SICR (Significant Increase in Credit Risk)**

**Purpose:** Identify and manage credit deterioration

**Key Models:**
- `SicrGroup.php` - SICR groups
- `SicrItem.php` - SICR criteria
- `SicrTrigger.php` - Triggered alerts

**Controllers:**
- `SicrGroupController.php`
- `SicrItemController.php`
- `SicrTriggerController.php`

**Features:**
- Quantitative triggers (DPD, PD changes)
- Qualitative triggers (forbearance, restructuring)
- Alert management
- Loan book integration
- Trigger history

---

### 8. **Forward-Looking Information (FLI)**

**Purpose:** Incorporate macroeconomic forecasts into ECL

**Key Models:**
- `MacroStatsDefinition.php` - Macro variables
- `MacroStatsValue.php` - Historical & forecast data
- `Scenarios.php` - Economic scenarios
- `ScenarioProfiles.php` - Scenario configurations
- `MacroForecastWeighted.php` - Weighted forecasts
- `RegressionModel.php` - PD regression models
- `RegressionPrediction.php` - Predictions

**Controllers:**
- `MacroStatsController.php`
- `MacroStatsValueController.php`
- `ScenariosController.php`
- `MacroForecastWeightedController.php`
- `RegressionController.php`
- `CreditLossDataController.php`

**Features:**
- Macro variable management (GDP, inflation, etc.)
- Multi-scenario modeling (Base, Optimistic, Pessimistic)
- Probability-weighted forecasts
- Regression analysis
- Credit loss correlation

---

### 9. **Reporting & Analytics**

**Purpose:** Generate regulatory and management reports

**Key Controllers:**
- `ReportsController.php` (227KB - LARGEST file!)
- `DashboardController.php` (38KB)

**Features:**
- ECL summary reports
- Stage migration reports
- Portfolio analysis
- Vintage analysis
- Concentration reports
- Regulatory reports
- PDF export
- Excel export

---

### 10. **User & Permission Management**

**Purpose:** Role-based access control

**Key Models:**
- `User.php` - System users
- Uses Spatie Permission package

**Controllers:**
- `UsersController.php`
- `RolesController.php`

**Features:**
- Role-based permissions
- User management
- Activity logging
- Member portal access

---

### 11. **Communication System**

**Purpose:** Automated client communications

**Key Models:**
- `CommunicationCampaign.php`
- `CommunicationTemplate.php`
- `CommunicationLog.php`
- `SmsGateway.php`

**Controllers:**
- `CommunicationCampaignController.php`
- `CommunicationTemplateController.php`
- `CommunicationLogController.php`
- `SmsGatewaysController.php`

**Features:**
- SMS campaigns
- Email campaigns
- Template management
- Campaign scheduling
- Business rules
- Delivery tracking

---

### 12. **Data Import System**

**Purpose:** Bulk data import functionality

**Key Models:**
- `Import.php`
- `GeneralImportConfiguration.php`
- `GeneralImportTemplate.php`

**Controllers:**
- `GeneralImportController.php`
- `ClientImportController.php`
- `ImportsController.php`

**Features:**
- Excel import
- CSV import
- Template-based import
- Validation
- Error reporting
- Failed record download

---

### 13. **Reference Data Management**

**Purpose:** Manage lookup tables and configurations

**Key Models:**
- `Branch.php` - Branches
- `Currency.php` - Currencies
- `Country.php` - Countries
- `Province.php`, `District.php`, `Ward.php`, `Village.php` - Locations
- `IndustryType.php` - Industry classifications
- `LegalType.php` - Legal structures
- `Bank.php` - Banks
- `ChartOfAccount.php` - Accounting

**Controllers:**
- `BranchesController.php`
- `CurrenciesController.php`
- `ProvincesController.php`, `DistrictsController.php`, etc.
- `IndustryTypesController.php`
- `LegalTypesController.php`
- `BanksController.php`
- `ChartOfAccountController.php`

---

### 14. **Settings & Configuration**

**Purpose:** System-wide settings

**Key Models:**
- `Setting.php`
- `Timezone.php`

**Controllers:**
- `SettingsController.php`

**Features:**
- Company information
- Email configuration
- SMS configuration
- System preferences
- Loan band configuration

---

## 🔐 Authentication & Authorization

### Authentication
- **Laravel Jetstream** - Full auth scaffolding
- **Laravel Fortify** - Backend authentication
- **Two-factor authentication** support
- **Session management**

### Authorization
- **Spatie Laravel Permission** - RBAC
- **128 Permissions** (seeded)
- **2+ Roles** (seeded)
- **Middleware protection** on all routes

---

## 🗄️ Database Schema

### Key Tables (83 total)

**Client Management:**
- `clients` - Client master data
- `shareholders` - Corporate shareholders
- `ratio_analyses` - Financial ratios
- `balance_sheets` - Balance sheet data
- `balance_sheet_data` - Line items
- `income_statements` - Income statement data
- `income_statement_data` - Line items
- `porter_five_forces_analyses` - Industry analysis

**Loan Management:**
- `loan_applications` - Applications
- `loan_products` - Product definitions
- `loan_books` - Active loans
- `loan_portfolios` - Portfolio groupings
- `loan_approval_stages` - Workflow stages
- `loan_applications_linked_approval_stages` - Application workflow
- `loan_application_scores` - Scoring results
- `contracts` - Loan contracts
- `collateral_registers` - Collateral
- `collateral_allocations` - Collateral assignments

**IFRS 9 Calculations:**
- `transition_matrices` - PD calculations
- `transition_matrix_entries` - Matrix data
- `transition_matrix_cummulatives` - Cumulative PD
- `loss_given_defaults` - LGD calculations
- `loss_given_default_cummulatives` - Cumulative LGD
- `expected_credit_losses` - ECL results
- `reporting_periods` - Reporting periods
- `stageing_rules` - Staging criteria
- `finance_sicr_groups` - SICR groups
- `finance_sicr_items` - SICR criteria
- `finance_sicr_triggers` - SICR alerts

**Forward-Looking Information:**
- `macro_statistics` - Macro variable definitions
- `macro_stats_values` - Historical & forecast data
- `scenarios` - Economic scenarios
- `scenario_profiles` - Scenario configurations
- `macro_forecast_weighted` - Weighted forecasts
- `regression_models` - PD models
- `regression_predictions` - Predictions
- `macro_credit_loss_data` - Credit loss data
- `macro_credit_loss_definitions` - Variable definitions

**Reference Data:**
- `branches`
- `currencies`
- `countries`
- `provinces`, `districts`, `wards`, `villages`
- `industry_types`
- `legal_types`
- `banks`
- `chart_of_accounts`

**System:**
- `users`
- `roles`
- `permissions`
- `role_has_permissions`
- `model_has_roles`
- `settings`
- `activity_log`
- `imports`

---

## 🛣️ Key Routes

### Dashboard
- `GET /` - Home/Dashboard
- `GET /dashboard` - Main dashboard

### Clients
- `GET /client` - List clients
- `POST /client/store` - Create client
- `GET /client/{client}/show` - View client
- `PUT /client/{client}/update` - Update client
- `GET /client/{client}/balance_sheet` - Balance sheets
- `GET /client/{client}/income_statement` - Income statements
- `GET /client/{client}/ratio_analysis` - Ratios
- `GET /client/{client}/shareholder` - Shareholders

### Loan Applications
- `GET /loan_application` - List applications
- `POST /loan_application/store` - Create application
- `GET /loan_application/{application}/show` - View application
- `POST /loan_application/{application}/change_status` - Update status
- `POST /loan_application/{application}/assign_approver` - Assign approver

### Loan Products
- `GET /loan_product` - List products
- `POST /loan_product/store` - Create product
- `POST /loan_product/{product}/sync_attributes` - Sync scoring

### Transition Matrix
- `GET /transition-matrix` - List matrices
- `POST /transition-matrix/store` - Create matrix
- `GET /transition-matrix/{matrix}/show` - View matrix
- `POST /transition-matrix/{matrix}/rerun` - Recalculate
- `POST /transition-matrix/{matrix}/lock-pd` - Lock calculation

### LGD
- `GET /loss-given-default/list` - List LGD
- `POST /loss-given-default/calculations` - Calculate LGD
- `POST /loss-given-default/manual-calculation` - Manual entry
- `POST /loss-given-default/{id}/lock` - Lock calculation

### ECL
- `GET /expected-credit-loss/list` - List ECL
- `POST /expected-credit-loss/calculations` - Calculate ECL
- `GET /expected-credit-loss/reports` - Export reports

### SICR
- `GET /sicr-groups` - List groups
- `GET /sicr-items` - List items
- `GET /sicr-triggers` - List triggers
- `POST /sicr-triggers/{trigger}/update-loan-book` - Update loan book

### Macro/FLI
- `GET /macro-statistics` - List variables
- `GET /macro-statistics/{stat}/values` - Variable values
- `GET /scenarios/{id}` - List scenarios
- `GET /macro-forecast-weighted` - Weighted forecasts
- `GET /credit-loss-data` - Credit loss data

### Reports
- `GET /report` - Reports index

---

## 🔧 Key Technologies & Packages

### Laravel Packages
```json
{
  "barryvdh/laravel-dompdf": "PDF generation",
  "beyondcode/laravel-websockets": "Real-time features",
  "doctrine/dbal": "Database abstraction",
  "inertiajs/inertia-laravel": "SPA without API",
  "laravel/fortify": "Authentication backend",
  "laravel/jetstream": "Auth scaffolding",
  "laravel/sanctum": "API authentication",
  "maatwebsite/excel": "Excel import/export",
  "spatie/laravel-activitylog": "Audit trail",
  "spatie/laravel-permission": "RBAC",
  "yajra/laravel-datatables": "DataTables"
}
```

### Frontend Packages
```json
{
  "vue": "^3.x",
  "@inertiajs/vue3": "Inertia Vue adapter",
  "tailwindcss": "CSS framework",
  "autoprefixer": "CSS processing",
  "postcss": "CSS processing"
}
```

---

## 📊 Performance Optimizations Applied

### Recent Fixes (2025-11-26)

1. **N+1 Query Problem Fixed**
   - Removed auto-appended accessors from `LoanApplication` model
   - Removed auto-appended accessors from `LoanApplicationLinkedApprovalStage` model
   - Fixed `getApproverNameAttribute()` to use relationships
   - **Result:** Query count reduced from 60+ to ~5-10

2. **Null-Safe Operators Added**
   - Updated `HandleInertiaRequests` middleware
   - Added null coalescing operators for all settings
   - **Result:** Prevents crashes when settings missing

3. **Database Seeding**
   - Seeded `SettingsTableSeeder`
   - Seeded `CurrenciesTableSeeder`
   - **Result:** Application loads without errors

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.1+
- Composer
- Node.js & NPM
- MySQL 5.7+

### Installation
```bash
# Clone repository
git clone [repository-url]
cd MAICC-IFRS9

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
# DB_DATABASE=maicc_ifrs9
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Build assets
npm run dev

# Start server
php artisan serve
```

### Default Credentials
Check `UsersTableSeeder.php` for default admin credentials.

---

## 📝 Development Guidelines

### Code Style
- Follow PSR-12 coding standards
- Use Laravel best practices
- Use Eloquent relationships over raw queries
- Always use eager loading to prevent N+1 queries

### Performance
- **Never** put database queries in model accessors that are in `$appends`
- Always use `->with()` for eager loading
- Use null-safe operators (`?->`) for optional relationships
- Cache expensive calculations

### Security
- All routes protected by authentication middleware
- Permission checks on sensitive operations
- CSRF protection enabled
- SQL injection prevention via Eloquent

---

## 🐛 Known Issues & Fixes

### Issue 1: Maximum Execution Time
**Status:** ✅ FIXED  
**Cause:** N+1 queries in model accessors  
**Fix:** Removed auto-appended accessors, use relationships  
**File:** `PERFORMANCE_FIX.md`

### Issue 2: Missing Settings
**Status:** ✅ FIXED  
**Cause:** Empty settings table  
**Fix:** Run `SettingsTableSeeder`  
**File:** `DATABASE_SEEDING_FIX.md`

---

## 📚 Additional Documentation

- `PERFORMANCE_FIX.md` - N+1 query optimization details
- `DATABASE_SEEDING_FIX.md` - Database seeding guide
- `COMPLETE_FIX_SUMMARY.md` - Summary of all fixes
- `README.md` - Installation guide
- `WARP.md` - Project-specific documentation

---

## 🔍 Quick Reference

### Find a Feature
- **Client Management:** `app/Http/Controllers/ClientsController.php`
- **Loan Applications:** `app/Http/Controllers/LoanApplicationsController.php`
- **Credit Scoring:** `app/Http/Controllers/ScoringAttributesController.php`
- **PD Calculation:** `app/Http/Controllers/TransitionMatrixController.php`
- **LGD Calculation:** `app/Http/Controllers/LossGiveDefaultController.php`
- **ECL Calculation:** `app/Http/Controllers/ExpectedCreditLossController.php`
- **SICR Management:** `app/Http/Controllers/SicrTriggerController.php`
- **Macro Variables:** `app/Http/Controllers/MacroStatsController.php`
- **Reports:** `app/Http/Controllers/ReportsController.php`

### Find a Model
All models are in `app/Models/` directory (83 files)

### Find a Route
All routes are in `routes/web.php` (841 lines)

### Find a Vue Component
All components are in `resources/js/` directory

---

## 📞 Support

For issues or questions, refer to:
1. Laravel documentation: https://laravel.com/docs
2. Vue.js documentation: https://vuejs.org
3. Inertia.js documentation: https://inertiajs.com
4. IFRS 9 standard: https://www.ifrs.org

---

**Last Updated:** 2025-11-26  
**Version:** 1.0  
**Maintained By:** Development Team
