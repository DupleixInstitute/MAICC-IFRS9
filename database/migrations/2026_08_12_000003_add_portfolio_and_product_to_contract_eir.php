<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portfolio (MAIIC / FInES) and product type on the EIR contract profile.
 *
 * The general rule on this table is to reach master data by join rather than
 * copy it, and loan_books does carry the same taxonomy — funding_source holds
 * MAIIC/FInES and product_group holds the product names. Two things make this
 * the exception:
 *
 *  - the join only reaches facilities on the current tape. Of the 181
 *    facilities in the delivered Extract A, 74 are absent from loan_books, 46
 *    of them closed — and closed facilities are precisely the ones the
 *    comparative period needs to report on;
 *  - portfolio is fixed at origination. A loan does not move between the
 *    MAIIC and FInES funding lines, so there is no as-of problem: the value
 *    cannot go stale the way a stage or a balance does.
 *
 * Portfolio drives the Note 22–24 concentration disclosure, the per-portfolio
 * roll-up of the GL reconciliation, and the below-market question on the FInES
 * concessionary line.
 *
 * product_type is added at the same time to close a latent gap: the reader
 * already mapped that column and offered it on the mapping screen, but nothing
 * persisted it, so an operator's mapping was silently discarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('portfolio', 60)->nullable()->after('contract_id')->index();
            $table->string('product_type', 120)->nullable()->after('portfolio');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropIndex(['portfolio']);
            $table->dropColumn(['portfolio', 'product_type']);
        });
    }
};
