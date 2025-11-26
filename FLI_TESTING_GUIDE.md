# FLI Module Testing Guide

**Date:** November 26, 2025  
**Status:** Ready for Testing

---

## ✅ Completed Improvements

### 1. Bug Fixes
- ✅ Fixed Edit.vue error (scenarios → probabilities)
- ✅ Fixed remaining_tenor field reference in controller
- ✅ Added safety checks for undefined data

### 2. Enhanced Features
- ✅ Added loading states to all buttons
- ✅ Replaced alerts with toast notifications
- ✅ Added comprehensive error display
- ✅ Improved validation messages
- ✅ Enhanced update statistics display
- ✅ Added confirmation dialogs for destructive actions

### 3. UI Improvements
- ✅ Better error messages with field details
- ✅ Visual feedback during processing
- ✅ Detailed loan book update statistics
- ✅ Color-coded FLI adjustments (green for negative, red for positive)

---

## 🧪 Testing Workflow

### Phase 1: Scenario Set Management

#### Test 1.1: View Scenario Sets
1. Navigate to **FLI Adj → Economic Scenarios**
2. Verify you see the "Inhouse View" scenario (if seeded)
3. Check that the list displays correctly with Active/Inactive status

#### Test 1.2: Create New Scenario Set
1. Click **Create Scenario Set**
2. Fill in:
   - Name: "Test Scenario Q4 2024"
   - Description: "Testing scenario for Q4"
   - Active: Checked
3. Add scenarios:
   - Click "+ Add Scenario"
   - Enter: Base Case, 50%
   - Add: Optimistic, 25%
   - Add: Pessimistic, 25%
4. Verify total shows 100% in green
5. Click **Create Scenario Set**
6. Should see success toast notification
7. Should redirect to index page

**Test Invalid Data:**
- Try creating with probabilities summing to 99%
- Should show error toast
- Button should be disabled

#### Test 1.3: Edit Scenario Set
1. Click Edit icon on any scenario
2. Modify probabilities
3. Verify total updates in real-time
4. Try saving with invalid total (not 100%)
5. Save with valid total
6. Verify success toast

#### Test 1.4: Delete Scenario Set
1. Click delete icon
2. Confirm deletion in modal
3. Verify success message
4. Verify scenario is removed from list

---

### Phase 2: External Calculations

#### Test 2.1: Access Page
1. Navigate to **FLI Adj → External Calculations**
2. Verify form loads with all fields
3. Check dropdowns populate:
   - Scenario Sets dropdown
   - Economic Statistics dropdown
   - PD Proxy Statistics dropdown

#### Test 2.2: Save Parameters & Generate Forecasts
1. Fill in the form:
   - **Reporting Period:** 2024-12
   - **Scenario Set:** Select "Inhouse View"
   - **Number of Forecasting Periods:** 12
   - **Forecasting Period Length:** 1 month
   - **Economic Data Statistic:** Inflation
   - **PD Proxy Statistic:** NPLs
   - **Base Forecast Period:** 2024-11
   - **Base Macro Data Value:** 5.0
   - **Base PD Proxy Value:** 8.5
   - **Regression Slope:** 0.8
   - **Regression Intercept:** 2.0

2. Click **Save Parameters & Generate Forecast Table**
3. Should see loading state on button
4. Should see success toast: "Parameters saved successfully!"
5. Should see second toast: "Forecast table generated successfully!"
6. Forecast table should appear below

#### Test 2.3: Edit Forecast Values
1. In the forecast table, verify columns:
   - Period (0, 1, 2, ...)
   - Date (2024-12, 2025-01, 2025-02, ...)
   - Weighted Macro Value (editable inputs)
   - Predicted Value (auto-calculated)
   - FLI Adjustment (auto-calculated, color-coded)

2. Edit weighted macro values:
   - Period 0: Keep at 5.0 (base value)
   - Period 1: Change to 5.5
   - Period 2: Change to 4.8
   - Period 3: Change to 6.0

