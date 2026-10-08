<?php

namespace Database\Seeders;

use App\Models\StagingThreshold;
use Illuminate\Database\Seeder;

/**
 * Staging threshold rules, governed by tenor class as the Reserve Bank of
 * Malawi's Financial Services (Credit Risk Management for Development Finance
 * Institutions) Directive, 2018 classifies facilities (Malawi Gazette Supplement
 * 13 July 2018, No. 18A, pp. 42-47; a copy is under docs/regulatory).
 *
 * The directive (s.2, s.10, Schedule):
 *   short term  = repayment period of not more than 12 months
 *                 standard to 30 days; special mention 31-90; substandard 91-180;
 *                 doubtful 181-365; loss > 365
 *   medium term = 12 to 60 months, long term = over 60 months
 *                 standard to 90 days; special mention 91-180; substandard 181-365;
 *                 doubtful 366-746; loss > 746
 *   non-performing = substandard, doubtful or loss, placed on non-accrual (s.13)
 *
 * Stage 3 (credit-impaired) is therefore the directive's non-performing line:
 * 91 days past due for a short-term facility and 181 days for a medium- or
 * long-term one. That rebuts the IFRS 9 B5.5.37 presumption that default occurs
 * no later than 90 days past due for the medium- and long-term book, on the
 * authority of the regulator's own classification, which is what MAIIC's ECL
 * model has applied (>181 days) and what the 2025 financial statements rest on.
 *
 * Stage 2 is kept at 31 days past due for every tenor: the IFRS 9 B5.5.11
 * presumption of a significant increase in credit risk at 30 days is NOT
 * rebutted. The directive's wider standard band for medium- and long-term
 * facilities (to 90 days) is a prudential classification, not evidence that
 * credit risk has not increased; the LONG_TERM row below keeps that rebuttal
 * available as a proposal, future-dated and inactive until Dr Thom signs it.
 *
 * Rows are selected by StagingThreshold::forFacility(): a facility-class row
 * first, else the DEFAULT row with the highest min_tenor_months not above the
 * loan's tenor. Seeding is idempotent on (facility_class, min_tenor_months).
 */
class StagingThresholdSeeder extends Seeder
{
    public const DIRECTIVE = 'Financial Services (Credit Risk Management for Development Finance Institutions) Directive, 2018 '
        . '(Malawi Gazette Supplement 13 July 2018, No. 18A)';

    public function run(): void
    {
        // Short-term facilities (repayment period not more than 12 months).
        StagingThreshold::updateOrCreate(
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 0],
            [
                'stage2_dpd'     => 31,
                'stage3_dpd'     => 91,
                'rebuttal_basis' => 'Short-term facility (repayment period not more than 12 months, ' . self::DIRECTIVE . ' s.2). '
                    . 'Stage 3 at 91 days past due is the directive\'s substandard (non-performing) line for short-term '
                    . 'facilities (s.10(c)(i)), placed on non-accrual under s.13; it coincides with the IFRS 9 B5.5.37 '
                    . '90-day presumption, so no rebuttal is needed. Stage 2 at 31 days keeps the IFRS 9 B5.5.11 presumption.',
                'effective_from' => '2018-07-13',
            ]
        );

        // Medium- and long-term facilities (repayment period over 12 months).
        StagingThreshold::updateOrCreate(
            ['facility_class' => 'DEFAULT', 'min_tenor_months' => 13],
            [
                'stage2_dpd'     => 31,
                'stage3_dpd'     => 181,
                'rebuttal_basis' => 'Medium- or long-term facility (repayment period over 12 months, ' . self::DIRECTIVE . ' s.2). '
                    . 'Stage 3 at 181 days past due is the directive\'s substandard (non-performing) line for medium- and '
                    . 'long-term facilities (s.10(c)(ii)), placed on non-accrual under s.13. This rebuts the IFRS 9 B5.5.37 '
                    . 'presumption that default occurs no later than 90 days past due, on the authority of the regulator\'s '
                    . 'classification of development-finance lending, and is the basis MAIIC\'s ECL model (>181 days) and the '
                    . '2025 financial statements rest on. Stage 2 at 31 days keeps the IFRS 9 B5.5.11 presumption.',
                'effective_from' => '2018-07-13',
            ]
        );

        // Mega Farm programme loans: seasonal input finance repaid after harvest,
        // short-term under the directive whatever tenor the account carries.
        StagingThreshold::updateOrCreate(
            ['facility_class' => 'MEGA_FARM', 'min_tenor_months' => 0],
            [
                'stage2_dpd'     => 31,
                'stage3_dpd'     => 91,
                'rebuttal_basis' => 'Mega Farm programme facility: seasonal farm-input finance repaid after harvest, a short-term '
                    . 'facility under ' . self::DIRECTIVE . ' s.2, non-performing from 91 days past due (s.10(c)(i)). '
                    . 'Spec v4 section 16.8. The basis the 2025 provision of K39.76 billion was staged on is to be confirmed by Finance.',
                'effective_from' => '2018-07-13',
            ]
        );

        // Proposal, inactive until signed: Stage 2 at 91 days for medium- and
        // long-term facilities, rebutting the 30-day SICR presumption on the
        // directive's standard band (31 to 90 days is still "standard").
        StagingThreshold::updateOrCreate(
            ['facility_class' => 'LONG_TERM', 'min_tenor_months' => 13],
            [
                'stage2_dpd'     => 91,
                'stage3_dpd'     => 181,
                'rebuttal_basis' => 'PROPOSAL, PENDING CFO SIGN-OFF (future-dated, inactive). Rebuttal of the IFRS 9 B5.5.11 30-day '
                    . 'presumption of a significant increase in credit risk for medium- and long-term facilities: under '
                    . self::DIRECTIVE . ' s.10(a)(iii) a medium- or long-term facility 31 to 90 days overdue is still classified '
                    . 'standard, and development-finance cash flows are seasonal (moratoria, agricultural cycles), so a 30-day slip '
                    . 'does not by itself evidence a significant increase in credit risk. Open item O13 (staging rebuttal), spec v4 4.2.',
                'effective_from' => '2099-01-01',
            ]
        );

        $this->command?->info('Staging thresholds seeded: short-term 31/91, medium and long-term 31/181, Mega Farm 31/91, long-term Stage 2 proposal inactive.');
    }
}
