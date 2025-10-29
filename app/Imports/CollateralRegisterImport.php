<?php
namespace App\Imports;

use App\Models\CollateralRegister;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Support\Facades\Storage;

class CollateralRegisterImport implements ToCollection, WithHeadingRow, ShouldQueue, WithChunkReading, WithEvents
{
    public $import;
    protected $exceptionFilePath;

    public function __construct(Import $import, array $data)
            {
                $this->import = $import;
                $this->data = $data;

                $this->exceptionFilePath = storage_path("app/public/failed_imports/loan_books_exception_{$import->id}.csv");
                if (!file_exists(dirname($this->exceptionFilePath))) {
                    mkdir(dirname($this->exceptionFilePath), 0755, true);
                }
                if (!file_exists($this->exceptionFilePath)) {
                    $handle = fopen($this->exceptionFilePath, 'w');
                    fputcsv($handle, ['register_number','customer_id','customer_name','collateral_type','property_use',
                                                        'description', 'location', 'registration_date','expiry_date','valuation_date',  
                                                        'nominal_value',  'market_value','execution_value', 'status',
                                                     ]);
                    fclose($handle);
                }
            }

    public function collection(Collection $rows)
    {

        $bulkInsert = [];
        $inserted = 0;
        $exceptions = 0;

        foreach ($rows as $row) { // Skip headers

            try {
                $row = $row->toArray();
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedRow[strtolower(trim($key))] = $value;
                }


               $bulkInsert[] =
               
            //    CollateralRegister::create([
            //        // 'register_number' => $normalizedRow['register_number'] ?: null,
            //         'customer_id' => $normalizedRow['customer_id'] ?? null,
            //         'customer_name' =>$normalizedRow['name'],
            //         'collateral_type' => $normalizedRow['collateral_type'],
            //         'property_use' => $normalizedRow['property_use'] ? : null,
            //         'description' => $normalizedRow['description'] ? : null,
            //         //'location' => $normalizedRow['location'] ? : null,
            //         'registration_date' => Carbon::parse($normalizedRow['registration_date']) ? : null,
            //         'expiry_date' => Carbon::parse($normalizedRow['expiry_date']) ? : null,
            //         'valuation_date' => Carbon::parse($normalizedRow['valuation_date']) ? : null,
            //         'nominal_value' => $normalizedRow['nominal_value'],
            //         //'market_value' => $normalizedRow['market_value'],
            //         'execution_value' => $normalizedRow['execution_value'] ? : null,
            //        // 'status' => strtoupper(trim($row[13])) ?: 'ACTIVE',
            //        // 'notes' => $row[14] ?? null,
            //     ]);

                    CollateralRegister::updateOrCreate(
                    [
                        'customer_id' => $normalizedRow['customer_id'] ?? null,
                        'collateral_type' => $normalizedRow['collateral_type'] ?? null,
                    ],
                    [
                        'customer_name' => $normalizedRow['name'] ?? null,
                        'property_use' => $normalizedRow['property_use'] ?? null,
                        'description' => $normalizedRow['description'] ?? null,
                        // //'registration_date' => isset($normalizedRow['registration_date'])
                        //     ? Carbon::parse($normalizedRow['registration_date'])
                        //     : null,
                        // 'expiry_date' => isset($normalizedRow['expiry_date'])
                        //     ? Carbon::parse($normalizedRow['expiry_date'])
                        //     : null,
                        // 'valuation_date' => isset($normalizedRow['valuation_date'])
                        //     ? Carbon::parse($normalizedRow['valuation_date'])
                        //     : null,
                        'nominal_value' => $normalizedRow['nominal_value'] ?? null,
                        'execution_value' => $normalizedRow['execution_value'] ?? null,
                        'market_value' => $normalizedRow['market_value'] ?? null,
                    ]
                );
                $inserted++;
            } catch (\Exception $e) {
                \Log::error('Collateral import error: ' . $e->getMessage());
                $exceptions++;
                $this->appendExceptionRow($row);
            }
        }
    }

        protected function appendExceptionRow(array $row)
            {
                $handle = fopen($this->exceptionFilePath, 'a');
                fputcsv($handle, $row);
                fclose($handle);
            }

        public function chunkSize(): int
            {
                return 1000;
            }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->import->update([
                    'status' => 'processing',
                ]);
            },
            AfterImport::class => function (AfterImport $event) {
                $this->import->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            },
            ImportFailed::class => function (ImportFailed $event) {
                Log::error('Import failed: ' . $event->getException()->getMessage());
                $this->import->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);
            },
        ];
}
}