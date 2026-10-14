<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ResetPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset permissions table';

    /**
     * The permission seeders, in the order eir:bootstrap runs them
     * (Bootstrap::stepSeed): the base list, the MAIIC and ticket permissions
     * that list does not carry, then the cleanup that removes the
     * credit-scoring-era permissions the base list still creates
     * (documentation audit, 14 October 2026: this command had re-created
     * them and dropped every eir.* permission).
     */
    public const SEEDERS = ['PermissionsTableSeeder', 'MaiicAdminPermissionsSeeder', 'TicketsPermissionsSeeder', 'LegacyPermissionsCleanupSeeder'];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Schema::disableForeignKeyConstraints();
        $this->info("truncating permissions table");
        DB::table('permissions')->truncate();
        $this->info("seeding permissions");
        foreach (self::SEEDERS as $seeder) {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\' . $seeder, '--force' => true]);
        }
        $this->info("assigning permissions to admin");
        Role::findByName('admin')->syncPermissions(Permission::all());
        Schema::enableForeignKeyConstraints();
        return 0;
    }
}
