<?php

namespace App\Imports;

/**
 * Header aliases for the reference-rate series (File C, spec v3 section 5.3).
 *
 * Keys are headings a source file may carry; values are the field names
 * ReferenceRateImportService reads. The E-Banker rate table spools with
 * "Appli From Date" and "PLR Rate"; the repaired file [PLR] carries
 * effective_date, plr_rate_pct and the three audit columns. A saved mapping
 * from the intake screen still overrides these.
 */
class ReferenceRateImport
{
    public static function aliases(): array
    {
        return [
            'EFFECTIVE_DATE' => 'effective_date',
            'EFFECTIVE DATE' => 'effective_date',
            'Appli From Date' => 'effective_date',
            'APPLI_FROM_DATE' => 'effective_date',
            'APPLICABLE_FROM_DATE' => 'effective_date',

            'RATE' => 'rate',
            'PLR Rate' => 'rate',
            'PLR_RATE' => 'rate',
            'PLR_RATE_PCT' => 'rate',
            'REFERENCE_RATE' => 'rate',

            'INDEX' => 'index_code',
            'INDEX_CODE' => 'index_code',

            'SOURCE_ROW' => 'source_row',
            'AS_DELIVERED' => 'as_delivered',
            'ORIGINAL_CELL_AS_DELIVERED' => 'as_delivered',
            'INTERPRETATION' => 'interpretation',
        ];
    }
}
