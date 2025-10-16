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
            $table->decimal('lgd_value', 8, 2)
                  ->nullable()
                  ->after('calculated_ifrs9_stage')
                  ->comment('Loss Given Default value for the loan book');
            $table->decimal('pd_value', 8, 2)
                  ->nullable()
                  ->after('calculated_ifrs9_stage')
                  ->comment('Probability Of Default for the loan book');
             $table->decimal('ecl_value', 18, 2)
                   ->nullable()
                   ->after('calculated_ifrs9_stage')
                   ->comment('Expected Credit Loss value for the loan book');
            $table->decimal('12m_pd',8,2)
                   ->nullable()
                   ->comment('12 months pd');
            $table->decimal('lifetime_pd',8,2)
                   ->nullable()
                   ->comment('This is the lifetime customer pd');
            $table->decimal('customer_lgd',8,2)
                  ->nullable()
                  ->comment('Customer provided Loss Given Default value for the loan book');
            $table->decimal('collection_lgd',8,2)
                  ->nullable()
                  ->comment('Collection provided Loss Given Default value for the loan book');
            
        
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->dropColumn('lgd_value');
            $table->dropColumn('pd_value');
            $table->dropColumn('ecl_value');
            $table->dropColumn('12m_pd');
            $table->dropColumn('lifetime_pd');
            $table->dropColumn('customer_lgd');
            $table->dropColumn('collection_lgd');
        });
    }
};
