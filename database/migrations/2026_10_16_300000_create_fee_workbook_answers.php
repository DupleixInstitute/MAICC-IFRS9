<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Finance's fee reconciliation workbook, round trip (16 October 2026; spec v4 section 7.8.9).
|
| fee_workbook_uploads        one workbook Finance answered, uploaded on Fee Classification: the file is
|                             its supporting document (approval workflow 'fee-workbook'); OPEN while any
|                             answer waits for a decision. basis KEYED: produced by the download, every row
|                             mapped by its hidden key and the fingerprint checked; NATURAL_KEYS: an offline
|                             workbook with no keys, mapped by the columns that name each record.
| fee_workbook_answers        one answer per row: the record it maps to, the answer and the fields that go
|                             with it, PROPOSED by the person who uploaded it and APPROVED, REJECTED or
|                             NOT_ACTIONABLE by a different person; outcome says what the approval did.
| fee_unposted_confirmations  Finance's approved answer on sheet 4b per LOS charge never posted: whether it was
|                             charged, and how. Read by the setting los_unposted_fee_basis (UnpostedFeeBasis).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_workbook_uploads')) {
            Schema::create('fee_workbook_uploads', function (Blueprint $table) {
                $table->id();
                $table->string('original_name', 255);
                $table->string('basis', 20)->comment('KEYED (produced by the download) or NATURAL_KEYS (an offline workbook)');
                $table->string('sha256', 64);
                $table->string('fingerprint_hash', 64)->nullable()->comment('The hash of the keys the download recorded');
                $table->timestamp('generated_at')->nullable();
                $table->string('source_database', 100)->nullable();
                $table->string('status', 20)->default('OPEN')->comment('OPEN while an answer waits; DECIDED, REJECTED or WITHDRAWN');
                $table->unsignedInteger('answer_count')->default(0);
                $table->json('refused')->nullable()->comment('Rows that mapped to no record or to more than one, each named');
                $table->unsignedBigInteger('uploaded_by');
                $table->timestamp('uploaded_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('close_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fee_workbook_answers')) {
            Schema::create('fee_workbook_answers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('upload_id')->index();
                $table->string('sheet', 40);
                $table->unsignedInteger('row_number');
                $table->string('record_type', 30)->comment('loan, account, held, overlap, difference, unposted, classify, takeon, move, fee, missing_fee, confirmation');
                $table->string('record_key', 160)->comment('The stable key of the record, as in the hidden key column');
                $table->string('contract_id', 40)->nullable();
                $table->string('answer', 120)->nullable();
                $table->string('loan_account_given', 60)->nullable();
                $table->decimal('correct_amount', 20, 2)->nullable();
                $table->string('detail', 500)->nullable()->comment('What the charge is, or where it was posted');
                $table->text('comment')->nullable();
                $table->json('context')->nullable()->comment('The row as Finance saw it');
                $table->unsignedBigInteger('proposed_by');
                $table->string('status', 20)->default('PROPOSED');
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_reason')->nullable();
                $table->text('outcome')->nullable()->comment('What the approval did, through which service');
                $table->string('follow_up', 500)->nullable()->comment('A change the system does not make by itself, left visible');
                $table->unsignedBigInteger('lock_review_id')->nullable();
                $table->timestamps();
                $table->index(['sheet', 'status']);
            });
        }

        if (! Schema::hasTable('fee_unposted_confirmations')) {
            Schema::create('fee_unposted_confirmations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_fee_id')->unique();
                $table->string('contract_id', 40);
                $table->boolean('charged')->nullable()->comment('true charged, false never charged, null Finance does not know');
                $table->string('answer', 120);
                $table->string('how', 500)->nullable()->comment('Where it was posted or received (GL, date)');
                $table->text('comment')->nullable();
                $table->unsignedBigInteger('workbook_answer_id')->nullable();
                $table->unsignedBigInteger('proposed_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // an installed database takes the new setting at the basis it was built on, so nothing moves on deploy;
        // a fresh install gets it from GovernanceSettingsSeeder with every other default
        if (Schema::hasTable('governance_settings') && DB::table('governance_settings')->exists()
            && ! DB::table('governance_settings')->where('key', \App\Services\Eir\UnpostedFeeBasis::KEY)->exists()) {
            $definition = \App\Services\Eir\GovernanceService::catalogue()[\App\Services\Eir\UnpostedFeeBasis::KEY];
            $id = DB::table('governance_settings')->insertGetId([
                'key' => \App\Services\Eir\UnpostedFeeBasis::KEY, 'value' => $definition['default'], 'options' => json_encode($definition['options']),
                'label' => $definition['label'], 'description' => $definition['description'], 'effective_from' => \Database\Seeders\GovernanceSettingsSeeder::EFFECTIVE_FROM,
                'set_by' => null, 'approved_by' => null, 'approved_at' => now(), 'reason' => $definition['seed_reason'], 'status' => 'APPROVED',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (Schema::hasTable('audit_logs')) {
                \App\Services\AuditLoggerService::log('EIR Governance Setting Seeded', \App\Models\GovernanceSetting::class, $id, [
                    'new_values' => ['key' => \App\Services\Eir\UnpostedFeeBasis::KEY, 'value' => $definition['default'], 'effective_from' => \Database\Seeders\GovernanceSettingsSeeder::EFFECTIVE_FROM, 'reason' => $definition['seed_reason']],
                    'meta' => ['seeded_by' => 'migration 2026_10_16_300000', 'environment' => app()->environment()],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_unposted_confirmations');
        Schema::dropIfExists('fee_workbook_answers');
        Schema::dropIfExists('fee_workbook_uploads');
    }
};
