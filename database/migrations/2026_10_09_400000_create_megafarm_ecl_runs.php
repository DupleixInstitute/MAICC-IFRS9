<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Each run of the Mega Farm programme's ECL with its basis (spec v4 section 16, D30). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('megafarm_ecl_runs', function (Blueprint $t) {
            $t->id(); $t->string('reporting_period', 7)->index(); $t->string('scope'); $t->string('method'); $t->unsignedInteger('loans'); $t->decimal('gross', 20, 2); $t->decimal('programme_ecl', 20, 2); $t->decimal('share', 6, 4); $t->decimal('maiic_ecl', 20, 2);
            $t->string('declined', 500)->nullable(); $t->json('basis')->nullable(); $t->unsignedBigInteger('run_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('megafarm_ecl_runs');
    }
};
