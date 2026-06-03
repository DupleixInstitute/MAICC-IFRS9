# Forward-Looking Information (FLI) Adjustment Module - Requirements Document

## Project Information
- **Application:** MAICC IFRS9 Compliance System
- **Client:** MAICC, Malawi
- **Module:** Forward-Looking Information (FLI) Adjustment
- **Standard:** IFRS 9 - Financial Instruments
- **Date:** 2025-11-26

---

## 1. Executive Summary

### Purpose
Implement a Forward-Looking Information (FLI) adjustment module that adjusts Probability of Default (PD) values based on macroeconomic forecasts and scenario-weighted predictions, in compliance with IFRS 9 requirements.

### Key Objectives
1. Capture and manage economic scenario sets with probability-weighted scenarios
2. Calculate FLI adjustments using external forecasts or regression analysis
3. Update loan book PD values with forward-looking adjustments
4. Ensure adjusted PDs remain within valid bounds (0% - 100%)

---

## 2. Business Context

### IFRS 9 Staging Overview
```
Stage 1: Performing Loans
├── PD Type: 12-month PD
├── Criteria: No significant credit deterioration
└── FLI Adjustment: Applied to 12-month forecast window

Stage 2: Underperforming Loans
├── PD Type: Lifetime PD
├── Criteria: Significant increase in credit risk (SICR)
└── FLI Adjustment: Applied to remaining tenor forecast window

Stage 3: Non-Performing Loans
├── PD Type: 100% (Credit-impaired)
├── Criteria: Defaulted
└── FLI Adjustment: None (PD remains 100%)
```

### PD Calculation Workflow
```
1. Stage Loans (1-3) → Done at import time
2. Adjust Stages for Qualitative Factors → Post-qualitative staging
3. Calculate PDs:
   ├── Stage 1: 12-month PD
   ├── Stage 2: Lifetime PD (12-month PD × remaining life adjustment)
   └── Stage 3: 100% PD
4. Store both 12-month and Lifetime PD for audit purposes
5. Use pd_value column as pd_pre_fli_adj (final PD before FLI)
6. Apply FLI Adjustment → pd_post_fli_adj
7. Validate adjusted PD (0% ≤ pd_post_fli_adj ≤ 100%)
```

---

## 3. Database Schema

### 3.1 Existing Tables to Modify

#### loan_book (Existing - Add Columns)
```sql
ALTER TABLE loan_book ADD COLUMN fli_adj DECIMAL(10,6) NULL COMMENT 'FLI adjustment factor as fraction';
ALTER TABLE loan_book ADD COLUMN pd_post_fli_adj DECIMAL(10,6) NULL COMMENT 'PD after FLI adjustment';

-- Existing columns referenced:
-- reporting_period (DATE)
-- value_date (DATE)
-- maturity_date (DATE)
-- remaining_life_in_months (INT)
-- pd_value (DECIMAL) -- This is pd_pre_fli_adj
-- stage_post_qualitative (INT) -- 1, 2, or 3
```

### 3.2 New Tables to Create

#### scenario_sets
```sql
CREATE TABLE scenario_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
);
```

#### scenario_probabilities
```sql
CREATE TABLE scenario_probabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scenario_set_id BIGINT UNSIGNED NOT NULL,
    scenario_name VARCHAR(255) NOT NULL,
    probability DECIMAL(5,2) NOT NULL COMMENT 'Probability as percentage (0-100)',
    order_position INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (scenario_set_id) REFERENCES scenario_sets(id) ON DELETE CASCADE,
    INDEX idx_scenario_set (scenario_set_id),
    CONSTRAINT chk_probability CHECK (probability >= 0 AND probability <= 100)
);
```

