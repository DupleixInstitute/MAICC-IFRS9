<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Go-live controls of 14 October 2026 (system audit of 9 October 2026,
 * findings H9, H11, M9, M15, L5 and the C9 residual; spec v4 sections 6.3,
 * 6.9, 7.1 and 7.3):
 *
 *  contract_eir             who prepared the version 1 schedule, so a second
 *                           person approves it (schedule_approval_control,
 *                           O18); the approval label when the bootstrap
 *                           approves it; the over-sanction amount (drawn
 *                           above the sanction, 7.3), flagged for Credit
 *  staging_thresholds       maker-checker: a row is proposed and approved by
 *                           a second person before staging reads it (H11);
 *                           the rows already loaded are the approved defaults
 *  takeon_build_requests    the take-on Build is proposed by one person and
 *                           approved by another (6.9)
 *  schemes                  where each scheme row came from (SM_01, or DD_10
 *                           where SM_01 lacks the scheme or the field)
 *  eir_actual_transactions  a reversal paired to the posting it reverses (7.1)
 *  loan_books.lifetime_pd   decimal(8,2) cut a probability to two places;
 *                           decimal(12,8) like the other PD columns (L5)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_eir')) {
            Schema::table('contract_eir', function (Blueprint $t) {
                if (! Schema::hasColumn('contract_eir', 'schedule_prepared_by')) {
                    $t->unsignedBigInteger('schedule_prepared_by')->nullable()->comment('The person who generated or imported the version 1 schedule; null for a system build');
                }
                if (! Schema::hasColumn('contract_eir', 'schedule_approver_label')) {
                    $t->string('schedule_approver_label', 120)->nullable()->comment('Set when the schedule was approved under the bootstrap label, never a MAIIC approval');
                }
                if (! Schema::hasColumn('contract_eir', 'over_sanction_amount')) {
                    $t->decimal('over_sanction_amount', 20, 2)->nullable()->comment('Drawn above the sanction (spec 7.3): flagged for Credit; null when within it');
                }
            });
        }

        if (Schema::hasTable('staging_thresholds')) {
            Schema::table('staging_thresholds', function (Blueprint $t) {
                if (! Schema::hasColumn('staging_thresholds', 'status')) {
                    // the rows already loaded are the approved defaults; a new row starts PROPOSED
                    $t->string('status', 20)->default('APPROVED');
                    $t->unsignedBigInteger('proposed_by')->nullable();
                    $t->unsignedBigInteger('approved_by')->nullable();
                    $t->timestamp('approved_at')->nullable();
                    $t->string('reason', 500)->nullable();
                }
            });
        }

        if (! Schema::hasTable('takeon_build_requests')) {
            Schema::create('takeon_build_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('load_id')->comment('The landed take-on workbook the build reads');
                $t->string('status', 20)->default('PROPOSED')->comment('PROPOSED, APPROVED, SUPERSEDED');
                $t->unsignedBigInteger('proposed_by')->nullable();
                $t->timestamp('proposed_at')->nullable();
                $t->string('reason', 500)->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->string('approver_label', 120)->nullable()->comment('Set when approved under the bootstrap label, never a MAIIC approval');
                $t->timestamp('approved_at')->nullable();
                $t->text('result')->nullable();
                $t->timestamps();
                $t->index(['load_id', 'status']);
            });
        }

        if (Schema::hasTable('schemes')) {
            Schema::table('schemes', function (Blueprint $t) {
                if (! Schema::hasColumn('schemes', 'scheme_name')) {
                    $t->string('scheme_name', 120)->nullable();
                }
                if (! Schema::hasColumn('schemes', 'source')) {
                    $t->string('source', 60)->nullable()->comment('SM_01, or SM_01+DD_10 where the scheme master lacked a field');
                }
            });
        }

        if (Schema::hasTable('eir_actual_transactions')) {
            Schema::table('eir_actual_transactions', function (Blueprint $t) {
                if (! Schema::hasColumn('eir_actual_transactions', 'reverses_external_id')) {
                    $t->string('reverses_external_id')->nullable()->comment('For a reversal: the posting it reverses (spec 7.1)');
                    $t->string('reversal_status', 20)->nullable()->comment('PAIRED or UNPAIRED for a reversal; REVERSED for a posting a reversal undid');
                }
            });
        }

        if (Schema::hasTable('loan_books') && Schema::hasColumn('loan_books', 'lifetime_pd') && DB::getDriverName() === 'mysql') {
            \App\Support\LegacyZeroDates::alter(fn () => DB::statement("ALTER TABLE loan_books MODIFY lifetime_pd DECIMAL(12,8) NULL COMMENT 'Lifetime PD, a fraction'"));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_books') && Schema::hasColumn('loan_books', 'lifetime_pd') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE loan_books MODIFY lifetime_pd DECIMAL(8,2) NULL COMMENT 'This is the lifetime customer pd'");
        }
        if (Schema::hasTable('eir_actual_transactions') && Schema::hasColumn('eir_actual_transactions', 'reverses_external_id')) {
            Schema::table('eir_actual_transactions', fn (Blueprint $t) => $t->dropColumn(['reverses_external_id', 'reversal_status']));
        }
        if (Schema::hasTable('schemes') && Schema::hasColumn('schemes', 'source')) {
            Schema::table('schemes', fn (Blueprint $t) => $t->dropColumn(['scheme_name', 'source']));
        }
        Schema::dropIfExists('takeon_build_requests');
        if (Schema::hasTable('staging_thresholds') && Schema::hasColumn('staging_thresholds', 'status')) {
            Schema::table('staging_thresholds', fn (Blueprint $t) => $t->dropColumn(['status', 'proposed_by', 'approved_by', 'approved_at', 'reason']));
        }
        if (Schema::hasTable('contract_eir') && Schema::hasColumn('contract_eir', 'schedule_prepared_by')) {
            Schema::table('contract_eir', fn (Blueprint $t) => $t->dropColumn(['schedule_prepared_by', 'schedule_approver_label', 'over_sanction_amount']));
        }
    }
};
