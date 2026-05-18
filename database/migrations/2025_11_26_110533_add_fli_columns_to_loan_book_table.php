<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the FLI columns the ECL engine consumes.
 *
 * The table is "loan_books" (plural) — this migration originally targeted
 * "loan_book" (singular) so it was a permanent no-op. It also added
 * "pd_post_fli_adj", but the ECL calculation reads "pd_post_fli", so that
 * is the column the FLI pipeline must write.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_books')) {
            return;
        }

        Schema::table('loan_books', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_books', 'fli_adj')) {
                if (Schema::hasColumn('loan_books', 'pd_value')) {
                    $table->decimal('fli_adj', 16, 8)->nullable()->after('pd_value');
                } else {
                    $table->decimal('fli_adj', 16, 8)->nullable();
                }
            }

            if (! Schema::hasColumn('loan_books', 'pd_post_fli')) {
                $table->decimal('pd_post_fli', 16, 8)->nullable()->after('fli_adj');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_books')) {
            return;
        }

        Schema::table('loan_books', function (Blueprint $table) {
            foreach (['fli_adj', 'pd_post_fli'] as $column) {
                if (Schema::hasColumn('loan_books', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
