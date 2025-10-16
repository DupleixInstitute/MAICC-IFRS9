<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shareholders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('itc_date')->nullable();
            $table->date('dob')->nullable();
            $table->decimal('shares', 65)->nullable();
            $table->string('itc_ref_no')->nullable();
            $table->date('itc_ref_date')->nullable();
            $table->integer('number_of_paid_debts')->nullable();
            $table->integer('number_of_defaulted_debts')->nullable();
            $table->integer('number_of_judgements')->nullable();
            $table->integer('number_of_trace_alerts')->nullable();
            $table->tinyInteger('blacklisted');
            $table->tinyInteger('fraud_alert');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shareholders');
    }
};
