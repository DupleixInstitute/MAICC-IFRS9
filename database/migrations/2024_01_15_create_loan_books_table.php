<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Schema::create('loan_books', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
        //     $table->integer('days_past_due')->default(0);
        //     $table->string('risk_category');
        //     $table->string('previous_status');
        //     $table->string('current_status');
        //     $table->timestamp('transition_date');
        //     $table->decimal('outstanding_balance', 15, 2);
        //     $table->decimal('pd_score', 8, 6);
        //     $table->timestamps();
        // });
    }

    public function down()
    {
        // Schema::dropIfExists('loan_books');
    }
};
