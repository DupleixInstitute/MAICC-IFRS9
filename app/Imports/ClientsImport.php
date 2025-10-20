<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Support\Facades\Storage;

class ClientsImport implements ToCollection, WithEvents, WithHeadingRow, WithChunkReading, ShouldQueue
{
    public Import $import;
    protected string $exceptionFilePath;

    public function __construct(Import $import)
    {
        $this->import = $import;
        $this->exceptionFilePath = storage_path("app/public/failed_imports/clients_exception_{$import->id}.csv");

        if (!file_exists(dirname($this->exceptionFilePath))) {
            mkdir(dirname($this->exceptionFilePath), 0755, true);
        }

        if (!file_exists($this->exceptionFilePath)) {
            $handle = fopen($this->exceptionFilePath, 'w');
            fputcsv($handle, ['CUSTOMER_ID', 'NAME']);
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
            Import::where('id', $this->import->id)->update([
                'started_at' => now(),
                'failed_file_path' => 'failed_imports/' . basename($this->exceptionFilePath),
            ]);
        }

        foreach ($rows as $row) {
            // $customerID = trim($row['customer_id'] ?? '');
            // $publicName = explode('-', $row['public_name'] ?? '');

            $customerID = isset($row['customer_id']) && !empty(trim($row['customer_id'])) ? trim($row['customer_id']) : 0;
            $name = isset($row['name']) && !empty(trim($row['name'])) ? trim($row['name']) : 'TBA';

            // $phoneNumber = isset($publicName[0]) && !empty(trim($publicName[0])) ? trim($publicName[0]) : '00000000000';
            // $name = isset($publicName[1]) && !empty(trim($publicName[1])) ? trim($publicName[1]) : 'TBA';

            // Log incomplete record to file only if name was missing
            if ($name === 'TBA' || $customerID == '00000000000') {
                $exceptions++;
                $this->appendExceptionRow($row->toArray());
            }

            $bulkInsert[] = [
                'customer_id' => $customerID,
                //'mobile' => $phoneNumber,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $inserted++;
        }

        if (!empty($bulkInsert)) {
            Client::upsert($bulkInsert, ['customer_id'], ['name', 'updated_at']);
        }
        Log::info('Upsert payload:', $bulkInsert);

        $import = Import::find($this->import->id);
        $import->records += $inserted;
        $import->failed_records += $exceptions; // still using this field
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
