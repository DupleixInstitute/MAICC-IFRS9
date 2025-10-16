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
            $table->unsignedBigInteger('client_id')->nullable()->after('id');
            $table->unsignedBigInteger('loan_portfolio_id')->nullable()->after('id');
            $table->string('contract_status')->nullable();
            $table->string('reporting_period')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->dropColumn([
                'loan_portfolio_id',
                'client_id',
                'contract_status'
            ]);
        });
    }
};
