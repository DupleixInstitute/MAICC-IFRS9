<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deferral note on a held GL fee posting (Held Fee Classification Export/Reimport Specification v1,
 * section 6, DEFER), keyed by the posting's durable external_transaction_id rather than its
 * rebuild-prone numeric id. Deliberately its own small table, not a column on fee_gl_unassigned: that
 * table is rebuilt from scratch on a schedule (GlFeeBuildService), and a note here must survive the
 * rebuild. One note per posting; a later DEFER replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_gl_held_notes')) {
            Schema::create('fee_gl_held_notes', function (Blueprint $t) {
                $t->id();
                $t->string('external_transaction_id', 60)->unique();
                $t->string('note', 2000);
                $t->unsignedBigInteger('author_id');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_gl_held_notes');
    }
};
