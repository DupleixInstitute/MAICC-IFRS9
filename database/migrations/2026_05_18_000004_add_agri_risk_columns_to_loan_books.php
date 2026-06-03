<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agri credit-risk reality for a DFI lending to smallholders:
 *   credit_enhancement — how the loan is actually secured (off-take /
 *     warehouse receipt / group guarantee / AIP / asset / unsecured), since
 *     input loans are rarely backed by real estate.
 *   cooperative — the cooperative / anchor buyer the borrower is tied to, so
 *     correlated (contagion) default can be measured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_books', 'credit_enhancement')) {
                $table->string('credit_enhancement', 40)->nullable()->after('collateral_id');
            }
            if (! Schema::hasColumn('loan_books', 'cooperative')) {
                $table->string('cooperative', 80)->nullable()->after('credit_enhancement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            foreach (['credit_enhancement', 'cooperative'] as $c) {
                if (Schema::hasColumn('loan_books', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
