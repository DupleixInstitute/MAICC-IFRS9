<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance's own position on a loan's ageing, recorded beside the system's figure, never
 * replacing it (December 2025 Loan Book Investigation Findings v1, section 10): the system's
 * own days and bracket at the time of export are kept on every row, so a comment can never
 * obscure what the build actually produced. This table does not feed any calculation — no
 * staging, ECL or RBM read touches it — it is a recorded annotation only, pending the
 * governance decision on whether the staging policy should ever prefer a schedule-corrected
 * count over the raw report bucket (LoanAgeingOverlayService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_ageing_overlays')) {
            Schema::create('loan_ageing_overlays', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 30);
                $t->string('reporting_period', 7);
                $t->integer('system_days')->nullable();
                $t->string('system_bracket', 20)->nullable();
                $t->string('workbook_bracket', 20)->nullable();
                $t->integer('finance_days')->nullable();
                $t->string('finance_bracket', 20)->nullable();
                $t->text('comment')->nullable();
                $t->string('source_file', 255)->nullable();
                $t->unsignedBigInteger('imported_by')->nullable();
                $t->timestamps();
                $t->unique(['contract_id', 'reporting_period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_ageing_overlays');
    }
};
