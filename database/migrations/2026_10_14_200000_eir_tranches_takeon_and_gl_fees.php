<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The three EIR gaps of the results review of 13 October 2026 (E1 to E3)
|--------------------------------------------------------------------------
| contract_disbursements  capitalised_fees: a type-301 fee debit is the fee
|                         capitalised into the loan, not a tranche of its own
|                         (spec v4 section 7.8.4); it is added to the drawdown
|                         of its date and the part that is fee is recorded.
| contract_fees           superseded_by_source / superseded_at: one fee, one
|                         source (fee_source_precedence, spec 7.8.2). A line
|                         from a lower source is kept and shown, never enters
|                         the EIR and is not counted twice by the fee GL check.
| fee_gl_unassigned       a GL_04 fee leg the batch cannot place on one loan
|                         (no loan account in the batch, or several that its
|                         own fee debits cannot split): held with the reason,
|                         never dropped and never guessed (spec 7.8.4).
| contract_takeon         the take-on solve check (E2): the date the workbook
|                         schedule starts, the EIR the schedule gives with no
|                         fee, whether the solve passed the governed check and
|                         why not.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_disbursements') && ! Schema::hasColumn('contract_disbursements', 'capitalised_fees')) {
            Schema::table('contract_disbursements', function (Blueprint $table) {
                $table->decimal('capitalised_fees', 20, 2)->nullable()->after('amount')
                    ->comment('Fee debits on the loan account in the same drawdown, included in amount (spec 7.8.4)');
            });
        }

        if (Schema::hasTable('contract_fees') && ! Schema::hasColumn('contract_fees', 'superseded_by_source')) {
            Schema::table('contract_fees', function (Blueprint $table) {
                $table->string('superseded_by_source', 50)->nullable()->after('gl_account_ref')
                    ->comment('The higher source in fee_source_precedence that holds this contract\'s fees; the line stays out of the EIR');
                $table->timestamp('superseded_at')->nullable()->after('superseded_by_source');
            });
        }

        if (! Schema::hasTable('fee_gl_unassigned')) {
            Schema::create('fee_gl_unassigned', function (Blueprint $table) {
                $table->id();
                $table->string('external_transaction_id', 60)->unique()->comment('GL_04a:CUMVOUCH_DET_ID');
                $table->date('transaction_date')->nullable();
                $table->string('batch', 60)->comment('Transaction date, batch type and batch number');
                $table->string('gl_code', 20);
                $table->decimal('amount', 20, 2)->comment('Positive a credit to fee income, negative a reversal');
                $table->string('narration')->nullable();
                $table->string('reason', 500);
                $table->json('loans_in_batch')->nullable();
                $table->string('candidate_contract_id', 60)->nullable()
                    ->comment('A loan the evidence points to (its account name in the narration and a drawdown that day); a proposal for a person, never an assignment');
                $table->string('candidate_evidence', 255)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contract_takeon') && ! Schema::hasColumn('contract_takeon', 'eir_check')) {
            Schema::table('contract_takeon', function (Blueprint $table) {
                $table->date('solve_start_date')->nullable()->after('origination_date')
                    ->comment('The date the workbook schedule starts charging interest: the date of the first outflow');
                $table->decimal('nofee_eir', 12, 8)->nullable()->after('recomputed_eir')
                    ->comment('Effective annual rate of the workbook schedule with no fee: the rate the schedule itself carries');
                $table->string('eir_check', 20)->nullable()->after('nofee_eir')
                    ->comment('PASS | REFUSED: the governed take-on solve check (takeon_eir_plausibility)');
                $table->text('eir_check_reason')->nullable()->after('eir_check');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_takeon', 'eir_check')) {
            Schema::table('contract_takeon', fn (Blueprint $t) => $t->dropColumn(['solve_start_date', 'nofee_eir', 'eir_check', 'eir_check_reason']));
        }
        Schema::dropIfExists('fee_gl_unassigned');
        if (Schema::hasColumn('contract_fees', 'superseded_by_source')) {
            Schema::table('contract_fees', fn (Blueprint $t) => $t->dropColumn(['superseded_by_source', 'superseded_at']));
        }
        if (Schema::hasColumn('contract_disbursements', 'capitalised_fees')) {
            Schema::table('contract_disbursements', fn (Blueprint $t) => $t->dropColumn('capitalised_fees'));
        }
    }
};
