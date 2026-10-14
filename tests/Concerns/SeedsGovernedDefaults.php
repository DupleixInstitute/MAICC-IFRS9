<?php

namespace Tests\Concerns;

use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For a test that builds its own schema: the governance_settings table with
 * the catalogue's seeded defaults approved from 1900-01-01, as
 * GovernanceSettingsSeeder writes them on a clean install (D21). The engines
 * read their settings with no value in code, so a fixture that leaves the
 * table out exercises a database the system never runs on.
 */
trait SeedsGovernedDefaults
{
    /** @param list<string>|null $keys the settings to seed; null for the whole catalogue */
    protected function seedGovernedDefaults(?array $keys = null): void
    {
        if (! Schema::hasTable('governance_settings')) {
            Schema::create('governance_settings', function (Blueprint $t) {
                $t->increments('id'); $t->string('key', 60); $t->string('value'); $t->text('options'); $t->string('label'); $t->text('description');
                $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable();
                $t->string('reason', 500)->nullable(); $t->string('status', 20)->default('PROPOSED'); $t->timestamps();
            });
        }
        foreach (GovernanceService::catalogue() as $key => $def) {
            if (($keys !== null && ! in_array($key, $keys, true)) || DB::table('governance_settings')->where('key', $key)->where('effective_from', '1900-01-01')->exists()) {
                continue;
            }
            DB::table('governance_settings')->insert(['key' => $key, 'value' => $def['default'], 'options' => json_encode($def['options']), 'label' => $def['label'],
                'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
