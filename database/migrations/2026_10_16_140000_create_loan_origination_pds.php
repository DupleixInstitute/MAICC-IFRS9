<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each loan's starting risk (PD at Origination Specification v1, section 4):
 * one record per loan, written the first time the loan has a PD and never
 * changed afterwards, with a label saying how good a starting reading it is.
 *
 *   basis   AT_ORIGINATION       the PD was taken in the month the loan was made
 *           AFTER_ORIGINATION    made after the PD history began; first PD some months later
 *           BEFORE_HISTORY       made before the PD history began: the earliest PD held
 *           UNAVAILABLE          no PD in any month (replaced by a real reading if one comes)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_origination_pds')) {
            return;
        }
        Schema::create('loan_origination_pds', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id', 40)->unique();
            $t->date('origination_date')->nullable();
            $t->string('reporting_period', 7)->nullable();        // the month the PD was taken
            $t->unsignedInteger('months_after_origination')->nullable();
            $t->string('basis', 24)->index();
            $t->decimal('pd_12m_post_fli', 16, 8)->nullable();
            $t->decimal('pd_12m_pre_fli', 16, 8)->nullable();
            $t->decimal('pd_lifetime', 16, 8)->nullable();
            $t->unsignedTinyInteger('stage')->nullable();
            $t->string('segment_key', 80)->nullable();
            $t->unsignedBigInteger('loan_portfolio_id')->nullable();
            $t->string('pd_segment_run_id', 36)->nullable();
            $t->unsignedBigInteger('run_record_id')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_origination_pds');
    }
};
