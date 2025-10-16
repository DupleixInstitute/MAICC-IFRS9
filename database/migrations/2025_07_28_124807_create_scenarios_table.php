<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {

         Schema::create('scenario_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->string('profile_code');
            $table->text('description')->nullable();
            $table->boolean('complete')->default(false);
            $table->json('data')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('scenario_profiles')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('probability', 5, 2);
            $table->boolean('is_base_case')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('risk_disclaimer')->nullable();
            $table->text('key_drivers')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->enum('status', ['draft', 'under_review', 'approved', 'published'])->default('draft');
            $table->json('tags')->nullable();
            $table->timestamps();
        });
       
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
        Schema::dropIfExists('scenario_profiles');
    }
};
