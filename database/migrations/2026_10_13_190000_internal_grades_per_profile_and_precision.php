<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internal grades (13 October 2026), for the S&P grading of the December 2025 ECL model:
 * a grade code is unique within its profile, not across every profile (two profiles may
 * both have a grade 1); a grade's PD range is a probability (0.0002 to 0.9999), not a
 * whole number; and a cumulative PD keeps six decimals.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        Schema::table('internal_grade_mappings', function (Blueprint $t) {
            $t->dropUnique('internal_grade_mappings_grade_code_unique');
        });
        Schema::table('internal_grade_mappings', function (Blueprint $t) {
            $t->unique(['profile_id', 'grade_code'], 'internal_grade_mappings_profile_grade_unique');
        });
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE internal_grade_mappings MODIFY lower_bound DECIMAL(12,8) NULL, MODIFY upper_bound DECIMAL(12,8) NULL');
            DB::statement('ALTER TABLE grade_tenor_pd MODIFY pd_probability DECIMAL(12,8) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('internal_grade_mappings', function (Blueprint $t) {
            $t->dropUnique('internal_grade_mappings_profile_grade_unique');
            $t->unique('grade_code');
        });
    }
};
