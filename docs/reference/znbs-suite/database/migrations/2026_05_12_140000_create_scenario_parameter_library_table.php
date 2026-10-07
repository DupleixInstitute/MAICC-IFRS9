<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_parameter_library', function (Blueprint $table): void {
            $table->id();
            $table->string('family', 30)->index();
            $table->string('parameter_key', 80);
            $table->string('label', 120);
            $table->string('unit', 30)->nullable();
            $table->decimal('default_value', 20, 6)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['family', 'parameter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_parameter_library');
    }
};
