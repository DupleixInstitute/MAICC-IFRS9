<?php

namespace App\Console\Commands;

use App\Services\Ebanker\TakeonLandingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Land the take-on workbook (spec v4 section 6.9) and build the take-on
 * population under the governed basis.
 *
 *   php artisan eir:land-takeon --user=1 --build
 *   php artisan eir:land-takeon --original="..." --mapping="..." --dry-run
 */
class LandTakeonWorkbook extends Command
{
    protected $signature = 'eir:land-takeon
        {--original= : Tamanda\'s workbook as received (defaults to the committed copy under docs/bootstrap/takeon)}
        {--mapping= : The mapping workbook with the fee columns (defaults to the committed copy)}
        {--user=}
        {--build : Also build contract_takeon under takeon_history_basis}
        {--dry-run : Run the gates and report; write nothing}';

    protected $description = 'Land the take-on schedules workbook through its gates and build the take-on population';

    public function handle(TakeonLandingService $service): int
    {
        $dir = base_path('docs/bootstrap/takeon');
        $original = $this->option('original') ?? $this->find($dir, 'as received');
        $mapping = $this->option('mapping') ?? $this->find($dir, 'with mapping');
        if ($original === null || $mapping === null) {
            $this->error('Name both workbooks: --original and --mapping.');
            return self::FAILURE;
        }
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        try {
            $r = $service->land($original, $mapping, $user, (bool) $this->option('dry-run'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info("Take-on workbook {$r['status']} (load {$r['load_id']}): {$r['blocks']} blocks, {$r['lines']} schedule lines");
        $s = $r['gates']['summary'] ?? [];
        if ($s !== []) {
            $this->table(['Blocks', 'Mapped', 'Flagged', 'Not matched', 'Refused', 'Principal = take-on posting', 'No fee row'],
                [[$s['blocks'], $s['mapped'], $s['flagged'], $s['not_matched'], $s['refused'], $s['principal_exact'], $s['no_fee_row']]]);
        }
        $shown = 0;
        foreach ($r['gates']['blocks'] ?? [] as $no => $g) {
            foreach ($g['refusals'] as $m) {
                $this->line("  block {$no} REFUSED: {$m}");
            }
            if ($shown < 12 && $g['flags'] !== []) {
                $this->line("  block {$no}: " . implode('; ', $g['flags']));
                $shown++;
            }
        }
        if (str_ends_with($r['status'], 'QUARANTINED')) {
            return self::FAILURE;
        }
        if ($this->option('build') && ! $this->option('dry-run')) {
            try {
                $c = $service->build($user);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $this->info("Take-on population built under '{$c['setting']}': {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} starting at the take-on balance, {$c['refused']} refused.");
        }

        return self::SUCCESS;
    }

    private function find(string $dir, string $needle): ?string
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.xlsx') ?: [] as $f) {
            if (stripos(basename($f), $needle) !== false) {
                return $f;
            }
        }

        return null;
    }
}
