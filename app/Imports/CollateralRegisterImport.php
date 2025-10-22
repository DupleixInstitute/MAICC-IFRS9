<?php
namespace App\Imports;

use App\Models\CollateralRegister;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;

class CollateralRegisterImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) { // Skip headers

            try {
                $row = $row->toArray();
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedRow[strtolower(trim($key))] = $value;
                }
                CollateralRegister::create([
                    'register_number' => $normalizedRow['register_number'],
                    'customer_id' => $normalizedRow['customer_id'],
                    'customer_name' =>$normalizedRow['customer_name'],
                    'collateral_type' => $normalizedRow['collateral_type'],
                    'property_use' => $normalizedRow['property_use'],
                    'description' => $normalizedRow['description'],
                    'location' => $normalizedRow['location'],
                    'registration_date' => Carbon::parse($row[7]),
                    'expiry_date' => Carbon::parse($row[8]),
                    'valuation_date' => Carbon::parse($row[9]),
                    'nominal_value' => $normalizedRow['nominal_value'],
                    'market_value' => $normalizedRow['market_value'],
                    'execution_value' => $normalizedRow['execution_value'],
                   // 'status' => strtoupper(trim($row[13])) ?: 'ACTIVE',
                   // 'notes' => $row[14] ?? null,
                ]);
            } catch (\Exception $e) {
                \Log::error('Collateral import error: ' . $e->getMessage());
                continue;
            }
        }
    }
}
