<?php

namespace App\Console\Commands;

use App\Models\EirRatePeriod;
use App\Models\User;
use App\Services\Eir\ResetDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Phase P5, step 2: list each floating loan's prime-rate resets and write its
 * rate periods (spec v3 section 7.3). The EIR of each period is the next step.
 *
 *   php artisan eir:detect-resets --user=1
 *   php artisan eir:detect-resets --contract=104430000087 --user=1
 */
class DetectRateResets extends Command
{
    protected $signature = 'eir:detect-resets
        {--contract= : One loan account only; also prints its resets and periods}
        {--as-of= : Apply prime-rate changes up to this date (YYYY-MM-DD); today when omitted}
        {--index=PLR : The reference-rate series}
        {--user= : The id of the person running it, recorded in the audit log}';

    protected $description = 'Detect prime-rate resets for each floating loan and write its rate periods';

    public function handle(ResetDetectionService $service): int
    {
        $user = trim((string) $this->option('user'));
        if ($user === '' || ! ctype_digit($user) || User::query()->whereKey((int) $user)->doesntExist()) {
            $this->error('--user=<id> is required and must be an existing user id: the run is recorded against a person.');

            return self::INVALID;
        }
        auth()->onceUsingId((int) $user);

        try {
            $asOf = $this->option('as-of') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('as-of')) : null;
            $result = $service->detect($this->option('contract') ?: null, $asOf, (string) $this->option('index'), (int) $user);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Prime-rate resets detected against {$result['index_code']} up to {$result['as_of']}.");
        $this->table(['Contracts', 'Floating', 'Fixed', 'Blocked', 'Out of scope', 'Resets'], [[
            $result['contracts'], $result['floating'], $result['fixed'], $result['blocked'], $result['out_of_scope'], $result['resets'],
        ]]);

        $blocked = array_filter($result['details'], fn ($d) => $d['status'] === ResetDetectionService::STATUS_BLOCKED);
        if ($blocked !== []) {
            $this->warn(count($blocked) . ' loan(s) held:');
            $this->table(['Contract', 'Reason'], array_map(fn ($id, $d) => [$id, $d['reason']], array_keys($blocked), $blocked));
        }

        $floating = array_filter($result['details'], fn ($d) => $d['status'] === ResetDetectionService::STATUS_FLOATING);
        if ($floating !== [] && ! $this->option('contract')) {
            $this->table(['Contract', 'Rate behaviour from', 'Spread', 'Spread basis', 'Resets'], array_map(
                fn ($id, $d) => [$id, $d['rate_behaviour_source'], number_format($d['spread'], 2), $d['spread_basis'], count($d['resets'])],
                array_keys($floating), $floating
            ));
        }

        if ($id = $this->option('contract')) {
            $d = $result['details'][$id] ?? null;
            if ($d === null) {
                $this->warn("No contract {$id} in the contract master.");

                return self::SUCCESS;
            }
            $this->line("<comment>{$id}</comment>: {$d['status']}"
                . ($d['rate_behaviour_source'] ? " (from {$d['rate_behaviour_source']})" : '')
                . ($d['spread'] !== null ? ", rate at origination {$d['rate_at_origination']}% = prime {$d['reference_at_origination']} + spread " . number_format($d['spread'], 2) . " ({$d['spread_basis']})" : ''));
            $periods = EirRatePeriod::where('contract_id', $id)->orderBy('effective_from')->get();
            $this->table(['#', 'From', 'To', 'Prime', 'Spread', 'Rate', 'Status'], $periods->map(fn ($p) => [
                $p->period_no, $p->effective_from->toDateString(), $p->effective_to?->toDateString() ?? 'open',
                $p->reference_rate === null ? '-' : number_format($p->reference_rate, 2),
                $p->spread_over_prime === null ? '-' : number_format($p->spread_over_prime, 2),
                number_format($p->contractual_rate * 100, 2) . '%', $p->status,
            ])->all());
        }

        foreach ($result['details'] as $id => $d) {
            foreach ($d['kept'] ?? [] as $note) {
                $this->warn("{$id}: {$note}");
            }
        }

        return self::SUCCESS;
    }
}
