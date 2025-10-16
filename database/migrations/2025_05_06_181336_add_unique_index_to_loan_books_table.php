<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->unique(
                ['client_id', 'loan_portfolio_id', 'reporting_period', 'contract_id', 'external_identity_id'],
                'loan_books_unique_upsert_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->dropUnique('loan_books_unique_upsert_index');
        });
    }
};
