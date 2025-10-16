<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->tinyInteger('ecl_stage')->default(1)->comment('1: Stage 1 (12-month ECL), 2: Stage 2 (Lifetime ECL), 3: Stage 3 (Credit-impaired)');
            $table->decimal('ecl_provision', 20, 2)->default(0)->comment('Expected Credit Loss provision amount');
            $table->index('ecl_stage');
        });
    }

    public function down()
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn('ecl_stage');
            $table->dropColumn('ecl_provision');
        });
    }
};
