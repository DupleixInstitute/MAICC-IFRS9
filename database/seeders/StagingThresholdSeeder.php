<?php

namespace Database\Seeders;

use App\Models\StagingThreshold;
use Illuminate\Database\Seeder;

/**
 * Staging threshold rules (docs/EIR_Build.md §4 Phase 2).
 *
 * DEFAULT reproduces the ladder previously hardcoded in
 * LoanBooksImport::classifyIFRS9Stage — importing this seed changes
 * nothing about staging behaviour.
 *
 * LONG_TERM is the 90-day Stage-2 backstop for long-tenor facilities
 * recommended in the 2025 independent review (finding 1), rebutting the
 * 30-DPD presumption (IFRS 9 B5.5.37) on the basis of the RBM directive.
 * It is seeded FUTURE-DATED (inactive) and only governs once Dr Thom
 * signs the rebuttal and the effective date is brought forward
 * (open item #5 in docs/Development_of_EIR.md).
 */
class StagingThresholdSeeder extends Seeder
{
    public function run(): void
    {
        StagingThreshold::updateOrCreate(
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0],
            [
                'stage2_dpd'     => 31,
                'stage3_dpd'     => 181,
                'rebuttal_basis' => null,
                'effective_from' => '2020-01-01',
            ]
        );

        StagingThreshold::updateOrCreate(
            ['facility_class' => 'LONG_TERM', 'min_tenor_months' => 36],
            [
                'stage2_dpd'     => 91,
                'stage3_dpd'     => 181,
                'rebuttal_basis' => 'Rebuttal of the IFRS 9 B5.5.37 30-DPD presumption for long-tenor '
                    . 'facilities: the RBM Financial Asset Classification Directive (2014) classifies '
                    . 'long-term facilities on a 90-day cycle, and DFI lending exhibits seasonal '
                    . 'cash-flow lumpiness (moratoria, agricultural cycles) in which a 30-day slip '
                    . 'does not evidence a significant increase in credit risk. '
                    . 'PENDING CFO SIGN-OFF — rule is future-dated (inactive) until approved.',
                'effective_from' => '2099-01-01',
            ]
        );
    }
}
