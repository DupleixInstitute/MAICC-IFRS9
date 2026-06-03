# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

MAICC-IFRS9 is a comprehensive Financial Risk Management and Client Management System built with Laravel 10 and Vue.js 3. The application specializes in IFRS 9 compliance (International Financial Reporting Standards), credit risk modeling, and loan portfolio management for financial institutions.

## Development Commands

### Initial Setup

#### Docker Setup (Recommended)
```bash
# Start Docker containers
docker-compose up -d --build

# Install PHP dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations and seed database
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed

# Set permissions
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

#### Traditional Setup
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend assets
npm run dev
```

### Development Workflow

```bash
# Start Laravel development server
php artisan serve

# Build frontend assets for development
npm run dev

# Watch for frontend changes (hot reload)
npm run watch

# Build assets for production
npm run build
```

### Testing and Quality

```bash
# Run PHPUnit tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/Unit
./vendor/bin/phpunit tests/Feature

# Code formatting with Laravel Pint (included in composer.json)
./vendor/bin/pint

# PHP static analysis with PHPStan
./vendor/bin/phpstan analyse
```

### Database Operations

```bash
# Create new migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Refresh database (rollback and re-run migrations)
php artisan migrate:refresh --seed
```

### Artisan Commands

```bash
# Clear application caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generate optimized class loader
php artisan optimize

# Create controllers, models, etc.
php artisan make:controller ControllerName
php artisan make:model ModelName
php artisan make:request RequestName
```

## Architecture Overview

### Backend Architecture (Laravel)

The application follows Laravel's MVC architecture with additional layers for financial modeling:

**Core Modules:**
- **Client Management**: Comprehensive client lifecycle management with financial profiles
- **Loan Management**: Loan applications, products, portfolios, and approval workflows  
- **IFRS 9 Compliance**: Expected Credit Loss (ECL) calculations, staging rules, and transition matrices
- **Credit Risk Modeling**: Probability of Default (PD), Loss Given Default (LGD), and scenario analysis
- **Financial Analysis**: Balance sheets, income statements, ratio analysis, and Porter's Five Forces
- **Communication System**: Multi-channel communication with SMS gateways and email campaigns
- **Reporting**: Comprehensive financial and regulatory reporting

**Key Architectural Patterns:**
- **Events & Observers**: `ClientObserver`, `LoanApplicationObserver` for automated business logic
- **DataTables**: Server-side processing for large datasets using Yajra DataTables
- **Import/Export**: Bulk data operations with `Imports/` and `Exports/` classes
- **Services Layer**: Business logic separation in `app/Services/`
- **Actions**: Complex operations encapsulated in `app/Actions/`

### Frontend Architecture (Vue.js + Inertia.js)

**Framework Stack:**
- Vue.js 3 with Composition API
- Inertia.js for SPA-like experience without API endpoints
- Tailwind CSS for styling
- Chart.js for financial visualizations

**Directory Structure:**
- `resources/js/Pages/`: Main application pages organized by feature
- `resources/js/Components/`: Reusable Vue components
- `resources/js/Layouts/`: Application layout templates
- `resources/js/Shared/`: Shared utilities and composables

### Database Design

The database design centers around financial entities with audit trails:

**Core Entities:**
- `clients`: Customer master data with KYC information
- `loan_applications`: Loan processing workflow
- `loan_books`: Active loan portfolio
- `expected_credit_losses`: IFRS 9 ECL calculations
- `transition_matrices`: Credit rating migration analysis

**Key Relationships:**
- Clients → Loan Applications → Loan Books (1:N:N)
- Clients → Financial Statements (1:N) 
- Loan Books → ECL Calculations (1:N)
- Complex many-to-many relationships for credit risk modeling

### IFRS 9 Implementation

The system implements IFRS 9 through specialized modules:

**Stage Classification:**
- Stage 1: 12-month ECL (performing loans)
- Stage 2: Lifetime ECL (significant increase in credit risk)  
- Stage 3: Lifetime ECL (credit-impaired)

**Key Components:**
- `SicrGroup`/`SicrItem`/`SicrTrigger`: Significant Increase in Credit Risk (SICR) detection
- `TransitionMatrix`: Credit rating migration modeling
- `MacroForecastWeighted`: Forward-looking macro scenarios
- `LossGivenDefault`: Recovery rate modeling

### Development Patterns

**Controller Organization:**
Controllers are organized by business domain rather than technical function, enabling feature-based development.

**Model Conventions:**
- Eloquent relationships define business rules
- Observers handle cross-cutting concerns (notifications, auditing)
- Scopes provide reusable query logic

**Frontend Patterns:**
- Page-based routing through Inertia.js
- Shared state management through props and composables  
- Form handling with Vue 3 reactive forms and validation

## Docker Services

- **app**: PHP-FPM Laravel application (port 8000)
- **db**: MySQL 5.7 database (port 3307) 
- **nginx**: Web server proxy
- **redis**: Cache and session storage

## Key Dependencies

**Backend:**
- `laravel/jetstream`: Authentication and team management
- `inertiajs/inertia-laravel`: Full-stack framework bridge
- `spatie/laravel-permission`: Role-based access control
- `yajra/laravel-datatables`: Server-side data processing
- `barryvdh/laravel-dompdf`: PDF generation
- `maatwebsite/excel`: Excel import/export

**Frontend:**
- `@inertiajs/vue3`: Vue.js integration
- `@headlessui/vue`: Unstyled, accessible UI components
- `chart.js`: Financial data visualization
- `@fullcalendar/vue3`: Calendar functionality
- `vue-sweetalert2`: User notifications

## Environment Configuration

Critical `.env` variables:
- Database connection settings (MySQL)
- File storage configuration (local/S3)
- SMS gateway credentials (for client communications)
- Email configuration (for notifications)
- License verification settings