<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Maker-checker on two acts that undo a control (documentation audit of
| 14 October 2026)
|--------------------------------------------------------------------------
| eir_reopen_requests      reopening a locked original EIR: one person with
|                          eir.govern requests it with a reason, a different
|                          person approves it, and only then does
|                          EirCalculationService::reopen run (it refuses
|                          while a month the EIR reached is locked).
| period_unlock_requests   unlocking a locked reporting period: one person
|                          requests it with a reason, a different person
|                          approves it. Locking stays one person's act.
| A request is PROPOSED, then APPROVED, REJECTED (by another person, with a
| reason) or WITHDRAWN (by the person who asked).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eir_reopen_requests')) {
            Schema::create('eir_reopen_requests', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 64);
                $t->string('status', 12)->default('PROPOSED')->comment('PROPOSED, APPROVED, REJECTED, WITHDRAWN');
                $t->string('reason', 500);
                $t->unsignedBigInteger('requested_by');
                $t->timestamp('requested_at')->nullable();
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->string('decision_reason', 500)->nullable();
                $t->timestamps();
                $t->index(['contract_id', 'status']);
            });
        }
        if (! Schema::hasTable('period_unlock_requests')) {
            Schema::create('period_unlock_requests', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7);
                $t->string('status', 12)->default('PROPOSED')->comment('PROPOSED, APPROVED, REJECTED, WITHDRAWN');
                $t->string('reason', 500);
                $t->unsignedBigInteger('requested_by');
                $t->timestamp('requested_at')->nullable();
                $t->unsignedBigInteger('decided_by')->nullable();
                $t->timestamp('decided_at')->nullable();
                $t->string('decision_reason', 500)->nullable();
                $t->timestamps();
                $t->index(['reporting_period', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('period_unlock_requests');
        Schema::dropIfExists('eir_reopen_requests');
    }
};