#### fli_reporting_periods_parameters
```sql
CREATE TABLE fli_reporting_periods_parameters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporting_period DATE NOT NULL,
    scenario_set_id BIGINT UNSIGNED NOT NULL,
    number_of_forecasting_periods INT NOT NULL,
    forecasting_period_length_months INT NOT NULL,
    economic_data_statistic VARCHAR(50) NOT NULL COMMENT 'inflation, exchange_rates, credit_index, unemployment_rate, interest_rates',
    pd_proxy_statistic VARCHAR(50) NOT NULL COMMENT 'NPLs or 12_months_PDs',
    base_forecast_period DATE NOT NULL COMMENT 'Latest historical economic date (yyyymm)',
    base_macro_data_value DECIMAL(15,6) NOT NULL,
    base_pd_proxy_value DECIMAL(15,6) NOT NULL,
    regression_slope DECIMAL(15,6) NOT NULL,
    regression_intercept DECIMAL(15,6) NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (scenario_set_id) REFERENCES scenario_sets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reporting_period (reporting_period),
    INDEX idx_scenario_set (scenario_set_id)
);
```

#### fli_adj
```sql
CREATE TABLE fli_adj (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporting_period DATE NOT NULL,
    scenario_set_id BIGINT UNSIGNED NOT NULL,
    forecast_period DATE NOT NULL,
    forecast_window_in_months INT NOT NULL COMMENT 'Months between forecast_period and reporting_period',
    weighted_macro_data_value DECIMAL(15,6) NOT NULL,
    predicted_value DECIMAL(15,6) NOT NULL COMMENT 'Calculated using regression formula',
    fli_adj DECIMAL(10,6) NOT NULL COMMENT 'FLI adjustment factor as fraction',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (scenario_set_id) REFERENCES scenario_sets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reporting_period (reporting_period),
    INDEX idx_scenario_set (scenario_set_id),
    INDEX idx_forecast_window (forecast_window_in_months),
    UNIQUE KEY unique_forecast (reporting_period, scenario_set_id, forecast_window_in_months)
);
```

---

## 4. User Interface Requirements

### 4.1 Main Menu Structure
```
FLI Adj
├── Economic Scenarios Set
├── External Calculations
├── Scenario Set Name (required, unique)
├── Description (optional)
├── Is Active (checkbox)
└── Scenarios Table (dynamic rows)
    ├── Scenario Name (required)
    ├── Probability % (required, numeric, 0-100)
    ├── Order Position (auto-assigned)
    └── Actions (Add Row, Delete Row)

Validation:
├── Total probability must equal 100%
├── At least 2 scenarios required
├── Scenario names must be unique within set
└── Probabilities must be positive numbers
```

#### Default Seed Data: "Inhouse View"
```
Scenario Set: Inhouse View
├── Base Case: 40%
├── Best Case: 25%
├── Downside 1: 20%
└── Downside 2: 15%
Total: 100% ✓
```

### 4.3 External Calculations & System Calculations Pages

Both pages share the same initial form with these fields:

#### Input Form
```
1. Reporting Period (YYYY-MM) [Calendar control, month/year only]
2. Scenario Set [Dropdown - from scenario_sets table]
3. Number of Forecasting Periods [Numeric input, min: 1]
4. Length of Each Forecasting Period (months) [Numeric input, min: 1]
5. Economic Data Statistic [Dropdown]
   ├── Inflation (default)
   ├── Exchange Rates
   ├── Credit Index
   ├── Unemployment Rate
   └── Interest Rates
6. PD Proxy Statistic [Dropdown]
   ├── NPLs
   └── 12 Months PDs
7. Base Forecast Reporting Period (YYYY-MM) [Calendar control]
   7a. Base Forecast Macro-Economic Data Value [Numeric input]
   7b. Base Forecast PD-Proxy Data Value [Numeric input]
8. Straight-Line Regression Parameter - Slope [Numeric input]
9. Straight-Line Regression Parameter - Intercept [Numeric input]

[Save Parameters] [Generate Forecasts]
```

#### Forecast Generation Table
After clicking "Generate Forecasts", display editable table:

```
[Save to FLI_Adj Table] [Update Loan Book]

Period | Forecast      | Weighted Macro | Predicted | FLI Adj
Window | Period        | Data Value     | Value     | (%)
-------|---------------|----------------|-----------|--------
0      | 2024-11 (RO)  | [Editable]     | [Calc]    | [Calc]
1      | 2024-12       | [Editable]     | [Calc]    | [Calc]
2      | 2025-01       | [Editable]     | [Calc]    | [Calc]
...    | ...           | ...            | ...       | ...

Legend:
(RO) = Read-only, auto-populated from reporting_period
[Editable] = User can input/modify
[Calc] = Auto-calculated
```

