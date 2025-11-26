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
        if (Schema::hasTable('loan_book')) {
            Schema::table('loan_book', function (Blueprint $table) {
                if (!Schema::hasColumn('loan_book', 'fli_adj')) {
                    // Add after pd_value if it exists, otherwise just add
                    if (Schema::hasColumn('loan_book', 'pd_value')) {
                        $table->decimal('fli_adj', 10, 6)->nullable()->after('pd_value');
                    } else {
                        $table->decimal('fli_adj', 10, 6)->nullable();
                    }
                }
                if (!Schema::hasColumn('loan_book', 'pd_post_fli_adj')) {
                    $table->decimal('pd_post_fli_adj', 10, 6)->nullable()->after('fli_adj');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_book', function (Blueprint $table) {
            $table->dropColumn(['fli_adj', 'pd_post_fli_adj']);
        });
    }
};
