<?php

namespace App\Console\Commands;

use App\Services\Ebanker\GlFeeBuildService;
use Illuminate\Console\Command;

/**
 * Builds the fee lines of the GL fee route from the landed GL_04a and GL_04b
 * (spec v4 sections 7.8.3 to 7.8.5; results review of 13 October 2026, E3),
 * applies the governed source precedence and reports the assignment and the
 * fee GL gate. Every line lands PENDING for Fee Classification.
 *
 *   php artisan eir:build-gl-fees --user=1
 */
class EirBuildGlFees extends Command
{
    protected $signature = 'eir:build-gl-fees {--user= : Id of the user the build is recorded against}';

    protected $description = 'Tie the GL fee postings (GL_04) to their loans, hold what cannot be tied, apply fee_source_precedence and check the fee GLs against the trial balance';

    public function handle(GlFeeBuildService $service): int
    {
        $r = $service->build($this->option('user') !== null ? (int) $this->option('user') : null);
        $this->info("{$r['legs']} fee postings: {$r['assigned']['lines']} on a loan (" . number_format($r['assigned']['amount'], 2) . "), {$r['held']['lines']} held (" . number_format($r['held']['amount'], 2) . ').');
        foreach ($r['assigned']['by_rule'] as $rule => $n) {
            $this->line("  on a loan by {$rule}: {$n}");
        }
        foreach ($r['held']['by_reason'] as $reason => $n) {
            $this->line("  held, {$reason}: {$n}");
        }
        $this->line("Loaded {$r['loaded']['loaded_rows']} new line(s) PENDING; {$r['precedence']['superseded_lines']} line(s) superseded under " . implode(' > ', $r['precedence']['order']) . '; ' . count($r['precedence']['exceptions']) . ' cross-source difference(s).');
        $this->line("Fee GL gate: {$r['gate']['agree']} month(s) agree, {$r['gate']['differ']} differ, {$r['gate']['no_tb']} without a trial balance.");
        foreach ($r['gate']['months'] as $m) {
            if ($m['status'] === 'DIFFERENCE') {
                $this->warn("  {$m['gl']} {$m['month']}: GL_04a " . number_format($m['gl_04a'], 2) . ' against the trial balance ' . number_format((float) $m['trial_balance'], 2));
            }
        }

        return self::SUCCESS;
    }
}
