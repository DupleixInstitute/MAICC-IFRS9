<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Import;
use App\Models\LoanBook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Facades\Excel;

class LoanBooksImport implements ToCollection, WithEvents, WithHeadingRow, WithChunkReading, ShouldQueue
{
    /** @var Import */
    public $import;
    /** @var array */
    public $data;
    /** @var string */
    protected $exceptionFilePath;

    protected function parseDate($value): ?string
    {
        $formats = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($value));
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

            // Fallback to general parse
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; 
        }
    }

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
            fputcsv($handle, ['CONTRACT_ID', 'EXTERNAL_IDENTITY_ID', 'STATUS', 'CREATE_TIME', 'OVER_DUE_DATE', 'OVERDUE_DAYS', 'AMOUNT']);
            fclose($handle);
        }
    }

        public function classifyIFRS9Stage($row) {
           // Log::debug('classifyIFRS9Stage input: ', $row);

            $ifrs9stage = '1';

            $clean = function($value) {
                    $value = str_replace(['-', "\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\t", "\n", "\r"], '', $value);
                    $value = trim(preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', mb_convert_encoding($value, 'UTF-8')));
            
                return is_numeric($value) && floatval($value) > 0;
            };

           if ($clean($row['181_270_days'] ?? null)) {
                $ifrs9stage = '3';
            } elseif ($clean($row['91_180_days'] ?? null)) {
                $ifrs9stage = '2';
            } elseif ($clean($row['31_90_days'] ?? null)) {
                $ifrs9stage = '2';
            } elseif ($clean($row['1_30_days'] ?? null)) {
                $ifrs9stage = '1';
            }

            //Log::debug("IFRS9 Stage determined: $ifrs9stage");

            return $ifrs9stage;
        }


    public function collection(Collection $rows)
    {
        $bulkInsert = [];
        $inserted = 0;
        $exceptions = 0;

        if (!Import::where('id', $this->import->id)->whereNull('started_at')->exists()) {
            // already started
        } else {
            $this->import->update([
                'started_at' => now(),
                'failed_file_path' => 'failed_imports/' . basename($this->exceptionFilePath),
            ]);
        }

        $reportingPeriod = $this->data['reporting_period'];
        [$year, $month] = explode('-', $reportingPeriod);

        $normalizedRow = [];
            foreach ($rows as $row) {
            try {
                $row = $row->toArray();
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedRow[strtolower(trim($key))] = $value;
                }

                // ✅ Use normalized keys
                $customerId = trim($normalizedRow['customer_id'] ?? '');
                if ($customerId === '') {
                    throw new \Exception('Missing customer_id');
                }

                $contractId = $normalizedRow['contract_id'] ?? null;

                $client = Client::where('customer_id', $customerId)->first();

                if (!$client) {
                   
                   // $publicName = explode('-', $normalizedRow['public_name'] ?? $normalizedRow['name'] ?? '');
                  //  $phone = isset($publicName[0]) && trim($publicName[0]) !== '' ? trim($publicName[0]) : '00000000000';
                   
                  $name = trim($normalizedRow['name'] ?? ''); 
                  if (empty($name)) {
                        $exceptions++;
                        $this->appendExceptionRow($normalizedRow);
                        Log::info("Client created or updated: customer_id={$customerId}, name={$name}");
                        continue;
                    }

                    $client = Client::updateOrCreate(
                        ['customer_id' => $customerId],
                        ['name' => $name]
                    );
                }

                //$createDate = Carbon::createFromFormat('d-m-Y H:i', trim($row['create_time']))->startOfDay();
                //Log::debug('Hex create_time: ' . bin2hex($row['create_time']));
                $rawCreateTime = $row['create_time'] ?? '';

                $createDate = $normalizedRow['value_date'] ?? null;

                // Normalize encoding and remove invisible characters
                $cleanCreateTime = trim(
                    preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', mb_convert_encoding($rawCreateTime, 'UTF-8'))
                );

                // try {
                //     $createDate = Carbon::createFromFormat('d/m/Y H:i', $cleanCreateTime)->startOfDay();
                //     $reportingEndDate = Carbon::createFromFormat('Y-m', $reportingPeriod)->endOfMonth()->startOfDay();

                //     $diffDays = $reportingEndDate->diffInDays($createDate, false);

                //     if ($diffDays <= 30) {
                //         $ifrs9Stage = 1;
                //     } elseif ($diffDays <= 90) {
                //         $ifrs9Stage = 2;
                //     } else {
                //         $ifrs9Stage = 3;
                //     }
                // } catch (\Exception $e) {
                //     Log::error("Row import exception: " . $e->getMessage());
                //     $this->appendExceptionRow($row->toArray());
                //     continue;
                // }

            try {
                $valueDateRaw = $normalizedRow['value_date'] ?? null;
                $valueDate = $this->parseDate($valueDateRaw);

                if (!$valueDate) {
                    throw new \Exception('Invalid or missing value_date');
                }
                $createDate = Carbon::createFromFormat('Y-m-d', $valueDate)->startOfDay();
                $reportingEndDate = Carbon::createFromFormat('Y-m', $reportingPeriod)->endOfMonth()->startOfDay();
                $remainingLife = $createDate->floatDiffInYears($reportingEndDate);

                Log::debug("Remaining life in years: $remainingLife");

            } catch (\Exception $e) {
                Log::error("Failed to parse create date: " . $e->getMessage());
                $this->appendExceptionRow($normalizedRow);
                continue;
            }



                $bulkInsert[] = [
                    'customer_id' => $client->id,
                    'customer_name'=>$normalizedRow['name'],
                    'loan_portfolio_id' => $this->data['loan_portfolio_id'],
                    'reporting_period' => $reportingPeriod,
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'contract_id' => $contractId,             
                   // 'external_identity_id' => $externalId,
                    'create_date' => $this->parseDate($row['value_date']),
                    'due_date' => $this->parseDate($row['maturity_date']),
                    'tenor'=> $normalizedRow['tenor'],
                    'interest_rate'=>$normalizedRow['interest_rate'],
                    'remaining_tenor'=> $remainingLife ?? 0,
                   // 'overdue_days' => $row['overdue_days'],
                    'principal_balance' =>$normalizedRow['principal'],
                    'disbursed'=>$normalizedRow['disbursed'] ?? 0,
                    'repayments' => $normalizedRow['repayments'],
                    'carrying_amount' => $normalizedRow['carrying_amount'] ?? 0,
                    //'contract_status' => $row['status'],
                    'ifrs9stage_pre_qualitative' => $this->classifyIFRS9Stage($normalizedRow),
                    'ifrs9stage_post_qualitative' => $this->classifyIFRS9Stage($normalizedRow),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $inserted++;
            } catch (\Exception $e) {
                Log::error("Row import exception: " . $e->getMessage());
                $exceptions++;
                $this->appendExceptionRow($row);

            }
        }

        if (!empty($bulkInsert)) {
            LoanBook::upsert(
                $bulkInsert,
                ['customer_id', 'loan_portfolio_id', 'reporting_period', 'contract_id','customer_name'],
                ['reporting_year', 'reporting_month', 'create_date', 'due_date', 'principal_balance', 'ifrs9stage_pre_qualitative', 'ifrs9stage_post_qualitative','updated_at']
            );
        }

        $import = Import::find($this->import->id);
        $import->records += $inserted;
        $import->failed_records += $exceptions; // still using this DB column
        $import->save();
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
                Log::info("LoanBooksImport started: Import ID {$this->import->id}");
            },
            AfterImport::class => function (AfterImport $event) {
                $this->import->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                Log::info("LoanBooksImport completed: Import ID {$this->import->id}");
            },
            ImportFailed::class => function (ImportFailed $event) {
                $this->import->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);
                Log::error("LoanBooksImport failed: " . $event->getException()->getMessage());
            },
        ];
    }
}
