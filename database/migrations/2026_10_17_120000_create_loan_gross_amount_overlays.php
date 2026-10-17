<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance's own position on a loan's gross amount, recorded beside the system's own
 * rebuilt carrying amount, never replacing it (MAIIC Finance Overlay Importers
 * Specification v1, section 4.2): one row per contract id and reporting period, the
 * system's own figure always kept so a comment can never be read without it. This table
 * does not feed any calculation — no build, ECL or RBM read touches it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_gross_amount_overlays')) {
            Schema::create('loan_gross_amount_overlays', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 30);
                $t->string('reporting_period', 7);
                $t->decimal('system_amount', 20, 2)->nullable();
                $t->decimal('reference_amount', 20, 2)->nullable();
                $t->decimal('finance_amount', 20, 2)->nullable();
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
        Schema::dropIfExists('loan_gross_amount_overlays');
    }
};
