<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loan_products', function (Blueprint $table) {
            // Drop old columns that are no longer needed
            $table->dropColumn(['loan_product_category_id', 'score', 'description', 'active']);
            
            // Add new columns for transition profile
            $table->string('aggregation_criteria')->default('balance'); // 'count' or 'balance'
           // $table->foreignId('end_transition_profile_id')->nullable()->references('id')->on('loan_products');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_per_portfolio')->default(true);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_lgd')->default(true);
        });
    }

    public function down()
    {
        Schema::table('loan_products', function (Blueprint $table) {
            // Restore old columns
            $table->foreignId('loan_product_category_id')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);

            // Remove new columns
            $table->dropColumn([
                'aggregation_criteria',
               // 'end_transition_profile_id',
                'is_default',
                'is_per_portfolio',
                'is_paid',
                'is_lgd'
            ]);
        });
    }
};
