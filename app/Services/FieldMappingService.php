<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FieldMappingService
{
    /**
     * Table-specific column exclusions.
     * "*" applies to ALL tables by default.
     */
    protected array $exclusions = [
        // Global exclusions for all tables
        '*' => [
            'created_at',
            'updated_at',
            'deleted_at',
        ],

        // Custom exclusions per table
        'clients' => [
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ],

        'users' => [
            'password',
            'remember_token',
            'created_at',
            'updated_at',
        ],

        'loans' => [
            'loan_status', 
            'created_at', 
            'updated_at', 
            'deleted_at'],
    ];

    public function getTableColumns(string $tableName, bool $withDetails = false): array
    {
        if (!Schema::hasTable($tableName)) {
            return [];
        }

        // Fetch raw columns
        $columns = Schema::getColumnListing($tableName);

        // Determine exclusion list
        $exclude = $this->exclusions[$tableName] ?? $this->exclusions['*'];

        // Apply exclusion
        $columns = array_values(array_diff($columns, $exclude));

        // If only simple list is required
        if (!$withDetails) {
            return $columns;
        }

        // Build detailed list
        $details = [];
        foreach ($columns as $column) {
            $details[$column] = [
                'description' => "Field for $column",
                'example'     => DB::table($tableName)->value($column) ?? 'Example',
                'required'    => $column === 'customer_id', // marking customer_id required
            ];
        }

        return $details;
    }
}
