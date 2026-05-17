<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original add_calculated_ifrs9_stage migration had its up()/down()
     * commented out, so the column was never created even though the LGD,
     * reconciliation, disbursement and discounting code all query it.
     */
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_books', 'calculated_ifrs9_stage')) {
                $table->string('calculated_ifrs9_stage')->nullable()->after('ifrs9_stage')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (Schema::hasColumn('loan_books', 'calculated_ifrs9_stage')) {
                $table->dropIndex(['calculated_ifrs9_stage']);
                $table->dropColumn('calculated_ifrs9_stage');
            }
        });
    }
};
