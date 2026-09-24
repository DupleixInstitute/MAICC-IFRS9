<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Eir\SpreadDerivationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Derives the spread added to the prime rate (margin) for every contract
 * from the monthly loan books and the reference-rate series (decision D13).
 */
class DeriveSpreads extends Command
{
    protected $signature = 'eir:derive-spreads
        {--period= : Read loan-book months up to and including this one (YYYY-MM); all months when omitted}
        {--index=PLR : The reference-rate series to subtract}
        {--user= : The id of the person running it, recorded in the audit log}';

    protected $description = 'Derive each contract\'s spread over the reference rate from the loan books and flag any that drifts';

    public function handle(SpreadDerivationService $service): int
    {
        $user = trim((string) $this->option('user'));
        if ($user !== '') {
            if (! ctype_digit($user) || User::query()->whereKey((int) $user)->doesntExist()) {
                $this->error("--user={$user} is not an existing user id.");

                return self::INVALID;
            }
            // So the audit entry carries the person, not just the meta.
            auth()->onceUsingId((int) $user);
        }

        try {
            $result = $service->derive(
                $this->option('period') ?: null,
                (string) $this->option('index'),
                $user === '' ? null : (int) $user
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Spreads derived against %s%s.',
            $result['index_code'],
            $result['as_at_period'] ? " up to {$result['as_at_period']}" : ' over every loan-book month'
        ));
        $this->table(
            ['Contracts', 'Derived', 'Drifting (held)', 'No loan-book rate', 'Before first reference rate', 'Supplied agrees', 'Supplied disagrees'],
            [[
                $result['contracts'], $result['derived'], $result['drifted'], $result['no_data'],
                $result['no_reference_rate'], $result['supplied_agree'], $result['supplied_disagree'],
            ]]
        );

        $flagged = array_filter($result['details'], fn ($d) => $d['status'] === SpreadDerivationService::STATUS_DRIFT
            || $d['supplied_agrees'] === false);
        if ($flagged !== []) {
            $this->newLine();
            $this->warn(count($flagged) . ' contract(s) need a look:');
            $this->table(
                ['Contract', 'Status', 'Months', 'Spread', 'Min', 'Max', 'Supplied', 'Note'],
                array_map(fn ($id, $d) => [
                    $id, $d['status'], $d['observations'],
                    $d['spread'] === null ? '-' : number_format($d['spread'], 2),
                    $d['min'] === null ? '-' : number_format($d['min'], 2),
                    $d['max'] === null ? '-' : number_format($d['max'], 2),
                    $d['supplied_pp'] === null ? '-' : number_format($d['supplied_pp'], 2),
                    $d['note'],
                ], array_keys($flagged), array_values($flagged))
            );
        }

        return self::SUCCESS;
    }
}
