<?php

namespace App\Console\Commands;

use App\Models\HelpCategory;
use Database\Seeders\HelpAdminContentSeeder;
use Database\Seeders\HelpContentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replace a help-centre manual with the current seed content (Ticket #011).
 * The seeders refuse to touch a manual that already has content so that
 * authored edits survive `db:seed`; this command is the deliberate way to
 * discard those edits and reload the shipped text after a documentation
 * release. It removes the manual's chapters (articles, steps, figures and
 * page mappings cascade) inside a transaction, then runs the seeder.
 *
 *   php artisan manual:reseed user --force
 *   php artisan manual:reseed admin --force
 */
class ReseedManualCommand extends Command
{
    protected $signature = 'manual:reseed
        {manual=user : Which manual to replace: user or admin}
        {--force : Required; confirms that authored edits to this manual are discarded}';

    protected $description = 'Replace the User or Administrator Manual with the shipped seed content.';

    public function handle(): int
    {
        $manual = (string) $this->argument('manual');
        if (! in_array($manual, ['user', 'admin'], true)) {
            $this->error('Manual must be "user" or "admin".');

            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->error('Refusing to discard authored content without --force.');

            return self::FAILURE;
        }

        $existing = HelpCategory::manual($manual)->count();
        DB::transaction(function () use ($manual) {
            HelpCategory::manual($manual)->get()->each->delete();
        });
        $this->info("Removed {$existing} chapter(s) from the {$manual} manual.");

        $seeder = $manual === 'admin' ? new HelpAdminContentSeeder() : new HelpContentSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
