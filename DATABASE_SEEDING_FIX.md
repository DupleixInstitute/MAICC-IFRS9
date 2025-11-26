# Database Seeding Fix

## Problem
After fixing the N+1 query performance issue, a new error appeared:
```
Attempt to read property "setting_value" on null at HandleInertiaRequests.php:43
```

## Root Cause
The `settings` table was empty. The `HandleInertiaRequests` middleware was trying to access settings that didn't exist in the database, and the code wasn't handling null values gracefully.

## Solution Applied

### 1. Seeded Required Data
Ran the following seeders to populate the database with required data:
```bash
php artisan db:seed --class=SettingsTableSeeder
php artisan db:seed --class=CurrenciesTableSeeder
```

### 2. Made Middleware Resilient
Updated `app/Http/Middleware/HandleInertiaRequests.php` to use null-safe operators (`?->`) and null coalescing operators (`??`) to prevent crashes when settings are missing:

**Before:**
```php
$logo = Setting::where('setting_key', 'company_logo')->first()->setting_value;
'companyName' => Setting::where('setting_key', 'company_name')->first()->setting_value,
```

**After:**
```php
$logo = Setting::where('setting_key', 'company_logo')->first()?->setting_value;
'companyName' => Setting::where('setting_key', 'company_name')->first()?->setting_value ?? 'Company Name',
```

## Files Modified
1. `app/Http/Middleware/HandleInertiaRequests.php` - Added null-safe operators and default values

## Seeders Run
1. `SettingsTableSeeder` - Populated company settings (name, logo, email, etc.)
2. `CurrenciesTableSeeder` - Populated currency data

## Additional Seeders Available
If you need to fully seed the database, you can run:
```bash
php artisan db:seed
```

This will run all seeders defined in `DatabaseSeeder.php`:
- CountriesTableSeeder
- PermissionsTableSeeder
- RolesTableSeeder
- UsersTableSeeder
- CurrenciesTableSeeder ✅ (already run)
- TimezonesTableSeeder
- SettingsTableSeeder ✅ (already run)
- BranchesTableSeeder
- SmsGatewaysTableSeeder
- CommunicationCampaignBusinessRulesTableSeederTableSeeder
- LegalTypesTableSeeder
- IndustryTypesTableSeeder
- ChartOfAccountsTableSeeder
- ScoringAttributesTableSeeder
- CreditLossDefinitionSeeder

## Testing
The application should now load without errors. Try accessing the application in your browser.
