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
    public Import $import;
    public array $data;
    protected string $exceptionFilePath;

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

        foreach ($rows as $row) {
            try {
                $externalId = trim($row['external_identity_id']);
                $contractId = $row['contract_id'];

                $client = Client::where('external_id', $externalId)->first();

                if (!$client) {
                    $publicName = explode('-', $row['public_name'] ?? $row['name'] ?? '');
                    $phone = isset($publicName[0]) && trim($publicName[0]) !== '' ? trim($publicName[0]) : '00000000000';
                    $name = isset($publicName[1]) && trim($publicName[1]) !== '' ? trim($publicName[1]) : 'TBA';

                    if (!isset($publicName[1]) || empty(trim($publicName[1]))) {
                        $exceptions++;
                        $this->appendExceptionRow($row->toArray());
                    }

                    $client = Client::updateOrCreate(
                        ['external_id' => $externalId],
                        ['mobile' => $phone, 'name' => $name]
                    );
                }

                // Date parsing
                //$createDate = Carbon::createFromFormat('d-m-Y H:i', trim($row['create_time']))->startOfDay();
                //Log::debug('Hex create_time: ' . bin2hex($row['create_time']));
                $rawCreateTime = $row['create_time'] ?? '';

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
                    $createDate = Carbon::createFromFormat('d/m/Y H:i', $cleanCreateTime)->startOfDay();
                    $reportingEndDate = Carbon::createFromFormat('Y-m', $reportingPeriod)->endOfMonth()->startOfDay();

                    $diffDays = $createDate->diffInDays($reportingEndDate, false);


                    if ($diffDays >= 0 && $diffDays <= 30) {
                        $ifrs9Stage = 1;
                    } elseif ($diffDays > 30 && $diffDays <= 90) {
                        $ifrs9Stage = 2;
                    } elseif ($diffDays > 90) {
                        $ifrs9Stage = 3;
                    } else {
                        
                        $ifrs9Stage = 1;
                    }

                    if(isset($row->overdue_days)){
                        $daysValue = (int)$row->overdue_days;

                        if ($daysValue >= 0 && $daysValue <= 30) {
                            $ifrs9Stage = 1;
                        } elseif ($daysValue > 30 && $daysValue <= 180) {
                            $ifrs9Stage = 2;
                        } elseif ($daysValue > 180) {
                            $ifrs9Stage = 3;
                        }

                    }

                    // Optional for debugging
                    //Log::debug("Contract {$contractId} - Create: {$createDate}, ReportEnd: {$reportingEndDate}, Days: {$diffDays}, Stage: {$ifrs9Stage}");

                } catch (\Exception $e) {
                    Log::error("Failed to parse date or calculate stage: " . $e->getMessage());
                    $this->appendExceptionRow($row->toArray());
                    continue;
                }


                $bulkInsert[] = [
                    'client_id' => $client->id,
                    'loan_portfolio_id' => $this->data['loan_portfolio_id'],
                    'reporting_period' => $reportingPeriod,
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'contract_id' => $contractId,
                    'external_identity_id' => $externalId,
                    'create_date' => $this->parseDate($row['create_time']),
                    'due_date' => $this->parseDate($row['over_due_date']),
                    'overdue_days' => $row['overdue_days'],
                    'principal_balance' => $row['amount'],
                    'contract_status' => $row['status'],
                    'calculated_ifrs9_stage' => $ifrs9Stage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $inserted++;
            } catch (\Exception $e) {
                Log::error("Row import exception: " . $e->getMessage());
                $exceptions++;
                $this->appendExceptionRow($row->toArray());
            }
        }

        if (!empty($bulkInsert)) {
            LoanBook::upsert(
                $bulkInsert,
                ['client_id', 'loan_portfolio_id', 'reporting_period', 'contract_id', 'external_identity_id'],
                ['reporting_year', 'reporting_month', 'create_date', 'due_date', 'overdue_days', 'principal_balance', 'contract_status', 'calculated_ifrs9_stage', 'updated_at']
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
