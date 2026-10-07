<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 19 — harden scenario governance.
 *
 * Blueprint §4.2 row 19 acceptance criterion:
 *
 *   "Every scenario has type, owner, calibration source, plausibility
 *    note, approval trail, and use-test evidence."
 *
 * Scenarios previously carried a free-text `scenario_type` and a bare
 * approver stamp — there was no governance role (base / adverse /
 * severe / challenger), no accountable owner, no record of how the
 * shocks were calibrated, no plausibility narrative and no use-test
 * evidence. A scenario could be approved with none of this.
 *
 * The calibration columns are pushed down to the shock and parameter
 * rows too, so each magnitude carries its own evidentiary basis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table): void {
            // Governance role — distinct from the descriptive scenario_type.
            $table->string('scenario_role', 20)->nullable()->after('scenario_type'); // base | adverse | severe | challenger
            $table->unsignedBigInteger('owner_id')->nullable()->after('created_by');
            $table->string('calibration_source', 250)->nullable()->after('narrative');
            $table->string('calibration_method', 40)->nullable()->after('calibration_source'); // historical | hypothetical | regulatory_prescribed | expert_judgement
            $table->text('plausibility_note')->nullable()->after('calibration_method');
            $table->text('use_test_evidence')->nullable()->after('plausibility_note');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('use_test_evidence');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');

            $table->foreign('owner_id', 'scenarios_owner_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by', 'scenarios_submitted_fk')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('scenario_shocks', function (Blueprint $table): void {
            $table->string('calibration_source', 250)->nullable()->after('notes');
            $table->string('calibration_basis', 40)->nullable()->after('calibration_source'); // historical | hypothetical | regulatory | expert_judgement
        });

        Schema::table('scenario_parameters', function (Blueprint $table): void {
            $table->string('calibration_source', 250)->nullable()->after('category');
            $table->string('evidence_reference', 500)->nullable()->after('calibration_source');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_parameters', function (Blueprint $table): void {
            $table->dropColumn(['calibration_source', 'evidence_reference']);
        });

        Schema::table('scenario_shocks', function (Blueprint $table): void {
            $table->dropColumn(['calibration_source', 'calibration_basis']);
        });

        Schema::table('scenarios', function (Blueprint $table): void {
            $table->dropForeign('scenarios_owner_fk');
            $table->dropForeign('scenarios_submitted_fk');
            $table->dropColumn([
                'scenario_role', 'owner_id', 'calibration_source', 'calibration_method',
                'plausibility_note', 'use_test_evidence', 'submitted_by', 'submitted_at',
            ]);
        });
    }
};