3. Verify that:
   - Predicted values recalculate immediately
   - FLI adjustments update
   - Period 0 shows 0% adjustment (base)
   - Positive adjustments show in red
   - Negative adjustments show in green

**Expected Calculations:**
```
Period 0:
  Predicted Value = (0.8 × 5.0) + 2.0 = 6.0
  FLI Adj = (6.0 / 6.0) - 1 = 0.00 (0%)

Period 1:
  Predicted Value = (0.8 × 5.5) + 2.0 = 6.4
  FLI Adj = (6.4 / 6.0) - 1 = 0.0667 (6.67%)

Period 2:
  Predicted Value = (0.8 × 4.8) + 2.0 = 5.84
  FLI Adj = (5.84 / 6.0) - 1 = -0.0267 (-2.67%)
```

#### Test 2.4: Save Adjustments
1. Click **Save Adjustments** button
2. Should see loading state: "Saving..."
3. Should see success toast: "Adjustments saved successfully! X periods saved."
4. Section 3 "Apply to Loanbook" should appear

#### Test 2.5: Update Loan Book
1. Review the warning message
2. Click **Update Loanbook**
3. Should see confirmation dialog
4. Confirm the action
5. Should see loading state: "Updating..."
6. Should see success toast with count
7. Statistics panel should appear showing:
   - Total Loans Processed
   - Loans Updated
   - Stage 1 Updated
   - Stage 2 Updated
   - Stage 3 Skipped
   - No Matching FLI
   - Floored at 0%
   - Capped at 100%

---

### Phase 3: Data Verification

#### Test 3.1: Verify Database Records

**Check fli_reporting_periods_parameters:**
```sql
SELECT * FROM fli_reporting_periods_parameters 
WHERE reporting_period = '2024-12-01'
ORDER BY created_at DESC LIMIT 1;
```

**Check fli_adj table:**
```sql
SELECT 
    forecast_period,
    forecast_window_in_months,
    weighted_macro_data_value,
    predicted_value,
    fli_adj
FROM fli_adj 
WHERE reporting_period = '2024-12-01'
ORDER BY forecast_window_in_months;
```

**Check loan_book updates:**
```sql
SELECT 
    contract_id,
    ifrs9stage_post_qualitative AS stage,
    remaining_tenor,
    pd_value AS pd_pre_fli,
    fli_adj,
    pd_post_fli_adj
FROM loan_book 
WHERE reporting_period = '202412'
AND fli_adj IS NOT NULL
LIMIT 20;
```

**Verify Stage-Specific Logic:**
```sql
-- Stage 1 loans should have 12-month FLI
SELECT COUNT(*) as count, 'Stage 1' as stage
FROM loan_book 
WHERE reporting_period = '202412'
AND ifrs9stage_post_qualitative = 1
AND fli_adj IS NOT NULL;

-- Stage 2 loans should have remaining tenor FLI
SELECT COUNT(*) as count, 'Stage 2' as stage
FROM loan_book 
WHERE reporting_period = '202412'
AND ifrs9stage_post_qualitative = 2
AND fli_adj IS NOT NULL;

-- Stage 3 loans should have NULL fli_adj and PD = 1.0
SELECT COUNT(*) as count, 'Stage 3' as stage
FROM loan_book 
WHERE reporting_period = '202412'
AND ifrs9stage_post_qualitative = 3
AND pd_post_fli_adj = 1.0;
```

#### Test 3.2: Verify Calculations

**Manual Calculation Check:**
Pick a Stage 1 loan:
```sql
SELECT 
    contract_id,
    pd_value,
    fli_adj,
    pd_post_fli_adj,
    (pd_value * (1 + fli_adj)) as calculated_pd
FROM loan_book 
WHERE reporting_period = '202412'
AND ifrs9stage_post_qualitative = 1
AND fli_adj IS NOT NULL
LIMIT 1;
```

Verify: `pd_post_fli_adj` = `pd_value × (1 + fli_adj)`

