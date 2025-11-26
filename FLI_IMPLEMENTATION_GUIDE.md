# FLI Adjustment Module - Implementation Guide

## Quick Start Implementation Checklist

### Phase 1: Database Setup ✓
- [ ] Create migrations for new tables
- [ ] Add columns to loan_book table
- [ ] Create seeder for "Inhouse View" scenario set
- [ ] Run migrations and seeders

### Phase 2: Models & Relationships ✓
- [ ] Create ScenarioSet model
- [ ] Create ScenarioProbability model
- [ ] Create FliReportingPeriodParameter model
- [ ] Create FliAdj model
- [ ] Define relationships

### Phase 3: Controllers ✓
- [ ] Create ScenarioSetController
- [ ] Create ExternalCalculationsController
- [ ] Create SystemCalculationsController
- [ ] Implement business logic

### Phase 4: Routes ✓
- [ ] Add FLI routes to web.php
- [ ] Add permissions middleware
- [ ] Test route accessibility

### Phase 5: Frontend (Vue/Inertia) ✓
- [ ] Create Scenarios management pages
- [ ] Create External Calculations page
- [ ] Create System Calculations page
- [ ] Implement forecast table component

### Phase 6: Testing ✓
- [ ] Unit tests for calculations
- [ ] Integration tests for loan book update
- [ ] UAT with sample data

---

## Step-by-Step Implementation

### STEP 1: Create Database Migrations

#### Migration 1: Create scenario_sets table
```bash
php artisan make:migration create_scenario_sets_table
```

```php
// database/migrations/YYYY_MM_DD_create_scenario_sets_table.php
public function up()
{
    Schema::create('scenario_sets', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        $table->timestamps();
        
        $table->index('name');
        $table->index('is_active');
    });
}
```

#### Migration 2: Create scenario_probabilities table
```bash
php artisan make:migration create_scenario_probabilities_table
```

```php
// database/migrations/YYYY_MM_DD_create_scenario_probabilities_table.php
public function up()
{
    Schema::create('scenario_probabilities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
        $table->string('scenario_name');
        $table->decimal('probability', 5, 2); // 0.00 to 100.00
        $table->integer('order_position')->default(0);
        $table->timestamps();
        
        $table->index('scenario_set_id');
    });
}
```

#### Migration 3: Create fli_reporting_periods_parameters table
```bash
php artisan make:migration create_fli_reporting_periods_parameters_table
```

```php
// database/migrations/YYYY_MM_DD_create_fli_reporting_periods_parameters_table.php
public function up()
{
    Schema::create('fli_reporting_periods_parameters', function (Blueprint $table) {
        $table->id();
        $table->date('reporting_period');
        $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
        $table->integer('number_of_forecasting_periods');
        $table->integer('forecasting_period_length_months');
        $table->string('economic_data_statistic', 50);
        $table->string('pd_proxy_statistic', 50);
        $table->date('base_forecast_period');
        $table->decimal('base_macro_data_value', 15, 6);
        $table->decimal('base_pd_proxy_value', 15, 6);
        $table->decimal('regression_slope', 15, 6);
        $table->decimal('regression_intercept', 15, 6);
        $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        $table->timestamps();
        
        $table->index('reporting_period');
        $table->index('scenario_set_id');
    });
}
```

#### Migration 4: Create fli_adj table
```bash
php artisan make:migration create_fli_adj_table
```

```php
// database/migrations/YYYY_MM_DD_create_fli_adj_table.php
public function up()
{
    Schema::create('fli_adj', function (Blueprint $table) {
        $table->id();
        $table->date('reporting_period');
        $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
        $table->date('forecast_period');
        $table->integer('forecast_window_in_months');
        $table->decimal('weighted_macro_data_value', 15, 6);
        $table->decimal('predicted_value', 15, 6);
        $table->decimal('fli_adj', 10, 6);
        $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        $table->timestamps();
        
        $table->index('reporting_period');
        $table->index('scenario_set_id');
        $table->index('forecast_window_in_months');
        $table->unique(['reporting_period', 'scenario_set_id', 'forecast_window_in_months'], 'unique_forecast');
    });
}
```

#### Migration 5: Add columns to loan_book table
```bash
php artisan make:migration add_fli_columns_to_loan_book_table
```

```php
// database/migrations/YYYY_MM_DD_add_fli_columns_to_loan_book_table.php
public function up()
{
    Schema::table('loan_book', function (Blueprint $table) {
        $table->decimal('fli_adj', 10, 6)->nullable()->after('pd_value');
        $table->decimal('pd_post_fli_adj', 10, 6)->nullable()->after('fli_adj');
    });
}

public function down()
{
    Schema::table('loan_book', function (Blueprint $table) {
        $table->dropColumn(['fli_adj', 'pd_post_fli_adj']);
    });
}
```

