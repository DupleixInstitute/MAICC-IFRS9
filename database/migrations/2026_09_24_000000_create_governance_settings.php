<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| EIR Governance Centre - spec v3 (2026-09-24) sections 3.3 (D17), 6.3 and 8
|--------------------------------------------------------------------------
| Every calculation convention the EIR engine uses is a governed setting,
| not a line of code. One row per (key, effective_from): the value in force
| on a date is the latest APPROVED row whose effective_from is on or before
| that date, so a change applies forward only and a locked period keeps the
| settings it was locked under. The history table is an append-only copy of
| each row at the moment a later approval supersedes it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->index();
            $table->string('value', 60);
            $table->json('options');
            $table->string('label');
            $table->text('description');
            $table->date('effective_from');
            $table->foreignId('set_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('PROPOSED');
            $table->timestamps();

            $table->unique(['key', 'effective_from']);
        });

        Schema::create('governance_setting_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('setting_id')->index();
            $table->string('key', 60)->index();
            $table->string('value', 60);
            $table->json('options');
            $table->string('label');
            $table->text('description');
            $table->date('effective_from');
            $table->foreignId('set_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('status', 20);
            $table->timestamp('superseded_at');
            $table->foreignId('superseded_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('superseded_by_setting_id')->nullable()
                  ->comment('The governance_settings row whose approval superseded this one');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_setting_history');
        Schema::dropIfExists('governance_settings');
    }
};
