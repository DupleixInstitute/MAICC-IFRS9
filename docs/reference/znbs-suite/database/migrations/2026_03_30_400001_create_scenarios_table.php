<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('scenario_type', 20);    // baseline | adverse | severe | custom
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft | approved | archived
            $table->unsignedSmallInteger('base_year');
            $table->unsignedTinyInteger('horizon_years')->default(3);
            $table->boolean('is_icaap_scenario')->default(false);
            $table->decimal('probability_weight', 5, 4)->nullable(); // e.g. 0.20 for 20%
            $table->string('narrative', 500)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
