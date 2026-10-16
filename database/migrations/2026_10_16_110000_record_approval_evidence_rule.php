<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the supporting-document rule came into force on this database
 * (Staging Module Specification v1, sections 8.3, 8.6 and 8.10): the moment
 * this release was migrated, which is when the code began to refuse an
 * approval without the evidence its setting or workflow requires. An approval
 * made before it was made under no such rule; it is shown, and carried by a
 * decisions pack, as "approved before supporting documents were required",
 * never as an approval that skipped its evidence. Written once; never moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_milestones')) {
            Schema::create('system_milestones', function (Blueprint $t) {
                $t->id();
                $t->string('key', 80)->unique();
                $t->timestamp('at');
                $t->string('note', 500)->nullable();
                $t->timestamps();
            });
        }
        if (! DB::table('system_milestones')->where('key', 'approval_evidence_rule')->exists()) {
            DB::table('system_milestones')->insert(['key' => 'approval_evidence_rule', 'at' => now(), 'created_at' => now(), 'updated_at' => now(),
                'note' => 'Supporting documents required before approval (Staging Module Specification v1, sections 8.3 and 8.6) from this moment on this database']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_milestones');
    }
};