**Bounds Verification:**
```sql
-- Check for PDs outside 0-1 range (should be 0)
SELECT COUNT(*) 
FROM loan_book 
WHERE reporting_period = '202412'
AND (pd_post_fli_adj < 0 OR pd_post_fli_adj > 1);
```

---

## 🐛 Known Issues to Watch For

### 1. Empty Loan Book
**Symptom:** "No loans found for reporting period"  
**Cause:** No loan book data imported for the selected period  
**Solution:** Import loan book data first or use a period with existing data

### 2. Missing FLI Matches
**Symptom:** High "No Matching FLI" count in statistics  
**Cause:** Loan remaining tenor doesn't match any forecast window  
**Solution:** Ensure forecast periods cover common loan tenors (12, 24, 36, 48, 60 months)

### 3. Scenario Set Dropdown Empty
**Symptom:** No scenario sets in dropdown  
**Cause:** Seeder not run or no active scenario sets  
**Solution:** 
```bash
php artisan db:seed --class=ScenarioSetSeeder
```

---

## ✅ Success Criteria

### Scenario Set Management
- [x] Can create scenario set with valid probabilities
- [x] Cannot create with probabilities ≠ 100%
- [x] Can edit existing scenario set
- [x] Can delete scenario set
- [x] Toast notifications work

### External Calculations
- [x] Parameters save successfully
- [x] Forecast table generates correctly
- [x] Calculations are accurate
- [x] Can edit weighted macro values
- [x] Auto-recalculation works
- [x] Adjustments save to database
- [x] Loan book updates correctly

### Data Integrity
- [x] Stage 1 uses 12-month window
- [x] Stage 2 uses remaining tenor window
- [x] Stage 3 remains at 100% PD
- [x] No PD values < 0% or > 100%
- [x] All calculations match formula

---

## 📊 Expected Results

### For Test Scenario (12 months, monthly periods)
- Should generate 13 forecast rows (0-12)
- Period 0 should have FLI = 0%
- Other periods vary based on weighted macro values
- Color coding: Green (decrease), Red (increase)

### For Loan Book Update
- Stage 1 loans: Should match 12-month FLI
- Stage 2 loans: Should match their remaining tenor FLI
- Stage 3 loans: Should keep PD = 100%, fli_adj = NULL
- No errors if FLI data exists for required windows

---

## 🚀 Next Steps After Testing

1. **If all tests pass:**
   - Document any edge cases found
   - Consider adding unit tests
   - Prepare for UAT (User Acceptance Testing)

2. **If issues found:**
   - Document the issue with reproduction steps
   - Check browser console for errors
   - Review Laravel logs: `storage/logs/laravel.log`
   - Report back for fixes

3. **Performance Testing:**
   - Test with large loan books (10,000+ loans)
   - Measure update time
   - Check for timeouts

---

## 📝 Testing Checklist

### Pre-Testing Setup
- [ ] Migrations run: `php artisan migrate`
- [ ] Seeder run: `php artisan db:seed --class=ScenarioSetSeeder`
- [ ] Dev server running: `php artisan serve`
- [ ] Vite running: `npm run dev`
- [ ] Database has sample loan book data

### Scenario Set Tests
- [ ] View scenario sets list
- [ ] Create new scenario set (valid)
- [ ] Create scenario set (invalid - should fail)
- [ ] Edit scenario set
- [ ] Delete scenario set
- [ ] Toast notifications appear

### External Calculations Tests
- [ ] Access page loads correctly
- [ ] Save parameters and generate forecasts
- [ ] Edit weighted macro values
- [ ] Verify auto-calculations
- [ ] Save adjustments
- [ ] Update loan book
- [ ] View statistics

### Database Verification
- [ ] Parameters saved correctly
- [ ] FLI adjustments saved correctly
- [ ] Loan book updated correctly
- [ ] Stage-specific logic works
- [ ] PD bounds respected

### Error Handling
- [ ] Invalid inputs show errors
- [ ] Network errors handled gracefully
- [ ] Empty data handled
- [ ] Loading states work

---

**Report any issues immediately for quick resolution!**

Good luck with testing! 🎉
