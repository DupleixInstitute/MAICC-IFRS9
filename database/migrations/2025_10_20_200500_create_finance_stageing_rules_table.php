<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_stageing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('institution_type')->default('default');
            $table->decimal('stage_1_threshold', 8, 2)->default(30);
            $table->decimal('stage_3_threshold', 8, 2)->default(90);
            $table->timestamps();
            $table->unique(['institution_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_stageing_rules');
    }
};
