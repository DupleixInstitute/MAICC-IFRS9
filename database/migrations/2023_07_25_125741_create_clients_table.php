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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->unsignedBigInteger('village_id')->nullable();
            $table->string('industry_type')->nullable();
            $table->string('industry_code')->nullable();
            $table->string('external_id')->nullable();
            $table->integer('client_id')->nullable();
            $table->enum('type', ['individual', 'corporate'])->default('individual');
            $table->string('name')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->unsignedBigInteger('title_id')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('nationality_id')->nullable();
            $table->text('address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('tel')->nullable();
            $table->string('mobile')->nullable();
            $table->enum('id_type', array('national', 'license', 'passport', 'other'))->nullable()->default('national');
            $table->string('id_number')->nullable();
            $table->string('email')->nullable();
            $table->date('dob')->nullable();
            $table->enum('marital_status', array('married', 'single', 'divorced', 'widowed', 'other'))->nullable();
            $table->string('zip')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_phone')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('is_active')->default(0);
            $table->string('status')->nullable()->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
