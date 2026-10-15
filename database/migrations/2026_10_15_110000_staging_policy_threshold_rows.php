<?php

use Database\Seeders\StagingThresholdSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The threshold rows of the single-ladder and IFRS 9 profiles (Staging Module
 * Specification v1, section 3) on a database seeded before 15 October 2026,
 * such as MAIIC's server: the release runs migrations but not
 * StagingThresholdSeeder, and without the single-ladder row staging stops
 * under the seeded default policy.
 *
 * A row is added only when no row of its class exists; no existing row is
 * read, changed or removed, so a threshold MAIIC approved on the Staging & SICR
 * Rules screen is untouched. The rows are seeded defaults: no proposer and no
 * approver, the reason naming them as such, never as a MAIIC approval.
 */
return new class extends Migration
{
    public const REASON = 'Seeded default of the Staging Module Specification v1 (migration of 15 October 2026), not a MAIIC approval';

    public function up(): void
    {
        if (! Schema::hasTable('staging_thresholds')) {
            return;
        }
        $governed = Schema::hasColumn('staging_thresholds', 'status');
        foreach (StagingThresholdSeeder::profileRows() as $row) {
            if (DB::table('staging_thresholds')->where('facility_class', $row['facility_class'])->exists()) {
                continue;
            }
            DB::table('staging_thresholds')->insert($row + ($governed ? ['status' => 'APPROVED', 'reason' => self::REASON] : []) + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Removes only the rows this migration added, as it added them. */
    public function down(): void
    {
        if (! Schema::hasTable('staging_thresholds') || ! Schema::hasColumn('staging_thresholds', 'reason')) {
            return;
        }
        DB::table('staging_thresholds')->where('reason', self::REASON)->whereNull('approved_by')->delete();
    }
};
