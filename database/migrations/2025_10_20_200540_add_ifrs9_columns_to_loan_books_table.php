<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_books', 'ifrs9_stage_prequalitative')) {
                $table->unsignedTinyInteger('ifrs9_stage_prequalitative')->default(1)->after('ifrs9_stage');
            }
            if (!Schema::hasColumn('loan_books', 'sicr_trigger')) {
                $table->unsignedTinyInteger('sicr_trigger')->default(0)->after('ifrs9_stage_prequalitative');
            }
            if (!Schema::hasColumn('loan_books', 'ifrs9_stage_postqualitative')) {
                $table->unsignedTinyInteger('ifrs9_stage_postqualitative')->default(1)->after('sicr_trigger');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            if (Schema::hasColumn('loan_books', 'ifrs9_stage_postqualitative')) {
                $table->dropColumn('ifrs9_stage_postqualitative');
            }
            if (Schema::hasColumn('loan_books', 'sicr_trigger')) {
                $table->dropColumn('sicr_trigger');
            }
            if (Schema::hasColumn('loan_books', 'ifrs9_stage_prequalitative')) {
                $table->dropColumn('ifrs9_stage_prequalitative');
            }
        });
    }
};
