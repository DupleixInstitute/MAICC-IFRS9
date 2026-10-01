<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The borrower's name on the contract master, as the file supplies it.
 *
 * Until now the EIR screens borrowed the name from the most recent loan book
 * row, so a contract loaded before its first loan book, or one that has left
 * the book, showed no name at all. The master file carries CUSTOMER_NAME, and
 * the finance team reads a contract by its name before its account number.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('contract_eir', 'customer_name')) {
            return;
        }

        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('contract_id')
                ->comment('Borrower name as supplied on the contract master');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_eir', 'customer_name')) {
            Schema::table('contract_eir', function (Blueprint $table) {
                $table->dropColumn('customer_name');
            });
        }
    }
};