#### Calculations
```javascript
// Period Window
period_window = 0, 1, 2, ..., number_of_forecasting_periods

// Forecast Period (auto-generated)
if (period_window === 0) {
    forecast_period = reporting_period
} else {
    forecast_period = reporting_period + (period_window × forecasting_period_length_months)
}

// Forecast Window in Months
forecast_window_in_months = period_window × forecasting_period_length_months

// Predicted Value (using linear regression)
predicted_value = (regression_slope × weighted_macro_data_value) + regression_intercept

// FLI Adjustment
fli_adj = (predicted_value / predicted_value_at_period_0) - 1
```

---

## 5. Business Logic & Calculations

### 5.1 FLI Adjustment Calculation

#### Formula
```
fli_adj = (predicted_value / base_predicted_value) - 1

Where:
- predicted_value = slope × weighted_macro_data_value + intercept
- base_predicted_value = predicted_value at period_window = 0
```

#### Example
```
Period 0 (Base):
weighted_macro_data_value = 5.0
predicted_value = (0.8 × 5.0) + 2.0 = 6.0
fli_adj = (6.0 / 6.0) - 1 = 0.0 (0%)

Period 1:
weighted_macro_data_value = 6.0
predicted_value = (0.8 × 6.0) + 2.0 = 6.8
fli_adj = (6.8 / 6.0) - 1 = 0.1333 (13.33%)

Period 2:
weighted_macro_data_value = 4.5
predicted_value = (0.8 × 4.5) + 2.0 = 5.6
fli_adj = (5.6 / 6.0) - 1 = -0.0667 (-6.67%)
```

### 5.2 Loan Book Update Logic

#### Matching Logic
```sql
UPDATE loan_book lb
JOIN fli_adj fa ON (
    -- Match based on stage and remaining life
    CASE 
        WHEN lb.stage_post_qualitative = 1 THEN 
            fa.forecast_window_in_months = 12  -- 12-month window for Stage 1
        WHEN lb.stage_post_qualitative = 2 THEN 
            fa.forecast_window_in_months = lb.remaining_life_in_months  -- Lifetime for Stage 2
        ELSE 
            FALSE  -- No update for Stage 3
    END
    AND fa.reporting_period = lb.reporting_period
)
SET 
    lb.fli_adj = fa.fli_adj,
    lb.pd_post_fli_adj = lb.pd_value * (1 + fa.fli_adj)
WHERE 
    lb.stage_post_qualitative IN (1, 2);
```

#### Stage-Specific Logic
```
Stage 1 (Performing):
├── Use FLI adjustment for 12-month window
├── Match: forecast_window_in_months = 12
└── Formula: pd_post_fli_adj = pd_value × (1 + fli_adj)

Stage 2 (Underperforming):
├── Use FLI adjustment for remaining tenor
├── Match: forecast_window_in_months = remaining_life_in_months
└── Formula: pd_post_fli_adj = pd_value × (1 + fli_adj)

Stage 3 (Non-Performing):
├── No FLI adjustment applied
├── PD remains 100%
└── fli_adj = NULL, pd_post_fli_adj = 1.0 (100%)
```

### 5.3 PD Validation & Bounds

#### Validation Rules
```javascript
// After calculating pd_post_fli_adj
if (pd_post_fli_adj < 0) {
    pd_post_fli_adj = 0.0;  // Floor at 0%
    validation_flag = 'FLOORED_AT_ZERO';
}

if (pd_post_fli_adj > 1.0) {
    pd_post_fli_adj = 1.0;  // Cap at 100%
    validation_flag = 'CAPPED_AT_100';
}

// For Stage 3, always enforce 100%
if (stage_post_qualitative === 3) {
    pd_post_fli_adj = 1.0;
    fli_adj = NULL;
    validation_flag = 'STAGE_3_FIXED';
}
```