#### Run Migrations
```bash
php artisan migrate
```

---

### STEP 2: Create Seeder

```bash
php artisan make:seeder ScenarioSetSeeder
```

```php
// database/seeders/ScenarioSetSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScenarioSetSeeder extends Seeder
{
    public function run()
    {
        // Create "Inhouse View" scenario set
        $scenarioSetId = DB::table('scenario_sets')->insertGetId([
            'name' => 'Inhouse View',
            'description' => 'Default economic scenario set for MAICC - Malawi',
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Create scenario probabilities
        DB::table('scenario_probabilities')->insert([
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Base Case',
                'probability' => 40.00,
                'order_position' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Best Case',
                'probability' => 25.00,
                'order_position' => 2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Downside 1',
                'probability' => 20.00,
                'order_position' => 3,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Downside 2',
                'probability' => 15.00,
                'order_position' => 4,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $this->command->info('Scenario Set "Inhouse View" seeded successfully!');
    }
}
```

#### Add to DatabaseSeeder
```php
// database/seeders/DatabaseSeeder.php
public function run()
{
    $this->call([
        // ... existing seeders
        ScenarioSetSeeder::class,
    ]);
}
```

#### Run Seeder
```bash
php artisan db:seed --class=ScenarioSetSeeder
```

---

### STEP 3: Create Models

#### Model 1: ScenarioSet
```bash
php artisan make:model ScenarioSet
```

```php
// app/Models/ScenarioSet.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioSet extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function probabilities(): HasMany
    {
        return $this->hasMany(ScenarioProbability::class)->orderBy('order_position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fliParameters(): HasMany
    {
        return $this->hasMany(FliReportingPeriodParameter::class);
    }

    public function fliAdjustments(): HasMany
    {
        return $this->hasMany(FliAdj::class);
    }

    // Validation: Check if probabilities sum to 100%
    public function hasValidProbabilities(): bool
    {
        return $this->probabilities->sum('probability') == 100.00;
    }
}
```

#### Model 2: ScenarioProbability
```bash
php artisan make:model ScenarioProbability
```

```php
// app/Models/ScenarioProbability.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioProbability extends Model
{
    protected $fillable = [
        'scenario_set_id',
        'scenario_name',
        'probability',
        'order_position',
    ];

    protected $casts = [
        'probability' => 'decimal:2',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }
}
```

#### Model 3: FliReportingPeriodParameter
```bash
php artisan make:model FliReportingPeriodParameter
```

