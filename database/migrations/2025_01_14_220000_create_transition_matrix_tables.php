<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create transition_matrices table if it doesn't exist
        if (!Schema::hasTable('transition_matrices')) {
            Schema::create('transition_matrices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transition_profile_id')->constrained('loan_products');
                $table->date('start_reporting_period');
                $table->date('end_reporting_period');
                $table->text('description')->nullable();
                $table->string('external_file_path')->nullable();
                $table->string('status')->default('draft'); // draft, active, archived
                $table->timestamps();
                $table->softDeletes();

                // Add index for faster queries
                $table->index(['start_reporting_period', 'end_reporting_period'],'start_end_reporting_period_index');
                $table->index('status');
            });
        }

        // Create transition_matrix_entries table if it doesn't exist
        if (!Schema::hasTable('transition_matrix_entries')) {
            Schema::create('transition_matrix_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transition_matrix_id')->constrained()->onDelete('cascade');
                $table->string('portfolio_group');
                $table->string('start_state');
                $table->string('end_state');
                $table->decimal('start_balance', 20, 2)->default(0);
                $table->integer('start_count')->default(0);
                $table->decimal('end_balance', 20, 2)->default(0);
                $table->integer('end_count')->default(0);
                $table->decimal('transitional_probability', 10, 6)->default(0);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();

                // Add indexes for faster queries and ordering
                $table->index(['start_state', 'end_state']);
                $table->index('portfolio_group');
                $table->index('is_default');
            });
        }

        // Add is_transition_profile column to loan_products if it doesn't exist
        if (!Schema::hasColumn('loan_products', 'is_transition_profile')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->boolean('is_transition_profile')->default(false);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('transition_matrix_entries');
        Schema::dropIfExists('transition_matrices');

        if (Schema::hasColumn('loan_products', 'is_transition_profile')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->dropColumn('is_transition_profile');
            });
        }
    }
};
