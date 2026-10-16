<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-performance (Staging Module Specification v1, section 8.8, item 6;
 * acceptance 19).
 *
 *   run_record_loans      each loan's figures as its run left them: the stage,
 *                         PD, LGD, EAD, ECL (and discounted), EIR interest and
 *                         closing gross carrying amount of the month; what a
 *                         re-performance is checked against, loan by loan
 *   reperformances        one per re-performance of a run: in place (from the
 *                         frozen snapshot, nothing written) or in a workspace
 *                         database (the engines re-run on a copy); who, when,
 *                         and what it found
 *   reperformance_rows    every difference it names: an input of the month
 *                         that no longer matches the snapshot, a loan whose
 *                         re-performed stage or figure differs
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('run_record_loans')) {
            Schema::create('run_record_loans', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('run_record_id')->index();
                $t->string('contract_id', 40);
                $t->unsignedTinyInteger('stage')->nullable();
                $t->decimal('pd', 18, 12)->nullable();
                $t->decimal('lgd', 18, 12)->nullable();
                $t->decimal('ead', 22, 2)->nullable();
                $t->decimal('ecl', 22, 2)->nullable();
                $t->decimal('ecl_discounted', 22, 2)->nullable();
                $t->decimal('eir_interest', 22, 2)->nullable();
                $t->decimal('closing_gross', 22, 2)->nullable();
                $t->unique(['run_record_id', 'contract_id']);
            });
        }
        if (! Schema::hasTable('reperformances')) {
            Schema::create('reperformances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('run_record_id')->index();
                $t->string('reporting_period', 7)->index();
                $t->string('mode', 20);                 // IN_PLACE or WORKSPACE
                $t->string('workspace', 64)->nullable();
                $t->string('status', 20);               // RUNNING, REPRODUCED, DIFFERENCES, FAILED
                $t->unsignedBigInteger('started_by')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->json('summary')->nullable();
                $t->text('error')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('reperformance_rows')) {
            Schema::create('reperformance_rows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('reperformance_id')->index();
                $t->string('check', 20);                // input, stage, figure
                $t->string('contract_id', 40)->nullable();
                $t->string('item', 255);
                $t->text('stored')->nullable();
                $t->text('reperformed')->nullable();
                $t->string('note', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reperformance_rows');
        Schema::dropIfExists('reperformances');
        Schema::dropIfExists('run_record_loans');
    }
};