#### Validation Messages
```
Warnings to display:
├── "X loans had PD adjusted to 0% (negative after FLI)"
├── "Y loans had PD capped at 100% (exceeded limit after FLI)"
└── "Z Stage 3 loans maintained at 100% PD (no FLI applied)"
```

---

## 6. Workflow Summary

### Complete Process Flow
```
1. Setup Economic Scenarios
   ├── Navigate to: FLI Adj > Economic Scenarios Set
3. Generate Forecasts
   ├── Click "Generate Forecasts" button
   ├── System generates forecast periods table
   ├── User enters/edits weighted macro data values
   ├── System calculates predicted values
   └── System calculates fli_adj for each period

4. Save FLI Adjustments
   ├── Review calculated values
   ├── Click "Save to FLI_Adj Table"
   └── Data saved to fli_adj table

5. Update Loan Book
   ├── Click "Update Loan Book" button
   ├── System matches loans by:
   │   ├── Stage 1: 12-month forecast window
   │   ├── Stage 2: Remaining life forecast window
   │   └── Stage 3: No update
   ├── Calculate pd_post_fli_adj = pd_value × (1 + fli_adj)
   ├── Apply validation (0% ≤ PD ≤ 100%)
   └── Update loan_book.fli_adj and loan_book.pd_post_fli_adj

6. Validation & Reporting
   ├── Display summary of updates
   ├── Show validation warnings
   └── Generate audit report
```

---

## 7. Technical Specifications

### 7.1 Controllers Required

```php
app/Http/Controllers/
├── FLI/
│   ├── ScenarioSetController.php
│   ├── ExternalCalculationsController.php
│   ├── SystemCalculationsController.php
│   └── FliAdjustmentController.php
```

### 7.2 Models Required

```php
app/Models/
├── ScenarioSet.php
├── ScenarioProbability.php
├── FliReportingPeriodParameter.php
└── FliAdj.php
```

### 7.3 Routes Required

```php
// Economic Scenarios Set
Route::prefix('fli-adj/scenarios')->group(function () {
    Route::get('/', [ScenarioSetController::class, 'index'])->name('fli.scenarios.index');
    Route::get('/create', [ScenarioSetController::class, 'create'])->name('fli.scenarios.create');
    Route::post('/', [ScenarioSetController::class, 'store'])->name('fli.scenarios.store');
    Route::get('/{scenarioSet}/edit', [ScenarioSetController::class, 'edit'])->name('fli.scenarios.edit');
    Route::put('/{scenarioSet}', [ScenarioSetController::class, 'update'])->name('fli.scenarios.update');
    Route::delete('/{scenarioSet}', [ScenarioSetController::class, 'destroy'])->name('fli.scenarios.destroy');
});

// External Calculations
Route::prefix('fli-adj/external')->group(function () {
    Route::get('/', [ExternalCalculationsController::class, 'index'])->name('fli.external.index');
    Route::post('/save-parameters', [ExternalCalculationsController::class, 'saveParameters'])->name('fli.external.save-parameters');
    Route::post('/generate-forecasts', [ExternalCalculationsController::class, 'generateForecasts'])->name('fli.external.generate');
    Route::post('/save-adjustments', [ExternalCalculationsController::class, 'saveAdjustments'])->name('fli.external.save');
    Route::post('/update-loanbook', [ExternalCalculationsController::class, 'updateLoanBook'])->name('fli.external.update-loanbook');
});

// System Calculations (Regression)
Route::prefix('fli-adj/regression')->group(function () {
    Route::get('/', [SystemCalculationsController::class, 'index'])->name('fli.regression.index');
    Route::post('/save-parameters', [SystemCalculationsController::class, 'saveParameters'])->name('fli.regression.save-parameters');
    Route::post('/generate-forecasts', [SystemCalculationsController::class, 'generateForecasts'])->name('fli.regression.generate');
    Route::post('/save-adjustments', [SystemCalculationsController::class, 'saveAdjustments'])->name('fli.regression.save');
    Route::post('/update-loanbook', [SystemCalculationsController::class, 'updateLoanBook'])->name('fli.regression.update-loanbook');
});
```

