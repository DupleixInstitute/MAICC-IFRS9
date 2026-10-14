<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eir\ResetScheduleService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase P5, step 3: build a Reset version of the schedule at each detected
 * prime-rate change (run eir:detect-resets first).
 *
 *   php artisan eir:build-reset-schedules --contract=104430000087 --user=1
 */
class BuildResetSchedules extends Command
{
    protected $signature = 'eir:build-reset-schedules
        {--contract= : One loan account only}
        {--user= : The id of the person running it, recorded in the audit log}';

    protected $description = 'Re-amortise the remaining instalments at each prime-rate reset, as a Reset schedule version';

    public function handle(ResetScheduleService $service): int
    {
        $user = trim((string) $this->option('user'));
        if ($user === '' || ! ctype_digit($user) || User::query()->whereKey((int) $user)->doesntExist()) {
            $this->error('--user=<id> is required and must be an existing user id: the run is recorded against a person.');

            return self::INVALID;
        }
        auth()->onceUsingId((int) $user);

        try {
            $result = $service->build($this->option('contract') ?: null, (int) $user);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Reset schedules: {$result['built']} built, {$result['kept']} kept, {$result['blocked']} blocked, across {$result['contracts']} loan(s).");
        foreach ($result['details'] as $id => $d) {
            if ($d['status'] !== 'OK') {
                $this->warn("{$id}: {$d['reason']}");
                continue;
            }
            $this->line("<comment>{$id}</comment>");
            $this->table(['Reset', 'Rate', 'Version', 'State', 'From', 'Balance', 'Instalments', 'Instalment', 'Note'], array_map(fn ($v) => [
                $v['reset_date'], number_format($v['rate_pct'], 2) . '%', $v['version'] ?? '-', $v['state'],
                $v['from'] ?? '', isset($v['balance']) ? number_format($v['balance'], 2) : '',
                $v['instalments'] ?? '', isset($v['instalment']) ? number_format($v['instalment'], 2) : '', $v['reason'] ?? '',
            ], $d['versions']));
        }

        return self::SUCCESS;
    }
}
