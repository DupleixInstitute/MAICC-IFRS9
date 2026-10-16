<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macro data overrides under maker-checker (Staging Module Specification v1,
 * section 8.6). A manual value replacing an imported one, a correction of an
 * observation and a deletion of one took effect as soon as they were entered,
 * with a reason. Each is now a proposal here, applied only when a second
 * person approves it with the source of the value attached.
 *
 *   kind            manual (a new value for a series and period), correction
 *                   (a new value for an observation) or deletion
 *   payload         what is applied on approval (the value, forecast or
 *                   actual, the publication date)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('macro_override_requests')) {
            return;
        }
        Schema::create('macro_override_requests', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);
            $t->string('series_code', 60)->nullable();
            $t->unsignedBigInteger('observation_id')->nullable();
            $t->json('payload');
            $t->text('reason');
            $t->string('status', 20)->default('PROPOSED')->index();
            $t->unsignedBigInteger('proposed_by')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->json('result')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macro_override_requests');
    }
};
