<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Mega Farm programme is provided on one method (the governed megafarm_pd_method) and may be
 * measured on another beside it for comparison, writing nothing to the loans. Every run before this
 * migration was the booked one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('megafarm_ecl_runs') && ! Schema::hasColumn('megafarm_ecl_runs', 'role')) {
            Schema::table('megafarm_ecl_runs', function (Blueprint $t) {
                $t->string('role', 20)->default('booked')->after('method')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('megafarm_ecl_runs') && Schema::hasColumn('megafarm_ecl_runs', 'role')) {
            Schema::table('megafarm_ecl_runs', function (Blueprint $t) {
                $t->dropIndex('megafarm_ecl_runs_role_index');
                $t->dropColumn('role');
            });
        }
    }
};
