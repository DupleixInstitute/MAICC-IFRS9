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
        Schema::create('scoring_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('scoring_attribute_group_id')->nullable();
            $table->text('name');
            $table->string('field_type')->nullable();
            $table->text('description')->nullable();
            $table->text('condition')->nullable();
            $table->text('options')->nullable();
            $table->string('default_values')->nullable();
            $table->text('rules')->nullable();
            $table->text('class')->nullable();
            $table->tinyInteger('required')->default(0);
            $table->tinyInteger('active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_attributes');
    }
};
