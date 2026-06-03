# MAICC-IFRS9 Codebase Index

**Generated:** November 26, 2025  
**Repository:** MAICC-IFRS9  
**Branch:** feature-tinashe-changes  
**Owner:** DupleixInstitute

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Core Modules](#core-modules)
5. [Database Architecture](#database-architecture)
6. [API & Routes](#api--routes)
7. [Frontend Components](#frontend-components)
8. [Services & Business Logic](#services--business-logic)
9. [Key Features](#key-features)
10. [Configuration](#configuration)
11. [Development Resources](#development-resources)

---

## Project Overview

**MAICC-IFRS9** is a comprehensive credit management and IFRS9 compliance system built on Laravel 10, designed for financial institutions to manage loan portfolios, assess credit risk, and calculate expected credit losses according to IFRS9 standards.

### Primary Purpose
- Client and loan application management
- Credit scoring and risk assessment
- IFRS9 Expected Credit Loss (ECL) calculations
- Forward-Looking Information (FLI) adjustments
- Stage migration tracking (Stage 1, 2, 3)
- Collateral and portfolio management

---

## Technology Stack

### Backend
- **Framework:** Laravel 10.x
- **PHP Version:** 8.1+
- **Database:** MySQL 5.7+
- **Web Server:** Nginx (Docker) / Apache (XAMPP)
- **Queue System:** Laravel Queue
- **Cache:** Redis/File
- **Session Management:** Database sessions

### Frontend
- **Framework:** Vue.js 3.5.13
- **UI Library:** Inertia.js 0.11.1
- **CSS Framework:** Tailwind CSS 3.1.6
- **Build Tool:** Vite 4.0.0
- **Icons:** Font Awesome 6.7.2, Heroicons
- **Components:** 
  - @headlessui/vue 1.7.23
  - @vueform/multiselect 1.5.0
  - @meforma/vue-toaster 1.3.0
  - TinyMCE Editor
  - FullCalendar

### Key Laravel Packages
- **Jetstream:** Authentication & user management
- **Fortify:** Authentication backend
- **Sanctum:** API authentication
- **Spatie Permission:** Role-based access control
- **Spatie Activity Log:** Audit logging
- **Yajra DataTables:** Server-side datatables
- **Maatwebsite Excel:** Excel import/export
- **DomPDF:** PDF generation
- **Laravel Telescope:** Debugging (dev)
- **LaRecipe:** Documentation

### Additional Dependencies
- **Communication:** Laravel WebSockets, Pusher, Telegram notifications
- **SMS:** BlueDot SMS integration
- **Payment:** Paynow SDK
- **Barcode:** Milon Barcode
- **Math:** Webit Eval Math

---

## Project Structure

```
MAICC-IFRS9/
├── app/
│   ├── Actions/              # Custom action classes
│   ├── Console/              # Artisan commands
│   ├── DataTables/           # DataTable definitions
│   ├── Events/               # Event classes
│   ├── Exceptions/           # Custom exceptions
│   ├── Exports/              # Excel export classes
│   ├── Helpers/              # Helper functions (general.php)
│   ├── Http/
│   │   └── Controllers/      # Application controllers
│   ├── Imports/              # Excel import classes
│   ├── Jobs/                 # Queue jobs
│   ├── Listeners/            # Event listeners
│   ├── Mail/                 # Mailable classes
│   ├── Models/               # Eloquent models
│   ├── Notifications/        # Notification classes
│   ├── Observers/            # Model observers
│   ├── Providers/            # Service providers
│   └── Services/             # Business logic services
├── assets/                   # Legacy static assets
│   ├── css/
│   ├── js/
│   ├── images/
│   └── DataTables/
├── bootstrap/                # Laravel bootstrap
├── config/                   # Configuration files
├── corporate/                # Legacy corporate module (PHP)
├── retail/                   # Legacy retail module
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── docker/                   # Docker configuration
├── public/                   # Public web root
├── resources/
│   ├── css/                  # Stylesheets
│   ├── js/                   # Vue.js application
│   │   ├── Components/       # Vue components
│   │   ├── Layouts/          # Layout components
│   │   ├── Pages/            # Page components
│   │   └── Shared/           # Shared components
│   ├── views/                # Blade templates
│   └── lang/                 # Localization files
├── routes/
│   ├── web.php               # Web routes
│   ├── api.php               # API routes
│   ├── channels.php          # Broadcasting channels
│   ├── console.php           # Console routes
│   └── custom-imports.php    # Custom import routes
├── storage/                  # Application storage
├── tests/                    # Test files
└── vendor/                   # Composer dependencies
```

---

## Core Modules

### 1. Client Management
**Path:** `app/Http/Controllers/ClientsController.php`  
**Model:** `app/Models/Client.php`  
**Views:** `resources/js/Pages/Clients/`

**Features:**
- Client CRUD operations
- Customer ID, name, phone validation
- Industry type classification
- Legal type assignment
- Shareholder management
- Balance sheet & income statement tracking
- Ratio analysis
- Porter's Five Forces analysis
- Financial statements (multiple periods)

**Key Models:**
- `Client.php` - Main client model
- `Shareholder.php` - Shareholder tracking
- `BalanceSheet.php` & `BalanceSheetData.php`
- `IncomeStatement.php` & `IncomeStatementData.php`
- `RatioAnalysis.php`
- `PorterFiveForcesAnalysis.php`

### 2. Loan Management
**Controllers:**
- `LoanProductsController.php` - Loan product definitions
- `LoanApplicationsController.php` - Application processing
- `LoanBooksController.php` - Loan book management
- `ContractsController.php` - Contract management

**Models:**
- `LoanProduct.php` - Product configurations
- `LoanProductCategory.php` - Product grouping
- `LoanApplication.php` - Application data
- `LoanBook.php` - Active loan records
- `Contract.php` - Contract details
- `LoanPortfolio.php` - Portfolio aggregation

**Features:**
- Multi-stage approval workflow
- Credit scoring engine
- Scoring attribute configuration
- Band-based scoring (LoanApplicationBand)
- Application comments & attachments
- Reviewer comments system
- Reminder notifications
- Reassignment capabilities

### 3. Credit Scoring System
**Path:** `app/Http/Controllers/ScoringAttributesController.php`

**Models:**
- `ScoringAttribute.php` - Scoring criteria
- `ScoringAttributeGroup.php` - Attribute grouping
- `LoanProductScoringAttribute.php` - Product-specific scoring
- `LoanProductScoringAttributeOptionValue.php` - Option values
- `LoanApplicationScore.php` - Application scores
php artisan migrate:fresh --seedphp artisan migrate:fresh --seedphp artisan migrate:fresh --seed
**Features:**
- Configurable scoring attributes
- Condition-based scoring
- Ratio-based scoring
- Group weighting
- Dynamic score calculation
- Accept/reject thresholds

### 4. IFRS9 Compliance Module

#### A. Stage Classification
**Controller:** `StageingRulesController.php`  
**Models:**
- `StageingRule.php` - Stage determination rules
- `SicrGroup.php` - Significant Increase in Credit Risk groups
- `SicrItem.php` - SICR criteria items
- `SicrTrigger.php` - SICR trigger definitions

**Features:**
- Stage 1/2/3 classification
- SICR trigger monitoring
- Days past due (DPD) tracking
- Manual stage override
- Stage migration history

#### B. Transition Matrix
**Controller:** `TransitionMatrixController.php`, `TransitionMatrixCummulativeController.php`  
**Service:** `TransitionMatrixService.php`, `TransitionMatrixCummulativeService.php`  
**Models:**
- `TransitionMatrix.php` - Transition matrix definitions
- `TransitionMatrixData.php` - Matrix calculation data
- `TransitionMatrixCummulative.php` - Cumulative matrices
- `TransitionMatrixCummulativeData.php` - Cumulative data
- `TransitionMatrixEntry.php` - Individual entries
- `TransitionProfileDefinition.php` - Profile definitions
- `TransitionProfileOption.php` - Profile options

**Features:**
- Probability of Default (PD) calculation
- Credit rating migration tracking
- Cohort-based analysis
- Multi-period transition matrices
- Cumulative transition calculation
- Profile-based segmentation

#### C. Loss Given Default (LGD)
**Controller:** `LossGiveDefaultController.php`, `LossGivenDefaultCummulativeController.php`

**Models:**
- `LossGivenDefault.php` - LGD calculations
- `LossGivenDefaultCummulative.php` - Cumulative LGD

**Features:**
- Recovery rate analysis
- Collateral consideration
- Historical loss tracking
- Cumulative loss calculation

#### D. Expected Credit Loss (ECL)
**Controller:** `ExpectedCreditLossController.php`  
**Model:** `ExpectedCreditLoss.php`

**Features:**
- ECL calculation engine
- Stage-specific provisions
- 12-month ECL (Stage 1)
- Lifetime ECL (Stage 2 & 3)
- Macro-economic scenario integration

#### E. Forward-Looking Information (FLI)
**Path:** `app/Http/Controllers/FLI/`  
**Controllers:**
- `ScenarioSetController.php`
- `ExternalCalculationsController.php`

**Models:**
- `FliAdj.php` - FLI adjustments
- `FliReportingPeriodParameter.php` - Reporting parameters
- `Scenarios.php` - Economic scenarios
- `ScenarioSet.php` - Scenario groupings
- `ScenarioProbability.php` - Scenario weighting
- `ScenarioProfiles.php` - Scenario profiles
- `MacroForecastWeighted.php` - Weighted forecasts
- `MacroStatsDefinition.php` - Macro statistics
- `MacroStatsValue.php` - Macro values
- `CreditLossData.php` - Credit loss data
- `CreditLossDefinition.php` - Loss definitions

**Features:**
- Multiple scenario modeling
- Probability-weighted ECL
- Macro-economic variable integration
- Custom scenario creation
- Scenario set management
- External calculation import

### 5. Collateral Management
**Controller:** `CollateralController.php`

**Models:**
- `Collateral.php` - Collateral records
- `CollateralType.php` - Collateral classifications
- `CollateralAllocation.php` - Loan-collateral linkage
- `CollateralRegister.php` - Collateral registry

**Features:**
- Collateral valuation
- LGD impact calculation
- Multi-collateral allocation
- Valuation tracking
- Register maintenance

### 6. Import/Export System
**Controllers:**
- `GeneralImportController.php`
- `GeneralImportConfigurationController.php`
- `ClientImportController.php`
- `ImportsController.php`
- `ExcelExportController.php`

**Models:**
- `Import.php` - Import tracking
- `GeneralImportTemplate.php` - Template definitions
- `GeneralImportConfiguration.php` - Import configurations

**Features:**
- Excel import/export
- Template-based imports
- Configuration management
- Failed record tracking
- Bulk data processing
- Progress tracking

### 7. Regression Analysis
**Controller:** `RegressionController.php`  
**Service:** `RegressionService.php`  
**Model:** `RegressionModel.php`, `RegressionPrediction.php`

**Features:**
- Statistical modeling
- PD/LGD prediction
- Model training & validation
- Forecast generation

### 8. Reporting System
**Controller:** `ReportsController.php`

**Models:**
- `ReportingPeriods.php` - Reporting period definitions
- `FinancialPeriod.php` - Financial periods

**Features:**
- Custom report generation
- Period-based reporting
- Export to Excel/PDF
- Dashboard visualizations
- ECL reports
- Portfolio analytics

### 9. Communication System
**Controllers:**
- `CommunicationCampaignController.php`
- `CommunicationTemplateController.php`
- `CommunicationLogController.php`

**Models:**
- `CommunicationCampaign.php` - Campaign management
- `CommunicationTemplate.php` - Message templates
- `CommunicationCampaignLog.php` - Delivery logs
- `CommunicationCampaignBusinessRule.php` - Rules
- `CommunicationCampaignAttachmentType.php` - Attachments
- `SmsGateway.php` - SMS gateway config

**Features:**
- SMS campaigns
- Email notifications
- Template management
- Business rule engine
- Delivery tracking
- Multi-channel support

### 10. User & Access Control
**Controllers:**
- `UsersController.php`
- `RolesController.php`

**Models:**
- `User.php` - User accounts
- Spatie Permission models (Role, Permission)

**Features:**
- Role-based access control (RBAC)
- Permission management
- Branch-based access
- User activity logging
- Two-factor authentication
- First login detection

---

## Database Architecture

### Total Migrations: 130+

### Key Tables

#### Core Business Tables
- `clients` - Client master data
- `loan_applications` - Loan applications
- `loan_books` - Active loan portfolio
- `contracts` - Loan contracts
- `loan_portfolios` - Portfolio aggregation

#### IFRS9 Tables
- `expected_credit_loss` - ECL calculations
- `finance_stageing_rules` - Stage rules
- `finance_sicr_groups` - SICR groups
- `finance_sicr_items` - SICR items
- `finance_sicr_triggers` - SICR triggers
- `transition_matrices` - PD matrices
- `transition_matrices_data` - Matrix data
- `transition_matrix_cummulative` - Cumulative matrices
- `transition_matrix_cummulative_data` - Cumulative data
- `loss_given_default` - LGD calculations
- `loss_given_default_cummulative` - Cumulative LGD

#### FLI & Scenarios
- `fli_adj` - FLI adjustments
- `fli_reporting_periods_parameters` - Period parameters
- `scenarios` - Economic scenarios
- `scenario_sets` - Scenario groupings
- `scenario_probabilities` - Scenario weights
- `scenario_profiles` - Scenario profiles
- `macro_statistics` - Macro definitions
- `macro_statistics_data` - Macro values
- `macro_forecast_weighted` - Weighted forecasts
- `macro_credit_loss_data` - Credit loss data

#### Configuration Tables
- `loan_products` - Product definitions
- `loan_product_categories` - Categories
- `scoring_attributes` - Scoring criteria
- `scoring_attribute_groups` - Attribute groups
- `loan_approval_stages` - Approval workflow
- `collateral_types` - Collateral types
- `settings` - System settings

#### Financial Data
- `balance_sheets` - Balance sheet headers
- `balance_sheet_data` - Balance sheet line items
- `income_statements` - Income statement headers
- `income_statement_data` - Income statement line items
- `ratio_analysis` - Financial ratios
- `chart_of_accounts` - COA definitions

#### Lookup/Reference Tables
- `countries`, `provinces`, `districts`, `wards`, `villages` - Geographic
- `currencies`, `timezones` - System references
- `banks`, `branches` - Organizational
- `industry_types`, `legal_types` - Classifications
- `financial_periods`, `reporting_periods` - Period management

#### System Tables
- `users`, `roles`, `permissions` - Access control
- `activity_log` - Audit trail
- `imports` - Import tracking
- `files` - File management
- `communication_campaigns` - Communications
- `notifications` - User notifications
- `jobs`, `failed_jobs` - Queue management
- `sessions` - Session storage

### Important Indexes
- `clients` - unique index on customer_id
- `loan_books` - unique index on contract_id
- `loan_books` - loan_portfolio_id foreign key
- SICR tables - performance indexes

### Recent Schema Changes (2025)
- FLI columns added to `loan_books` table
- Transition profile columns added
- IFRS9 columns added to `loan_books`
- Unique indexes on clients and loan_books
- Import tracking timestamps
- Soft deletes on general import configurations

---

## API & Routes

### Web Routes (`routes/web.php`)
Primary application routes using Inertia.js

**Major Route Groups:**

1. **Dashboard**
   - `GET /` - Home dashboard
   - `GET /dashboard` - Main dashboard
   - `GET /dashboard/{scope}/create-filter` - Filter creation
   - `GET /dashboard/filter-results` - Filter results
   - `GET /dashboard/my-workspace` - User workspace

2. **Users & Roles**
   - `GET /user` - User list
   - `POST /user/store` - Create user
   - `GET /user/{user}/edit` - Edit user
   - `GET /user/role` - Role list
   - `POST /user/role/store` - Create role

3. **Loan Products**
   - `GET /loan_product` - Product list
   - `POST /loan_product/store` - Create product
   - `POST /loan_product/{product}/sync_attributes` - Sync scoring

4. **Scoring Attributes**
   - `GET /scoring_attribute` - Attribute list
   - `POST /scoring_attribute/store` - Create attribute
   - `GET /scoring_attribute/table-columns` - Get table columns

5. **Clients**
   - `GET /client` - Client list
   - `POST /client/store` - Create client
   - `GET /client/{client}/show` - Client details
   - Multiple sub-routes for financials, shareholders, etc.

6. **Loan Applications**
   - `GET /loan_application` - Application list
   - `POST /loan_application/store` - Submit application
   - `POST /loan_application/{application}/score` - Calculate score
   - Approval workflow routes

7. **Loan Books**
   - `GET /loan_book` - Loan book list
   - `POST /loan_book/store` - Create loan book entry

8. **IFRS9 Routes**
   - Transition matrix management
   - LGD calculations
   - ECL calculations
   - SICR configuration
   - Staging rules

9. **FLI Routes**
   - `GET /fli/scenario-sets` - Scenario management
   - `GET /fli/external-calculations` - External calcs

10. **Imports/Exports**
    - General import routes
    - Client import routes
    - Excel export routes

11. **Reports**
    - Various report generation routes

### API Routes (`routes/api.php`)
RESTful API endpoints with Sanctum authentication

### Custom Import Routes (`routes/custom-imports.php`)
Specialized import endpoints

### Broadcasting Channels (`routes/channels.php`)
WebSocket channel definitions

---

## Frontend Components

### Vue.js Application Structure
**Path:** `resources/js/`

### Layouts
**Path:** `resources/js/Layouts/`
- Application layout wrapper
- Navigation components
- Header/footer components

### Pages (Inertia.js)
**Path:** `resources/js/Pages/`

Key page directories:
- `Dashboard/` - Dashboard views
- `Clients/` - Client management pages
- `LoanApplications/` - Application pages
- `LoanProducts/` - Product configuration pages
- `LoanBooks/` - Loan book pages
- `ExpectedCreditLoss/` - ECL calculation pages
- `FLI/` - Forward-looking information pages
- `TransitionMatrix/` - Transition matrix pages
- `LossGivenDefault/` - LGD pages
- `Collateral/` - Collateral pages
- `ScoringAttributeGroups/` - Scoring config
- `Regression/` - Regression analysis
- `Reports/` - Report viewers
- `Users/`, `Roles/` - User management
- `Settings/` - System settings
- `Auth/` - Authentication pages
- `Profile/` - User profile

### Shared Components
**Path:** `resources/js/Shared/`
- Form components
- Table components
- Modal components
- Button components
- Alert/notification components

### Jetstream Components
**Path:** `resources/js/Jetstream/`
- Authentication UI
- Team management (if enabled)
- User profile management

### Menu Components
**Path:** `resources/js/Menu/`
- Navigation menu structures
- Dynamic menu generation

---

## Services & Business Logic

### Service Classes
**Path:** `app/Services/`

1. **TransitionMatrixService.php**
   - Transition matrix calculations
   - PD computation
   - Matrix manipulation

2. **TransitionMatrixCummulativeService.php**
   - Cumulative transition calculations
   - Multi-period aggregation

3. **MacroForecastWeightedService.php**
   - Macro forecast processing
   - Scenario weighting
   - FLI adjustments

4. **RegressionService.php**
   - Regression model training
   - Prediction generation
   - Model validation

### Business Logic Patterns
- Service classes for complex calculations
- Model observers for automated actions
- Jobs for background processing
- Events and listeners for decoupled logic
- Actions for reusable operations

---

## Key Features

### 1. Credit Scoring Engine
- Dynamic attribute configuration
- Multi-criteria evaluation
- Ratio-based scoring
- Band classification
- Accept/reject automation

### 2. IFRS9 Compliance
- Automated stage classification
- SICR trigger monitoring
- ECL calculation (12-month & lifetime)
- Transition matrix management
- LGD and PD computation
- Collateral consideration
- Forward-looking adjustments

### 3. Workflow Management
- Multi-stage approval process
- Role-based assignments
- Comment threads
- Document attachments
- Reminder system
- Reassignment capabilities
- Approval history tracking

### 4. Data Import/Export
- Excel-based imports
- Template system
- Configuration management
- Failed record tracking
- Progress monitoring
- Bulk operations

### 5. Reporting & Analytics
- Custom report builder
- Dashboard visualizations
- Period comparisons
- Portfolio analytics
- ECL reports
- Export capabilities (Excel, PDF)

### 6. Communication Management
- SMS campaigns
- Email notifications
- Template engine
- Business rule automation
- Delivery tracking
- Multi-channel support

### 7. Financial Analysis
- Balance sheet tracking
- Income statement analysis
- Ratio calculations
- Multi-period comparisons
- Porter's Five Forces
- Shareholder tracking

### 8. Audit & Compliance
- Activity logging (Spatie)
- User action tracking
- Change history
- Compliance reporting

---

## Configuration

### Configuration Files
**Path:** `config/`

Key configuration files:
- `app.php` - Application settings
- `database.php` - Database connections
- `auth.php` - Authentication settings
- `permission.php` - Spatie permission config
- `activitylog.php` - Activity log settings
- `excel.php` - Excel import/export config
- `dompdf.php` - PDF generation settings
- `websockets.php` - WebSocket configuration
- `queue.php` - Queue settings
- `mail.php` - Email configuration
- `services.php` - Third-party services
- `telescope.php` - Debugging (dev only)
- `menu.php` - Menu configuration
- `widgets.php` - Dashboard widgets

### Environment Variables
Key `.env` settings:
- Database credentials
- App URL and environment
- Queue connection
- Mail configuration
- SMS gateway settings
- Payment gateway credentials
- WebSocket settings
- Telescope settings (dev)

---

## Development Resources

### Documentation Files
- `README.md` - Installation & setup
- `CODEBASE_INDEX.md` - Code structure overview
- `FLI_IMPLEMENTATION_GUIDE.md` - FLI feature guide
- `FLI_IMPLEMENTATION_PROGRESS.md` - FLI progress tracking
- `FLI_ADJUSTMENT_REQUIREMENTS.md` - FLI requirements
- `FLI_PHASE_3_4_SUMMARY.md` - FLI phase summaries
- `FLI_SESSION_SUMMARY.md` - Implementation sessions
- `FLI_SUMMARY.md` - FLI overview
- `COMPLETE_FIX_SUMMARY.md` - Bug fix tracking
- `DATABASE_SEEDING_FIX.md` - Seeding documentation
- `PERFORMANCE_FIX.md` - Performance optimization
- `QUICK_REFERENCE.md` - Quick reference guide
- `WARP.md` - Warp terminal setup

### Development Commands

#### Laravel Artisan
```bash
php artisan serve              # Start dev server
php artisan migrate           # Run migrations
php artisan db:seed           # Seed database
php artisan queue:work        # Process queue jobs
php artisan telescope:prune   # Clean Telescope data
php artisan route:list        # List all routes
php artisan make:*           # Generate files
```

#### NPM Scripts
```bash
npm run dev                   # Start Vite dev server
npm run build                # Build for production
npm run watch                # Watch for changes
```

#### Docker Commands
```bash
docker-compose up -d         # Start containers
docker-compose down          # Stop containers
docker-compose exec app bash # Access app container
```

### Testing
- PHPUnit configuration: `phpunit.xml`
- Test directory: `tests/`
- Run tests: `php artisan test`

### Code Quality
- Laravel Pint for code styling
- PHPStan for static analysis
- Debugbar (development)
- Telescope (development)

---

## Legacy Modules

### Corporate Module
**Path:** `corporate/`
Legacy PHP files for corporate credit scoring (pre-Laravel migration)

### Retail Module
**Path:** `retail/`
Legacy retail credit scoring module

### Assets
**Path:** `assets/`
Legacy static assets (being migrated to Vite)

---

## Deployment

### Docker Deployment (Recommended)
- Configuration: `docker-compose.yml`
- Dockerfile: `docker/`
- Nginx configuration included

### Traditional Deployment
- Requirements: PHP 8.1+, MySQL 5.7+, Composer, NPM
- Supports XAMPP/WAMP for local development

### Production Checklist
1. Set `APP_ENV=production` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Run `npm run build`
6. Set proper file permissions
7. Configure queue worker as daemon
8. Set up scheduled tasks (cron)
9. Configure SSL certificates

---

## Recent Development Activity

### Active Feature Branch
**Branch:** `feature-tinashe-changes`

### Recent Implementations
1. Forward-Looking Information (FLI) module
2. Scenario set management
3. External calculations integration
4. Enhanced transition matrix calculations
5. Cumulative LGD functionality
6. Regression analysis module
7. General import system overhaul
8. Enhanced SICR triggers

### Database Changes (2025)
- FLI-related tables and columns
- Scenario management tables
- Enhanced IFRS9 fields on loan_books
- Import tracking improvements
- Unique constraints for data integrity

---

## Contact & Support

For issues, feature requests, or questions:
- Check existing documentation files
- Review Laravel logs: `storage/logs/`
- Use Telescope in development for debugging
- Review activity logs in database

---

**End of Codebase Index**

*This index is auto-generated and should be updated when major architectural changes occur.*