### 7.4 Vue Components Required

```
resources/js/Pages/FLI/
├── Scenarios/
│   ├── Index.vue
│   ├── Create.vue
│   └── Edit.vue
├── ExternalCalculations/
│   ├── Index.vue
│   └── ForecastTable.vue
└── SystemCalculations/
    ├── Index.vue
    └── ForecastTable.vue
```

---

## 8. Validation Rules

### 8.1 Scenario Set Validation
```php
'name' => 'required|string|max:255|unique:scenario_sets,name',
'description' => 'nullable|string',
'scenarios' => 'required|array|min:2',
'scenarios.*.scenario_name' => 'required|string|max:255',
'scenarios.*.probability' => 'required|numeric|min:0|max:100',
// Custom validation: sum of probabilities must equal 100
```

### 8.2 FLI Parameters Validation
```php
'reporting_period' => 'required|date_format:Y-m',
'scenario_set_id' => 'required|exists:scenario_sets,id',
'number_of_forecasting_periods' => 'required|integer|min:1|max:120',
'forecasting_period_length_months' => 'required|integer|min:1|max:12',
'economic_data_statistic' => 'required|in:inflation,exchange_rates,credit_index,unemployment_rate,interest_rates',
'pd_proxy_statistic' => 'required|in:NPLs,12_months_PDs',
'base_forecast_period' => 'required|date_format:Y-m',
'base_macro_data_value' => 'required|numeric',
'base_pd_proxy_value' => 'required|numeric|min:0|max:100',
'regression_slope' => 'required|numeric',
'regression_intercept' => 'required|numeric',
```

### 8.3 FLI Adjustment Validation
```php
'weighted_macro_data_value' => 'required|numeric',
'predicted_value' => 'required|numeric',
'fli_adj' => 'required|numeric|between:-1,10',  // -100% to 1000%
```

---

## 9. Permissions & Security

### Required Permissions
```php
'fli.scenarios.view'
'fli.scenarios.create'
'fli.scenarios.edit'
'fli.scenarios.delete'
'fli.external.view'
'fli.external.calculate'
'fli.external.update-loanbook'
'fli.regression.view'
'fli.regression.calculate'
- Scenario probability validation (must sum to 100%)
- FLI adjustment calculation accuracy
- PD bounds validation (0% - 100%)
- Regression formula calculations

### Integration Tests
- Loan book update process
- Stage-specific FLI matching
- Multi-period forecast generation

### User Acceptance Tests
- Complete workflow from scenario setup to loan book update
- Edge cases: negative adjustments, extreme values
- Performance with large loan books (10,000+ loans)

---

## 11. Performance Considerations

### Optimization Strategies
1. **Batch Processing**: Update loan book in batches of 1,000 loans
2. **Indexing**: Ensure proper indexes on matching columns
3. **Caching**: Cache scenario sets and parameters
4. **Queue Jobs**: Use Laravel queues for large loan book updates

### Expected Performance
- Scenario set CRUD: < 1 second
- Forecast generation: < 2 seconds for 120 periods
- Loan book update: < 30 seconds for 10,000 loans

---

## 12. Reporting Requirements

### FLI Adjustment Report
```
Report Contents:
├── Reporting Period
├── Scenario Set Used
├── Number of Loans Updated
├── Stage Breakdown:
│   ├── Stage 1: X loans updated
│   ├── Stage 2: Y loans updated
│   └── Stage 3: Z loans (no update)
├── Validation Summary:
│   ├── Loans floored at 0%
│   ├── Loans capped at 100%
│   └── Average FLI adjustment by stage
└── Audit Information
```

---

## 13. Migration Plan

### Phase 1: Database Setup
1. Create new tables (scenario_sets, scenario_probabilities, fli_reporting_periods_parameters, fli_adj)
2. Add columns to loan_book (fli_adj, pd_post_fli_adj)
3. Seed "Inhouse View" scenario set

### Phase 2: Backend Development
### Functional Requirements Met
- ✅ Economic scenarios can be created and managed
- ✅ FLI parameters can be configured
- ✅ Forecasts can be generated and edited
- ✅ Loan book is updated correctly based on stage
- ✅ PD values remain within valid bounds
- ✅ Audit trail is maintained

### Performance Requirements Met
- ✅ Forecast generation < 2 seconds
- ✅ Loan book update < 30 seconds for 10,000 loans
- ✅ UI is responsive and intuitive

### Compliance Requirements Met
- ✅ IFRS 9 forward-looking requirements satisfied
- ✅ Scenario probability-weighting implemented
- ✅ Stage-specific adjustments applied correctly

---

## 15. Glossary

| Term | Definition |
|------|------------|
| **FLI** | Forward-Looking Information - macroeconomic forecasts used to adjust PD |
| **PD** | Probability of Default - likelihood of borrower defaulting |
| **ECL** | Expected Credit Loss - provision calculated as PD × LGD × EAD |
| **SICR** | Significant Increase in Credit Risk - triggers move from Stage 1 to 2 |
| **Scenario Set** | Collection of economic scenarios with assigned probabilities |
| **Weighted Forecast** | Probability-weighted average of scenario forecasts |
| **Regression** | Statistical method to predict PD based on macro variables |
| **Forecast Window** | Time period (in months) for which forecast applies |

---

## 16. Appendices

### Appendix A: Sample Calculations

#### Example 1: Stage 1 Loan (12-month PD)
```
Loan Details:
- stage_post_qualitative = 1
- pd_value (pd_pre_fli_adj) = 0.05 (5%)
- remaining_life_in_months = 36

