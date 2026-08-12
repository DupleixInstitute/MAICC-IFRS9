<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the accrual conventions and drawdown history the source states.
 *
 * The `source_` prefix is deliberate and load-bearing. These columns hold what
 * E-Banker says about a facility, which is not necessarily what the engine is
 * directed to apply: the conventions memo may approve a single day count for
 * the whole book, and the delivered Extract A shows the book is not on one —
 * 336 facilities on 365 and 26 on 360. Keeping the stated value separate from
 * the applied one is what lets that override be seen and explained rather than
 * silently absorbed, and leaves the unprefixed names free for the approved
 * convention when the memo lands.
 *
 * `disbursement_tranches` is the one that changes numbers rather than
 * disclosure. The solver anchors the cash-flow vector on a single drawdown at
 * origination_date. A facility drawn in stages has several negative flows at
 * different dates, so its true EIR differs from the one a single-drawdown
 * model produces. The raw history is captured now so the facilities affected
 * can be identified; consuming it is a change to the solver input, not to this
 * import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('source_day_count_basis', 20)->nullable()->after('rate_basis');
            $table->string('source_compounding', 20)->nullable()->after('source_day_count_basis');
            $table->text('disbursement_tranches')->nullable()->after('drawn_amount');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropColumn(['source_day_count_basis', 'source_compounding', 'disbursement_tranches']);
        });
    }
};
