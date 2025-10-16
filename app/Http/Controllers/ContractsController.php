<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class ContractsController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::query()
            ->with('client')
            ->orderBy('create_date', 'desc');
            // dd($query);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('contract_id', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('period')) {
            $query->where('opening_score_period', $request->input('period'));
        }

        $contracts = $query->paginate(10)->withQueryString();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters' => $request->only(['search', 'period']),
        ]);
    }

    public function import(Request $request)
    {
        try {
            // Step 1: Validate request
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:csv,txt|max:10240',
                'opening_score_period' => 'required|date_format:Y-m',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => [
                        'validation_errors' => $validator->errors()->toArray(),
                        'message' => 'Please check the file format and opening score period.'
                    ]
                ], 422);
            }

            // Step 2: Read and validate CSV structure
            $file = $request->file('file');
            if (!$file->isValid()) {
                return response()->json([
                    'error' => 'File upload failed',
                    'details' => [
                        'message' => 'The file upload was not successful. Please try again.'
                    ]
                ], 422);
            }

            $data = array_map(function($line) {
                return str_getcsv(trim($line));
            }, file($file->getRealPath()));
            
            if (empty($data)) {
                return response()->json([
                    'error' => 'Empty file',
                    'details' => [
                        'message' => 'The uploaded CSV file is empty.'
                    ]
                ], 422);
            }

            if (count($data) <= 1) {
                return response()->json([
                    'error' => 'No data rows',
                    'details' => [
                        'message' => 'The CSV file contains only headers but no data rows.'
                    ]
                ], 422);
            }

            // Step 3: Validate headers
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            $requiredHeaders = [
                'contract_id', 
                'customer_id', 
                'create_date', 
                'due_date', 
                'opening_score',
                'closed_date',
                'write_off_date'
            ];
            
            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                return response()->json([
                    'error' => 'Missing headers',
                    'details' => [
                        'missing_headers' => array_values($missingHeaders),
                        'message' => 'The following required columns are missing: ' . implode(', ', $missingHeaders),
                        'required_headers' => $requiredHeaders,
                        'provided_headers' => $headers
                    ]
                ], 422);
            }

            $headerMap = array_flip($headers);
            $period = Carbon::createFromFormat('Y-m', $request->opening_score_period);
            $scorePeriod = $period->format('Ym');

            // Step 4: Process data with transaction
            DB::beginTransaction();
            
            try {
                $errors = [];
                $successCount = 0;
                $failedCount = 0;
                $rowErrors = [];

                foreach (array_slice($data, 1) as $index => $row) {
                    $rowNumber = $index + 2;
                    try {
                        // Validate row structure
                        if (count($row) !== count($headers)) {
                            throw new \Exception(sprintf(
                                "Incorrect number of columns. Expected %d, got %d",
                                count($headers),
                                count($row)
                            ));
                        }

                        $row = array_map('trim', $row);
                        
                        // Validate contract_id
                        if (empty($row[$headerMap['contract_id']])) {
                            throw new \Exception("Contract ID cannot be empty");
                        }
                        if (strlen($row[$headerMap['contract_id']]) > 50) {
                            throw new \Exception("Contract ID exceeds maximum length of 50 characters");
                        }

                        // Validate customer_id
                        if (empty($row[$headerMap['customer_id']])) {
                            throw new \Exception("Customer ID cannot be empty");
                        }
                        if (strlen($row[$headerMap['customer_id']]) > 50) {
                            throw new \Exception("Customer ID exceeds maximum length of 50 characters");
                        }

                        // Validate dates
                        $dates = [];
                        try {
                            // Required dates
                            $dates['create_date'] = Carbon::createFromFormat('d/m/Y', $row[$headerMap['create_date']]);
                            $dates['due_date'] = Carbon::createFromFormat('d/m/Y', $row[$headerMap['due_date']]);
                            
                            // Optional dates
                            $dates['closed_date'] = !empty($row[$headerMap['closed_date']]) 
                                ? Carbon::createFromFormat('d/m/Y', $row[$headerMap['closed_date']])
                                : null;
                            
                            $dates['write_off_date'] = !empty($row[$headerMap['write_off_date']]) 
                                ? Carbon::createFromFormat('d/m/Y', $row[$headerMap['write_off_date']])
                                : null;

                            // Validate date logic
                            if ($dates['create_date']->gt($dates['due_date'])) {
                                throw new \Exception("Create date ({$row[$headerMap['create_date']]}) cannot be after due date ({$row[$headerMap['due_date']]})");
                            }

                            if ($dates['closed_date'] && $dates['closed_date']->lt($dates['create_date'])) {
                                throw new \Exception("Closed date ({$row[$headerMap['closed_date']]}) cannot be before create date ({$row[$headerMap['create_date']]})");
                            }

                            if ($dates['write_off_date'] && $dates['write_off_date']->lt($dates['create_date'])) {
                                throw new \Exception("Write-off date ({$row[$headerMap['write_off_date']]}) cannot be before create date ({$row[$headerMap['create_date']]})");
                            }

                            if ($dates['closed_date'] && $dates['write_off_date']) {
                                throw new \Exception("Contract cannot have both closed date ({$row[$headerMap['closed_date']]}) and write-off date ({$row[$headerMap['write_off_date']]})");
                            }

                            // Validate dates are not in the future
                            $today = Carbon::today();
                            if ($dates['create_date']->gt($today)) {
                                throw new \Exception("Create date ({$row[$headerMap['create_date']]}) cannot be in the future");
                            }
                            if ($dates['closed_date'] && $dates['closed_date']->gt($today)) {
                                throw new \Exception("Closed date ({$row[$headerMap['closed_date']]}) cannot be in the future");
                            }
                            if ($dates['write_off_date'] && $dates['write_off_date']->gt($today)) {
                                throw new \Exception("Write-off date ({$row[$headerMap['write_off_date']]}) cannot be in the future");
                            }

                        } catch (\Exception $e) {
                            if (str_contains($e->getMessage(), "Data missing")) {
                                throw new \Exception("Invalid date format. Use DD/MM/YYYY format");
                            }
                            throw $e;
                        }

                        // Validate opening_score
                        $openingScore = str_replace(['E', ','], '', $row[$headerMap['opening_score']]);
                        if (!is_numeric($openingScore)) {
                            throw new \Exception("Opening score ({$row[$headerMap['opening_score']]}) must be a number");
                        }
                        if ($openingScore < 0) {
                            throw new \Exception("Opening score ({$openingScore}) cannot be negative");
                        }
                        if ($openingScore > 100) {
                            throw new \Exception("Opening score ({$openingScore}) cannot be greater than 100");
                        }
                        if (strlen($openingScore) > 65) {
                            throw new \Exception("Opening score value is too large");
                        }

                        // Check for duplicate contract_id in current import
                        $contractId = $row[$headerMap['contract_id']];
                        $existingContract = Contract::where('contract_id', $contractId)
                            ->where('opening_score_period', '!=', $scorePeriod)
                            ->first();
                            
                        if ($existingContract) {
                            throw new \Exception("Contract ID ({$contractId}) already exists in period {$existingContract->opening_score_period}");
                        }

                        // All validations passed, create/update contract
                        Contract::updateOrCreate(
                            ['contract_id' => $contractId],
                            [
                                'customer_id' => $row[$headerMap['customer_id']],
                                'create_date' => $dates['create_date'],
                                'due_date' => $dates['due_date'],
                                'opening_score' => $openingScore,
                                'closed_date' => $dates['closed_date'],
                                'write_off_date' => $dates['write_off_date'],
                                'opening_score_period' => $scorePeriod,
                                'update_period' => $scorePeriod,
                            ]
                        );

                        $successCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $rowErrors[] = [
                            'row' => $rowNumber,
                            'data' => $row,
                            'error' => $e->getMessage()
                        ];
                        $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                    }
                }

                // If there are any errors, rollback and return error details
                if (!empty($errors)) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Import completed with errors',
                        'details' => [
                            'errors' => $errors,
                            'row_errors' => $rowErrors,
                            'total_rows' => count($data) - 1,
                            'successful' => $successCount,
                            'failed' => $failedCount,
                            'message' => 'Some rows could not be imported. Please check the errors and try again.'
                        ]
                    ], 422);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Import completed successfully',
                    'details' => [
                        'total_rows' => count($data) - 1,
                        'successful' => $successCount,
                        'failed' => $failedCount
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Contract import failed: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Database error',
                    'details' => [
                        'message' => 'A database error occurred while importing contracts.',
                        'error' => $e->getMessage()
                    ]
                ], 422);
            }
        } catch (\Exception $e) {
            \Log::error('Contract import failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Import failed',
                'details' => [
                    'message' => 'An unexpected error occurred during import.',
                    'error' => $e->getMessage()
                ]
            ], 422);
        }
    }

    public function downloadSample()
    {
        try {
            $filename = storage_path('app/contracts_sample.csv');
            
            if (!file_exists(storage_path('app'))) {
                mkdir(storage_path('app'), 0755, true);
            }
            
            $handle = fopen($filename, 'w');
            if ($handle === false) {
                throw new \Exception('Unable to create sample file');
            }
            
            try {
                // Write headers
                $headers = [
                    'contract_id',
                    'customer_id',
                    'create_date',
                    'due_date',
                    'opening_score',
                    'closed_date',
                    'write_off_date'
                ];
                
                if (fputcsv($handle, $headers) === false) {
                    throw new \Exception('Unable to write headers to sample file');
                }
                
                // Write sample data
                $sampleData = [
                    ['L001', 'CUS001', '01/01/2024', '31/12/2024', '75.5', '31/12/2024', ''],
                    ['L002', 'CUS002', '15/01/2024', '31/12/2024', '82.3', '', '']
                ];
                
                foreach ($sampleData as $row) {
                    if (fputcsv($handle, $row) === false) {
                        throw new \Exception('Unable to write sample data to file');
                    }
                }
            } finally {
                fclose($handle);
            }
            
            if (!file_exists($filename)) {
                throw new \Exception('Sample file was not created successfully');
            }
            
            return response()->download($filename, 'contracts_sample.csv', [
                'Content-Type' => 'text/csv',
            ])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            if (isset($filename) && file_exists($filename)) {
                unlink($filename);
            }
            
            \Log::error('Contract sample download failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Unable to generate sample file. Please try again or contact support.'
            ], 500);
        }
    }
}