FLI Adjustment (12-month window):
- forecast_window_in_months = 12
- fli_adj = 0.15 (15% increase)

Calculation:
pd_post_fli_adj = 0.05 × (1 + 0.15) = 0.0575 (5.75%)

Validation: 0% ≤ 5.75% ≤ 100% ✓
```

#### Example 2: Stage 2 Loan (Lifetime PD)
```
Loan Details:
- stage_post_qualitative = 2
- pd_value (pd_pre_fli_adj) = 0.25 (25%)
- remaining_life_in_months = 24

FLI Adjustment (24-month window):
- forecast_window_in_months = 24
- fli_adj = -0.10 (-10% decrease)

Calculation:
pd_post_fli_adj = 0.25 × (1 + (-0.10)) = 0.225 (22.5%)

Validation: 0% ≤ 22.5% ≤ 100% ✓
```

#### Example 3: Stage 3 Loan (No Adjustment)
```
Loan Details:
- stage_post_qualitative = 3
- pd_value (pd_pre_fli_adj) = 1.0 (100%)

FLI Adjustment:
- fli_adj = NULL (no adjustment for Stage 3)

Result:
pd_post_fli_adj = 1.0 (100%)
```

### Appendix B: Database Seed Data

```php
// Seeder: ScenarioSetSeeder.php
DB::table('scenario_sets')->insert([
    'name' => 'Inhouse View',
    'description' => 'Default economic scenario set for MAICC',
    'is_active' => true,
    'created_at' => now(),
    'updated_at' => now(),
]);

$scenarioSetId = DB::getPdo()->lastInsertId();

DB::table('scenario_probabilities')->insert([
    ['scenario_set_id' => $scenarioSetId, 'scenario_name' => 'Base Case', 'probability' => 40.00, 'order_position' => 1],
    ['scenario_set_id' => $scenarioSetId, 'scenario_name' => 'Best Case', 'probability' => 25.00, 'order_position' => 2],
    ['scenario_set_id' => $scenarioSetId, 'scenario_name' => 'Downside 1', 'probability' => 20.00, 'order_position' => 3],
    ['scenario_set_id' => $scenarioSetId, 'scenario_name' => 'Downside 2', 'probability' => 15.00, 'order_position' => 4],
]);
```

---

**Document Version:** 1.0  
**Last Updated:** 2025-11-26  
**Author:** Development Team  
**Status:** Ready for Implementation
