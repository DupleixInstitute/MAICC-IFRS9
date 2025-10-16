<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            // Drop the old FK
            $table->dropForeign(['transition_profile_id']);
        });

        Schema::table('transition_matrices', function (Blueprint $table) {
            // Add the correct FK pointing to transition_profile_definitions
            $table->foreign('transition_profile_id')
                  ->references('id')
                  ->on('transition_profile_definitions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('transition_matrices', function (Blueprint $table) {
            // Revert back to old relationship if needed (optional)
            $table->dropForeign(['transition_profile_id']);

            $table->foreign('transition_profile_id')
                  ->references('id')
                  ->on('loan_products') // Old table
                  ->onDelete('cascade');
        });
    }
};

