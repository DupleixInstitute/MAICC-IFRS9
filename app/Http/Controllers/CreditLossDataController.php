<?php

namespace App\Http\Controllers;

use App\Models\CreditLossData;
use App\Models\LoanPortfolio;
use App\Models\ScenarioProfiles;
use App\Models\Scenarios;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class CreditLossDataController extends Controller
{
    public function index(Request $request)
    {
        $profiles = ScenarioProfiles::with('scenarios')->get();

        $portfolioId = $request->input('portfolio_id');
        if ($portfolioId) {
            $portfolio = LoanPortfolio::findOrFail($portfolioId);
            $creditLossData = CreditLossData::with(['scenarioProfile', 'scenario', 'creator'])
                ->where('portfolio_id', $portfolio->id)
                ->orderBy('period')
                ->get();
        } else {
            $portfolio = null;
            $creditLossData = CreditLossData::with(['scenarioProfile', 'scenario', 'creator'])
                ->orderBy('period')
                ->get();
        }

        return Inertia::render('FLI/CreditLossData/Index', [
            'portfolio' => $portfolio,
            'creditLossData' => $creditLossData,
            'profiles' => $profiles,
            'portfolios' => LoanPortfolio::all(),
        ]);
    }

    public function create()
    {
        $profiles = ScenarioProfiles::with('scenarios')->get();

        return Inertia::render('FLI/CreditLossData/Create', [
            'portfolios' => LoanPortfolio::all(),
            'profiles' => $profiles,
        ]);
    }

    public function importView()
    {
        return Inertia::render('FLI/CreditLossData/Import', [
            'portfolios' => LoanPortfolio::all(),
        ]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'exists:loan_portfolios,id'],
            'period' => ['required', 'date_format:Y-m'],
            'ecl_value' => 'nullable|numeric',
            'npl_value' => 'nullable|numeric',
            'pd_value' => 'nullable|numeric|between:0,1',
            'lgd_value' => 'nullable|numeric|between:0,1',
            'ead_value' => 'nullable|numeric',
            'stage' => 'nullable|in:1,2,3',
            'credit_rating' => 'nullable|string|max:10',
           // 'provision_value' => 'nullable|numeric',
           // 'write_off_value' => 'nullable|numeric',
           // 'recovery_value' => 'nullable|numeric',
            //'scenario_profile_id' => 'nullable|exists:scenario_profiles,id',
            //'scenario_id' => 'nullable|exists:scenarios,id',
            'is_forecast' => 'nullable|boolean',
            'source' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['period'] = substr($validated['period'], 0, 7);
        $validated['created_by'] = auth()->id();

        CreditLossData::create($validated);

        return redirect()->route('credit-loss-data.index')
                        ->with('success', 'Credit loss data added successfully!');
    }

    public function update(Request $request, CreditLossData $creditLossData)
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'ecl_value' => 'nullable|numeric',
            'npl_value' => 'nullable|numeric',
            'pd_value' => 'nullable|numeric|between:0,1',
            'lgd_value' => 'nullable|numeric|between:0,1',
            'ead_value' => 'nullable|numeric',
            'stage' => 'nullable|in:1,2,3',
            'credit_rating' => 'nullable|string|max:10',
            'provision_value' => 'nullable|numeric',
            'write_off_value' => 'nullable|numeric',
            'recovery_value' => 'nullable|numeric',
            'scenario_profile_id' => 'nullable|exists:scenario_profiles,id',
            'scenario_id' => 'nullable|exists:scenarios,id',
            'is_forecast' => 'nullable|boolean',
            'source' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['period'] = substr($validated['period'], 0, 7);

        $creditLossData->update($validated);

        return redirect()->back()->with('success', 'Credit loss data updated successfully!');
    }


   public function import(Request $request)
        {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:2048',
                'portfolio_id' => ['required', 'exists:loan_portfolios,id'],
            ]);

            $file = $request->file('file');
            $path = $file->getRealPath();
            $rows = array_map('str_getcsv', file($path));

            // Get headers and normalize them
            $headers = array_map('trim', array_map('strtolower', $rows[0]));
            
            // Skip header row
            array_shift($rows);

            // Define expected field mappings with validation rules
            $fieldMappings = [
                'period' => [
                    'aliases' => ['period', 'date', 'month', 'year-month'],
                    'validation' => ['required', 'date_format:Y-m']
                ],
                'ecl_value' => [
                    'aliases' => ['ecl_value', 'ecl', 'expected credit loss', 'ecl value'],
                    'validation' => ['nullable', 'numeric']
                ],
                'npl_value' => [
                    'aliases' => ['npl_value', 'npl', 'non-performing loans', 'npl value'],
                    'validation' => ['nullable', 'numeric']
                ],
                'pd_value' => [
                    'aliases' => ['pd_value', 'pd', 'probability of default', 'pd value'],
                    'validation' => ['nullable', 'numeric', 'between:0,1']
                ],
                'lgd_value' => [
                    'aliases' => ['lgd_value', 'lgd', 'loss given default', 'lgd value'],
                    'validation' => ['nullable', 'numeric', 'between:0,1']
                ],
                'ead_value' => [
                    'aliases' => ['ead_value', 'ead', 'exposure at default', 'ead value'],
                    'validation' => ['nullable', 'numeric']
                ],
                'stage' => [
                    'aliases' => ['stage', 'credit stage', 'stage classification', 'ifrs9 stage'],
                    'validation' => ['nullable', 'numeric']
                ],
                'credit_rating' => [
                    'aliases' => ['credit_rating', 'rating', 'credit rating', 'credit score'],
                    'validation' => ['nullable', 'string']
                ],
                'source' => [
                    'aliases' => ['source', 'data source', 'source system'],
                    'validation' => ['nullable', 'string']
                ],
                'notes' => [
                    'aliases' => ['notes', 'comment', 'remarks', 'description'],
                    'validation' => ['nullable', 'string']
                ]
            ];

            // Map headers to database fields
            $headerMapping = [];
            foreach ($fieldMappings as $dbField => $config) {
                foreach ($config['aliases'] as $alias) {
                    $key = array_search($alias, $headers);
                    if ($key !== false) {
                        $headerMapping[$dbField] = $key;
                        break;
                    }
                }
            }

            // Check if required period field is found
            if (!isset($headerMapping['period'])) {
                return redirect()->back()
                    ->with('error', 'Required "period" column not found in CSV. Please check your file headers.');
            }

            $importedCount = 0;
            $skippedCount = 0;

            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Normalize row data using header mapping
                $rowData = [];
                $validationData = [];
                $validationRules = [];

                foreach ($headerMapping as $dbField => $columnIndex) {
                    $value = isset($row[$columnIndex]) ? trim($row[$columnIndex]) : null;
                    
                    // Convert empty strings to null
                    $value = $value === '' ? null : $value;
                    
                    $rowData[$dbField] = $value;
                    $validationData[$dbField] = $value;
                    $validationRules[$dbField] = $fieldMappings[$dbField]['validation'];
                }

                // Validate the row data
                $validator = Validator::make($validationData, $validationRules);

                if ($validator->fails()) {
                    $skippedCount++;
                    continue;
                }

                // Prepare data for updateOrCreate
                $createData = [
                    'portfolio_id' => $request->input('portfolio_id'),
                    'period' => $rowData['period'],
                    'ecl_value' => $rowData['ecl_value'] ?? null,
                    'npl_value' => $rowData['npl_value'] ?? null,
                    'pd_value' => $rowData['pd_value'] ?? null,
                    'lgd_value' => $rowData['lgd_value'] ?? null,
                    'ead_value' => $rowData['ead_value'] ?? null,
                    'stage' => $rowData['stage'] ?? null,
                    'credit_rating' => $rowData['credit_rating'] ?? null,
                    'created_by' => auth()->id(),
                    'source' => $rowData['source'] ?? 'CSV Import',
                    'notes' => $rowData['notes'] ?? null,
                ];

                // Remove null values that shouldn't overwrite existing data
                $createData = array_filter($createData, function ($value) {
                    return $value !== null;
                });

                CreditLossData::updateOrCreate(
                    [
                        'portfolio_id' => $request->input('portfolio_id'),
                        'period' => $rowData['period'],
                    ],
                    $createData
                );

                $importedCount++;
            }

            $message = "Credit loss data imported successfully. {$importedCount} records processed.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} records skipped due to validation errors.";
            }

            return redirect()->route('credit-loss-data.index')
                            ->with('success', $message);
        }

    public function destroy(CreditLossData $creditLossData)
    {
        $creditLossData->delete();

        return redirect()->back()->with('success', 'Credit loss data deleted.');
    }
}