<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The balance E-Banker shows after each scheduled instalment.
 *
 * E-Banker's EMI chart and Extract B both print it. Without it the schedule
 * screen can only work a balance backwards from the principal, which is wrong
 * wherever interest is added to the balance between instalments: on JAT
 * Group's quarterly loan the principal repaid comes to 287 million against
 * 209 million lent, the difference being interest capitalised in the months
 * with no instalment. The printed balance is the evidence; nothing derives it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contract_remaining_cashflow_schedule', 'closing_balance')) {
            return;
        }

        Schema::table('contract_remaining_cashflow_schedule', function (Blueprint $table) {
            $table->decimal('closing_balance', 20, 2)->nullable()->after('fee_due')
                ->comment('Balance after the instalment, as the source schedule prints it');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_remaining_cashflow_schedule', 'closing_balance')) {
            Schema::table('contract_remaining_cashflow_schedule', function (Blueprint $table) {
                $table->dropColumn('closing_balance');
            });
        }
    }
};
