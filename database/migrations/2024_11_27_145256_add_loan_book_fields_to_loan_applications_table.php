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
        Schema::table('loan_applications', function (Blueprint $table) {
            // Loan book specific fields
            $table->string('contract_id')->nullable()->after('id')->index();
            $table->string('external_identity_id')->nullable()->after('client_id')->index();
            $table->string('portfolio_group')->nullable();
            $table->unsignedBigInteger('reporting_date_id')->nullable()->after('date');
            $table->date('due_date')->nullable();
            $table->integer('overdue_days')->nullable();
            $table->decimal('principal_balance', 65, 2)->nullable();
            
            // Add foreign key for date dimension
            $table->foreign('reporting_date_id')
                  ->references('id')
                  ->on('date_dimensions')
                  ->onDelete('set null');

            // Add composite index for efficient monthly reporting
            $table->index(['reporting_date_id', 'status', 'overdue_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropForeign(['reporting_date_id']);
            $table->dropIndex(['reporting_date_id', 'status', 'overdue_days']);
            $table->dropColumn([
                'contract_id',
                'external_identity_id',
                'portfolio_group',
                'reporting_date_id',
                'due_date',
                'overdue_days',
                'principal_balance'
            ]);
        });
    }
};
