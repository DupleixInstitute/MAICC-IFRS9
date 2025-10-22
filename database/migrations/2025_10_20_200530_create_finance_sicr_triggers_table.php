<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_sicr_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('finance_sicr_groups');
            $table->foreignId('item_id')->constrained('finance_sicr_items');
            $table->string('account_number');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->foreignId('triggered_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_sicr_triggers');
    }
};
