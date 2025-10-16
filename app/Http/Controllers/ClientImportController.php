<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ClientImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'names_file' => ['required', 'file', 'mimes:csv,txt']
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('names_file');
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            // Skip header row
            array_shift($lines);
            
            $totalRows = count($lines);
            $chunkSize = 100; // Process 100 records at a time
            $processed = 0;
            $errors = [];
            $successfulRecords = [];

            foreach (array_chunk($lines, $chunkSize) as $chunk) {
                foreach ($chunk as $line) {
                    $data = str_getcsv($line);
                    
                    if (count($data) !== 2) {
                        $errors[] = "Line format incorrect: $line";
                        continue;
                    }

                    $customerId = trim($data[0]);
                    $publicNameInfo = trim($data[1]);

                    // Split public name info by dash (phone-name)
                    $parts = array_map('trim', explode('-', $publicNameInfo));
                    
                    // if (count($parts) !== 2) {
                    //     $errors[] = "Public name format incorrect: $publicNameInfo. Expected format: PHONE-NAME";
                    //     continue;
                    // }

                    if(count($parts) !== 2) {
                        $phone = 'TBA';
                        $name = 'TBA';
                    }else{
                        $phone = $parts[0];
                        $name = $parts[1];
                    }

                    $phone = $parts[0];
                    $name = $parts[1];

                    // Validate phone number format (should start with 07 and be 10 digits)
                    if (!preg_match('/^07\d{8}$/', $phone)) {
                        $errors[] = "Invalid phone number format: $phone. Should be 10 digits starting with 07";
                        continue;
                    }

                    try {
                        $client = Client::create([
                            'external_id' => $customerId,
                            'name' => $name,
                            'mobile' => $phone,
                            'type' => 'individual',
                        ]);
                        
                        $successfulRecords[] = [
                            'id' => $client->id,
                            'external_id' => $customerId,
                            'name' => $name,
                            'mobile' => $phone,
                        ];
                        
                        $processed++;
                    } catch (\Exception $e) {
                        $errors[] = "Error processing customer $customerId: " . $e->getMessage();
                    }
                }

                // Commit each chunk
                DB::commit();
                DB::beginTransaction();
            }

            DB::commit();

            return Inertia::render('Clients/Index', [
                'flash' => [
                    'success' => $processed > 0 ? "Successfully imported $processed clients" : null,
                    'error' => !empty($errors) ? implode("\n", $errors) : null,
                ],
                'importResults' => [
                    'total' => $totalRows,
                    'processed' => $processed,
                    'failed' => count($errors),
                    'records' => $successfulRecords,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Client import error: ' . $e->getMessage());
            
            return Inertia::render('Clients/Index', [
                'flash' => [
                    'error' => 'Error processing file: ' . $e->getMessage(),
                ],
            ]);
        }
    }
}
