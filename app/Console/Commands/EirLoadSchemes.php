<?php

namespace App\Console\Commands;

use App\Services\Ebanker\ContractInputsBuildService;
use Illuminate\Console\Command;

/**
 * The scheme settings from the landed scheme master (SM_01, with DD_10
 * where SM_01 lacks the scheme or the field), so the schedule generator can
 * read the interest and instalment basis a scheme states (spec v3 section
 * 6.3). Also refreshes the over-sanction flag of spec 7.3. Run by the
 * bootstrap and by the month-end (maiic:task month-end).
 *
 *   php artisan eir:load-schemes
 */
class EirLoadSchemes extends Command
{
    protected $signature = 'eir:load-schemes';

    protected $description = 'Load the scheme settings from the landed scheme master (SM_01, then DD_10) and flag contracts drawn above their sanction';

    public function handle(ContractInputsBuildService $inputs): int
    {
        $s = $inputs->schemes();
        $this->info("{$s['rows']} schemes in SM_01 and DD_10: {$s['upserted']} loaded" . ($s['from_dd10'] > 0 ? " ({$s['from_dd10']} with a field or the scheme from DD_10)" : '') . ', ' . count($s['refused']) . ' refused.');
        foreach ($s['refused'] as $code => $why) {
            $this->warn("  scheme {$code}: {$why}");
        }
        if ($s['rows'] === 0) {
            $this->error('No scheme master (SM_01 or DD_10) is landed: the schedule generator has no scheme settings to read.');

            return self::FAILURE;
        }
        $o = $inputs->flagOverSanction();
        $this->info("{$o['flagged']} contract(s) drawn above their sanction, flagged for Credit" . ($o['contracts'] !== [] ? ': ' . implode(', ', array_slice($o['contracts'], 0, 12)) : '') . '.');

        return self::SUCCESS;
    }
}
