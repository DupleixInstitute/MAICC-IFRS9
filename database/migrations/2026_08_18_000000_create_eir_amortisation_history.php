<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of superseded amortisation rows.
 *
 * `eir_amortisation` holds one live row per contract per period and is keyed
 * uniquely on that pair, so a recalculation cannot keep both versions in
 * place. A month-end figure that has already been reported can still be
 * restated — a corrected stage, a revised allowance, a late actuals delivery —
 * and an auditor's question is always "what did this say before, and who
 * changed it". The replaced row is copied here before it is removed, following
 * the same append-only decision-history pattern as
 * `eir_fee_classification_events`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eir_amortisation_history', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id', 199);
            $table->string('reporting_period', 7);

            // The superseded measurement, copied verbatim.
            $table->decimal('opening_gross', 20, 2);
            $table->decimal('interest_accrued', 20, 2);
            $table->enum('interest_basis', ['GROSS', 'NET']);
            $table->decimal('unwind_amount', 20, 2);
            $table->decimal('cash_received', 20, 2);
            $table->enum('cash_source', ['DERIVED', 'IMPORTED']);
            $table->decimal('modification_gain_loss', 20, 2);
            $table->decimal('closing_gross', 20, 2);
            $table->decimal('ecl_allowance', 20, 2);

            // When the superseded row was originally produced, so the history
            // records the life of the figure and not just its removal.
            $table->timestamp('originally_created_at')->nullable();

            $table->timestamp('superseded_at')->useCurrent();
            $table->foreignId('superseded_by')->nullable()->constrained('users');
            $table->string('supersession_reason', 500);

            $table->timestamps();
            $table->index(['contract_id', 'reporting_period']);
            $table->index('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eir_amortisation_history');
    }
};
