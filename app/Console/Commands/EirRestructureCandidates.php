<?php

namespace App\Console\Commands;

use App\Services\Eir\RateResetService;
use App\Services\Eir\RestructureCandidateService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Find the restructure candidates and the rate moves from the landing zone
 * (specification v4 sections 3.9 and P5). The rate moves are the one reset
 * detection (RateResetService::detect runs the reset pipeline's detector,
 * eir:detect-resets, then sets each reset against the loan book), so the
 * bootstrap and the month-end detect each reset once. Detection only: nothing is ever
 * confirmed here; each candidate and each move waits for one person's
 * classification and another's approval on EIR Data, Restructures & Resets.
 * Safe for the bootstrap and the month-end job.
 *
 *   php artisan eir:restructure-candidates
 *   php artisan eir:restructure-candidates --only=resets
 */
class EirRestructureCandidates extends Command
{
    protected $signature = 'eir:restructure-candidates {--only= : candidates or resets}';

    protected $description = 'Find candidate restructures in E-Banker\'s reschedule history (RS_02) and record the rate moves of floating loans; nothing is confirmed';

    public function handle(RestructureCandidateService $candidates, RateResetService $resets): int
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(0);
        $only = (string) $this->option('only');
        try {
            if ($only === '' || $only === 'candidates') {
                $c = $candidates->detect();
                $this->info(sprintf('Restructure candidates: %d found (%d cannot be booked: no date, old terms or new schedule), %d written, %d kept because a person has acted on them, %d removed.',
                    $c['found'], $c['incomplete'], $c['written'], $c['kept'], $c['removed']));
                $this->line('  by evidence: ' . json_encode($c['by_source']) . '; engine\'s suggestion: ' . json_encode($c['by_suggestion']));
            }
            if ($only === '' || $only === 'resets') {
                $r = $resets->detect();
                $p = $r['pipeline'];
                $this->info(sprintf('Prime-rate resets (the one detector, eir:detect-resets): %d floating loans, %d resets, %d loans held.', $p['floating'], $p['resets'], $p['blocked']));
                $this->info(sprintf('Rate moves: %d PLR resets and %d spread changes recorded (%d held from the reset pipeline, %d on loans the engine treats as fixed-rate that moved with the PLR); %d pipeline resets the loan book does not show; %d moves of fixed-rate loans written as restructure candidates; %d moves with no PLR in force left out.',
                    $r['plr_resets'], $r['spread_changes'], $r['plr_resets_held'], $r['fixed_moved_with_plr'], $r['not_in_loan_book'], $r['fixed_rate_candidates'], $r['no_reference_rate']));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line('Nothing was confirmed: classify and approve on EIR Data, Restructures & Resets.');

        return self::SUCCESS;
    }
}
