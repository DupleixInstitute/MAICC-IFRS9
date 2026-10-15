<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The months each fee source covers (15 October 2026, per-period precedence).
 *
 * fee_source_coverage  one row per span a source was delivered for: the GL fee build writes the months the
 *                      GL_04a fee legs span (its row is replaced on each build); each Finance fee workbook
 *                      load adds the months its tie-out covers. FeeSourcePrecedence reads it: a source
 *                      wins a fee event only in the months it covers, so a lower source's dated line in a
 *                      month the source of record does not cover stands. A tier with no row covers
 *                      nothing known, and every line is treated as inside it (fail closed: pair or overlap).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_source_coverage')) {
            Schema::create('fee_source_coverage', function (Blueprint $t) {
                $t->id();
                $t->string('tier', 40);
                $t->char('month_from', 7);
                $t->char('month_to', 7);
                $t->string('origin', 255);
                $t->unsignedBigInteger('recorded_by')->nullable();
                $t->timestamps();
                $t->index('tier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_source_coverage');
    }
};
