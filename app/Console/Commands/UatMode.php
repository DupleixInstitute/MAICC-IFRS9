<?php

namespace App\Console\Commands;

use App\Services\Eir\GovernanceService;
use App\Support\MakerChecker;
use Illuminate\Console\Command;
use Throwable;

/**
 * UAT mode from the server console (Staging Module Specification v1, section
 * 8.9, item 3; decision S11). "on" lets administrators approve their own
 * proposals, and works only on a server whose APP_ENV is not production;
 * "off" works on any server. "status" says what is in force.
 *
 *   php artisan governance:uat-mode on --user=1
 *   php artisan governance:uat-mode off --user=1
 */
class UatMode extends Command
{
    protected $signature = 'governance:uat-mode {action : on, off or status} {--user= : the id of the person switching it (required for on and off)}';

    protected $description = 'Switch UAT mode (the maker-checker override for administrators) on or off from the server console; on only on a non-production server';

    public function handle(GovernanceService $governance): int
    {
        $action = strtolower((string) $this->argument('action'));
        if ($action === 'status') {
            $this->line('Environment: ' . app()->environment() . '. Setting: ' . ($governance->inForce(MakerChecker::SETTING)?->value ?? 'not set') . '. UAT mode ' . (MakerChecker::overrideInForce() ? 'IN FORCE' : 'not in force') . '.');

            return self::SUCCESS;
        }
        if (! in_array($action, ['on', 'off'], true)) {
            $this->error("Say on, off or status, not '{$action}'.");

            return self::FAILURE;
        }
        // the record always names who switched it (review of 15 October 2026)
        $user = $this->option('user') !== null ? \App\Models\User::find((int) $this->option('user')) : null;
        if ($user === null) {
            $this->error('Name the person switching UAT mode with --user=ID (their user id); the change is recorded against them.');

            return self::FAILURE;
        }
        try {
            $row = $governance->switchUatMode($action === 'on', (int) $user->id);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Maker-checker override: '{$row->value}' from {$row->effective_from->toDateString()} on this server (APP_ENV=" . app()->environment() . '). '
            . ($action === 'on' ? 'Every self-approval is recorded on the self-approvals report.' : 'Every workflow needs a second person again.'));

        return self::SUCCESS;
    }
}