```php
// app/Models/FliReportingPeriodParameter.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FliReportingPeriodParameter extends Model
{
    protected $fillable = [
        'reporting_period',
        'scenario_set_id',
        'number_of_forecasting_periods',
        'forecasting_period_length_months',
        'economic_data_statistic',
        'pd_proxy_statistic',
        'base_forecast_period',
        'base_macro_data_value',
        'base_pd_proxy_value',
        'regression_slope',
        'regression_intercept',
        'created_by',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'base_forecast_period' => 'date',
        'base_macro_data_value' => 'decimal:6',
        'base_pd_proxy_value' => 'decimal:6',
        'regression_slope' => 'decimal:6',
        'regression_intercept' => 'decimal:6',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

#### Model 4: FliAdj
```bash
php artisan make:model FliAdj
```

```php
// app/Models/FliAdj.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FliAdj extends Model
{
    protected $table = 'fli_adj';

    protected $fillable = [
        'reporting_period',
        'scenario_set_id',
        'forecast_period',
        'forecast_window_in_months',
        'weighted_macro_data_value',
        'predicted_value',
        'fli_adj',
        'created_by',
    ];

    protected $casts = [
        'reporting_period' => 'date',
        'forecast_period' => 'date',
        'weighted_macro_data_value' => 'decimal:6',
        'predicted_value' => 'decimal:6',
        'fli_adj' => 'decimal:6',
    ];

    public function scenarioSet(): BelongsTo
    {
        return $this->belongsTo(ScenarioSet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Calculate predicted value using regression formula
    public static function calculatePredictedValue(float $macroValue, float $slope, float $intercept): float
    {
        return ($slope * $macroValue) + $intercept;
    }

    // Calculate FLI adjustment
    public static function calculateFliAdj(float $predictedValue, float $basePredictedValue): float
    {
        if ($basePredictedValue == 0) {
            return 0;
        }
        return ($predictedValue / $basePredictedValue) - 1;
    }
}
```

---

### STEP 4: Create Controllers

#### Controller 1: ScenarioSetController
```bash
php artisan make:controller FLI/ScenarioSetController
```

```php
// app/Http/Controllers/FLI/ScenarioSetController.php
<?php

namespace App\Http\Controllers\FLI;

use App\Http\Controllers\Controller;
use App\Models\ScenarioSet;
use App\Models\ScenarioProbability;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ScenarioSetController extends Controller
{
    public function index()
    {
        $scenarioSets = ScenarioSet::with('probabilities')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('FLI/Scenarios/Index', [
            'scenarioSets' => $scenarioSets,
        ]);
    }

    public function create()
    {
        return Inertia::render('FLI/Scenarios/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:scenario_sets,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'scenarios' => 'required|array|min:2',
            'scenarios.*.scenario_name' => 'required|string|max:255',
            'scenarios.*.probability' => 'required|numeric|min:0|max:100',
        ]);

        // Validate that probabilities sum to 100%
        $totalProbability = collect($validated['scenarios'])->sum('probability');
        if (abs($totalProbability - 100) > 0.01) {
            return back()->withErrors(['scenarios' => 'Scenario probabilities must sum to 100%']);
        }

        DB::beginTransaction();
        try {
            $scenarioSet = ScenarioSet::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['scenarios'] as $index => $scenario) {
                ScenarioProbability::create([
                    'scenario_set_id' => $scenarioSet->id,
                    'scenario_name' => $scenario['scenario_name'],
                    'probability' => $scenario['probability'],
                    'order_position' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create scenario set: ' . $e->getMessage()]);
        }
    }

    public function edit(ScenarioSet $scenarioSet)
    {
        $scenarioSet->load('probabilities');

        return Inertia::render('FLI/Scenarios/Edit', [
            'scenarioSet' => $scenarioSet,
        ]);
    }

    public function update(Request $request, ScenarioSet $scenarioSet)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:scenario_sets,name,' . $scenarioSet->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'scenarios' => 'required|array|min:2',
            'scenarios.*.scenario_name' => 'required|string|max:255',
            'scenarios.*.probability' => 'required|numeric|min:0|max:100',
        ]);

        // Validate that probabilities sum to 100%
        $totalProbability = collect($validated['scenarios'])->sum('probability');
        if (abs($totalProbability - 100) > 0.01) {
            return back()->withErrors(['scenarios' => 'Scenario probabilities must sum to 100%']);
        }

        DB::beginTransaction();
        try {
            $scenarioSet->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Delete existing probabilities and recreate
            $scenarioSet->probabilities()->delete();

            foreach ($validated['scenarios'] as $index => $scenario) {
                ScenarioProbability::create([
                    'scenario_set_id' => $scenarioSet->id,
                    'scenario_name' => $scenario['scenario_name'],
                    'probability' => $scenario['probability'],
                    'order_position' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update scenario set: ' . $e->getMessage()]);
        }
    }

    public function destroy(ScenarioSet $scenarioSet)
    {
        try {
            $scenarioSet->delete();
            return redirect()->route('fli.scenarios.index')
                ->with('success', 'Scenario set deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete scenario set: ' . $e->getMessage()]);
        }
    }
}
```

#### Controller 2: ExternalCalculationsController
```bash
php artisan make:controller FLI/ExternalCalculationsController
```

```php
// app/Http/Controllers/FLI/ExternalCalculationsController.php
<?php

namespace App\Http\Controllers\FLI;

use App\Http\Controllers\Controller;
use App\Models\ScenarioSet;
use App\Models\FliReportingPeriodParameter;
use App\Models\FliAdj;
use App\Models\LoanBook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ExternalCalculationsController extends Controller
{
    public function index()
    {
        $scenarioSets = ScenarioSet::where('is_active', true)
            ->with('probabilities')
            ->get()
            ->map(function ($set) {
                return [
                    'value' => $set->id,
                    'label' => $set->name,
                ];
            });

        return Inertia::render('FLI/ExternalCalculations/Index', [
            'scenarioSets' => $scenarioSets,
        ]);
    }

    public function saveParameters(Request $request)
    {
        $validated = $request->validate([
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
        ]);

        try {
            $parameter = FliReportingPeriodParameter::create([
                ...$validated,
                'reporting_period' => Carbon::createFromFormat('Y-m', $validated['reporting_period'])->startOfMonth(),
                'base_forecast_period' => Carbon::createFromFormat('Y-m', $validated['base_forecast_period'])->startOfMonth(),
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parameters saved successfully',
                'parameter_id' => $parameter->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save parameters: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function generateForecasts(Request $request)
    {
        $validated = $request->validate([
            'parameter_id' => 'required|exists:fli_reporting_periods_parameters,id',
        ]);

        $parameter = FliReportingPeriodParameter::findOrFail($validated['parameter_id']);

        $forecasts = [];
        $reportingPeriod = Carbon::parse($parameter->reporting_period);

        for ($i = 0; $i <= $parameter->number_of_forecasting_periods; $i++) {
            $forecastWindowMonths = $i * $parameter->forecasting_period_length_months;
            $forecastPeriod = $reportingPeriod->copy()->addMonths($forecastWindowMonths);

            $forecasts[] = [
                'period_window' => $i,
                'forecast_period' => $forecastPeriod->format('Y-m'),
                'forecast_window_in_months' => $forecastWindowMonths,
                'weighted_macro_data_value' => $i === 0 ? $parameter->base_macro_data_value : null,
                'predicted_value' => null,
                'fli_adj' => null,
            ];
        }

        return response()->json([
            'success' => true,
            'forecasts' => $forecasts,
            'parameter' => $parameter,
        ]);
    }

    public function saveAdjustments(Request $request)
    {
        $validated = $request->validate([
            'parameter_id' => 'required|exists:fli_reporting_periods_parameters,id',
            'forecasts' => 'required|array',
            'forecasts.*.forecast_period' => 'required|date_format:Y-m',
            'forecasts.*.forecast_window_in_months' => 'required|integer|min:0',
            'forecasts.*.weighted_macro_data_value' => 'required|numeric',
        ]);

        $parameter = FliReportingPeriodParameter::findOrFail($validated['parameter_id']);
        $reportingPeriod = Carbon::parse($parameter->reporting_period);

        DB::beginTransaction();
        try {
            // Delete existing adjustments for this reporting period and scenario set
            FliAdj::where('reporting_period', $reportingPeriod)
                ->where('scenario_set_id', $parameter->scenario_set_id)
                ->delete();

            $basePredictedValue = null;

            foreach ($validated['forecasts'] as $forecast) {
                $macroValue = $forecast['weighted_macro_data_value'];
                $predictedValue = FliAdj::calculatePredictedValue(
                    $macroValue,
                    $parameter->regression_slope,
                    $parameter->regression_intercept
                );

                // Store base predicted value (period 0)
                if ($forecast['forecast_window_in_months'] == 0) {
                    $basePredictedValue = $predictedValue;
                }

                $fliAdj = FliAdj::calculateFliAdj($predictedValue, $basePredictedValue ?? $predictedValue);

                FliAdj::create([
                    'reporting_period' => $reportingPeriod,
                    'scenario_set_id' => $parameter->scenario_set_id,
                    'forecast_period' => Carbon::createFromFormat('Y-m', $forecast['forecast_period'])->startOfMonth(),
                    'forecast_window_in_months' => $forecast['forecast_window_in_months'],
                    'weighted_macro_data_value' => $macroValue,
                    'predicted_value' => $predictedValue,
                    'fli_adj' => $fliAdj,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'FLI adjustments saved successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save adjustments: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateLoanBook(Request $request)
    {
        $validated = $request->validate([
            'reporting_period' => 'required|date_format:Y-m',
            'scenario_set_id' => 'required|exists:scenario_sets,id',
        ]);

        $reportingPeriod = Carbon::createFromFormat('Y-m', $validated['reporting_period'])->startOfMonth();

        DB::beginTransaction();
        try {
            $stats = [
                'total_loans' => 0,
                'stage_1_updated' => 0,
                'stage_2_updated' => 0,
                'stage_3_skipped' => 0,
                'floored_at_zero' => 0,
                'capped_at_100' => 0,
            ];

            // Get all loans for this reporting period
            $loans = LoanBook::where('reporting_period', $reportingPeriod)->get();
            $stats['total_loans'] = $loans->count();

            foreach ($loans as $loan) {
                // Skip Stage 3 loans
                if ($loan->stage_post_qualitative == 3) {
                    $loan->fli_adj = null;
                    $loan->pd_post_fli_adj = 1.0; // 100%
                    $loan->save();
                    $stats['stage_3_skipped']++;
                    continue;
                }

                // Determine forecast window based on stage
                $forecastWindow = $loan->stage_post_qualitative == 1 
                    ? 12  // 12-month for Stage 1
                    : $loan->remaining_life_in_months;  // Lifetime for Stage 2

                // Find matching FLI adjustment
                $fliAdj = FliAdj::where('reporting_period', $reportingPeriod)
                    ->where('scenario_set_id', $validated['scenario_set_id'])
                    ->where('forecast_window_in_months', $forecastWindow)
                    ->first();

                if ($fliAdj) {
                    $loan->fli_adj = $fliAdj->fli_adj;
                    $pdPostFli = $loan->pd_value * (1 + $fliAdj->fli_adj);

                    // Apply bounds validation
                    if ($pdPostFli < 0) {
                        $pdPostFli = 0;
                        $stats['floored_at_zero']++;
                    } elseif ($pdPostFli > 1.0) {
                        $pdPostFli = 1.0;
                        $stats['capped_at_100']++;
                    }

                    $loan->pd_post_fli_adj = $pdPostFli;
                    $loan->save();

                    if ($loan->stage_post_qualitative == 1) {
                        $stats['stage_1_updated']++;
                    } else {
                        $stats['stage_2_updated']++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Loan book updated successfully',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan book: ' . $e->getMessage(),
            ], 500);
        }
    }
}
```

---

### STEP 5: Add Routes

```php
// routes/web.php

// FLI Adjustment Routes
Route::prefix('fli-adj')->middleware(['auth'])->group(function () {
    
    // Economic Scenarios Set
    Route::prefix('scenarios')->name('fli.scenarios.')->group(function () {
        Route::get('/', [App\Http\Controllers\FLI\ScenarioSetController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\FLI\ScenarioSetController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\FLI\ScenarioSetController::class, 'store'])->name('store');
        Route::get('/{scenarioSet}/edit', [App\Http\Controllers\FLI\ScenarioSetController::class, 'edit'])->name('edit');
        Route::put('/{scenarioSet}', [App\Http\Controllers\FLI\ScenarioSetController::class, 'update'])->name('update');
        Route::delete('/{scenarioSet}', [App\Http\Controllers\FLI\ScenarioSetController::class, 'destroy'])->name('destroy');
    });

    // External Calculations
    Route::prefix('external')->name('fli.external.')->group(function () {
        Route::get('/', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'index'])->name('index');
        Route::post('/save-parameters', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'saveParameters'])->name('save-parameters');
        Route::post('/generate-forecasts', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'generateForecasts'])->name('generate');
        Route::post('/save-adjustments', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'saveAdjustments'])->name('save');
        Route::post('/update-loanbook', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'updateLoanBook'])->name('update-loanbook');
    });

    // System Calculations (Regression Analysis)
    // Note: Can reuse ExternalCalculationsController or create separate controller
    Route::prefix('regression')->name('fli.regression.')->group(function () {
        Route::get('/', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'index'])->name('index');
        Route::post('/save-parameters', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'saveParameters'])->name('save-parameters');
        Route::post('/generate-forecasts', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'generateForecasts'])->name('generate');
        Route::post('/save-adjustments', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'saveAdjustments'])->name('save');
        Route::post('/update-loanbook', [App\Http\Controllers\FLI\ExternalCalculationsController::class, 'updateLoanBook'])->name('update-loanbook');
    });
});
```

---

### STEP 6: Update Menu Configuration

```php
// config/menu.php (or wherever your menu is configured)

// Add FLI Adj menu item
[
    'label' => 'FLI Adj',
    'icon' => 'TrendingUp',
    'permission' => 'fli.view',
    'children' => [
        [
            'label' => 'Economic Scenarios Set',
            'route' => 'fli.scenarios.index',
            'permission' => 'fli.scenarios.view',
        ],
        [
            'label' => 'External Calculations',
            'route' => 'fli.external.index',
            'permission' => 'fli.external.view',
        ],
        [
            'label' => 'System Calculations (Regression)',
            'route' => 'fli.regression.index',
            'permission' => 'fli.regression.view',
        ],
    ],
],
```

---

### STEP 7: Testing

#### Test 1: Create Scenario Set
```bash
# Access: /fli-adj/scenarios/create
# Create "Inhouse View" with 4 scenarios totaling 100%
# Verify probabilities validation
```

#### Test 2: External Calculations
```bash
# Access: /fli-adj/external
# Enter parameters
# Generate forecasts
# Enter macro values
# Save adjustments
# Update loan book
# Verify loan_book.fli_adj and pd_post_fli_adj updated
```

#### Test 3: Validation
```bash
# Test negative PD adjustment → should floor at 0%
# Test PD > 100% → should cap at 100%
# Test Stage 3 loans → should remain at 100%
```

---

## Next Steps

1. **Create Vue/Inertia Pages** (see separate frontend guide)
2. **Add Permissions** to roles
3. **Create Unit Tests**
4. **User Acceptance Testing**
5. **Documentation for end users**

---

**Implementation Status:** Ready for Development  
**Estimated Time:** 3-5 days for full implementation  
**Priority:** High (IFRS 9 Compliance Requirement)
