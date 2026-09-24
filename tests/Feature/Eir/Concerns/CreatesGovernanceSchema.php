<?php

namespace Tests\Feature\Eir\Concerns;

use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The governance tables on the private in-memory schema the EIR tests use,
 * and the seeded defaults every engine service now reads instead of a
 * number written in code. No migrations, no developer database.
 */
trait CreatesGovernanceSchema
{
    protected function createGovernanceSchema(): void
    {
        // Every proposal and approval is audit-logged, so the log table comes with the schema.
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $t) {
                $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type');
                $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable();
                $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable();
                $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->text('user_agent')->nullable(); $t->timestamps();
            });
        }

        Schema::create('governance_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('key', 60);
            $t->string('value', 60);
            $t->text('options');
            $t->string('label');
            $t->text('description');
            $t->string('effective_from');
            $t->integer('set_by')->nullable();
            $t->integer('approved_by')->nullable();
            $t->string('approved_at')->nullable();
            $t->string('reason', 500)->nullable();
            $t->string('status', 20)->default('PROPOSED');
            $t->timestamps();
            $t->unique(['key', 'effective_from']);
        });

        Schema::create('governance_setting_history', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('setting_id');
            $t->string('key', 60);
            $t->string('value', 60);
            $t->text('options');
            $t->string('label');
            $t->text('description');
            $t->string('effective_from');
            $t->integer('set_by')->nullable();
            $t->integer('approved_by')->nullable();
            $t->string('approved_at')->nullable();
            $t->string('reason', 500)->nullable();
            $t->string('status', 20);
            $t->string('superseded_at');
            $t->integer('superseded_by')->nullable();
            $t->integer('superseded_by_setting_id')->nullable();
            $t->timestamps();
        });
    }

    /** Dupleix's recommended defaults, APPROVED and effective 2025-01-01. */
    protected function seedGovernanceDefaults(): void
    {
        (new GovernanceSettingsSeeder())->run();
    }
}
