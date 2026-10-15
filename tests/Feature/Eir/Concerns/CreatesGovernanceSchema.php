<?php

namespace Tests\Feature\Eir\Concerns;

use App\Services\Eir\StagingPolicy;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

    /**
     * The columns of loan_books the contractual-interest calculation reads: the
     * outstanding balance the month before, the rate for the month, the
     * cumulative amount disbursed and the customer name. The EIR tests build
     * their own private schema rather than migrating, so the table has to be
     * declared where they can share it.
     */
    protected function createLoanBookSchema(): void
    {
        if (Schema::hasTable('loan_books')) {
            return;
        }

        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('customer_name')->nullable();
            $t->string('reporting_period')->nullable();
            $t->double('interest_rate')->default(0);
            $t->double('carrying_amount')->default(0);
            $t->double('principal_balance')->default(0);
            $t->double('disbursed')->default(0);
            $t->string('create_date')->nullable();
            $t->timestamps();
        });
    }

    /** Dupleix's recommended defaults, APPROVED and effective 2025-01-01. */
    protected function seedGovernanceDefaults(): void
    {
        (new GovernanceSettingsSeeder())->run();
    }

    /**
     * Stage under the directive-aligned policy instead of the seeded
     * single-ladder default (Staging Module Specification v1, section 3), for a
     * test of the directive's mechanics. With overrides, the Custom preset with
     * every staging setting at its directive-aligned value and the overrides on
     * top, e.g. ['stage_cure_months' => '0 months (no probation)'].
     *
     * @param array<string,string> $overrides
     */
    /**
     * A supporting document on a proposal, for a setting whose approval needs
     * one (Staging Module Specification v1, section 8.3).
     */
    protected function attachEvidence(int $settingId, string $name = 'Board minute.pdf'): void
    {
        if (! Schema::hasTable('supporting_documents')) {
            Schema::create('supporting_documents', function (Blueprint $t) {
                $t->increments('id'); $t->string('documentable_type'); $t->unsignedBigInteger('documentable_id'); $t->string('disk')->default('local'); $t->string('path');
                $t->string('original_name'); $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size')->default(0); $t->string('extension')->nullable();
                $t->string('document_type')->nullable(); $t->string('description')->nullable(); $t->string('hash')->nullable(); $t->unsignedBigInteger('uploaded_by')->nullable(); $t->timestamps(); $t->softDeletes();
            });
        }
        DB::table('supporting_documents')->insert(['documentable_type' => \App\Models\GovernanceSetting::class, 'documentable_id' => $settingId, 'disk' => 'local', 'path' => 'governance-documents/' . $settingId . '/x.pdf',
            'original_name' => $name, 'document_type' => 'minute', 'hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function stageDirectiveAligned(array $overrides = []): void
    {
        $preset = $overrides === [] ? StagingPolicy::PRESET_DIRECTIVE : StagingPolicy::PRESET_CUSTOM;
        DB::table('governance_settings')->where('key', StagingPolicy::PRESET)->update(['value' => $preset]);
        if ($overrides !== []) {
            foreach ($overrides + StagingPolicy::PRESETS[StagingPolicy::PRESET_DIRECTIVE] as $key => $value) {
                DB::table('governance_settings')->where('key', $key)->update(['value' => $value]);
            }
        }
    }
}
