<?php

use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System audit of 9 October 2026, finding H10. The seeded governance defaults
 * were effective from 1 January 2025 (the first loan-book month), but the
 * schedule generator and the EIR resolve a setting as at the contract's
 * origination date, and 63 of MAIIC's 144 contracts predate 2024: sixteen
 * were refused for "no approved value is in force for day_count". The seeder
 * now dates defaults from inception; this backdates the defaults an installed
 * database already holds. Only the seeder's own untouched rows move: the
 * seeder's reason, no proposer, no approver, effective 2025-01-01. A value a
 * MAIIC person proposed or approved is never touched, and a key that already
 * has a row at the inception date is left alone.
 */
return new class extends Migration
{
    private const OLD_EFFECTIVE_FROM = '2025-01-01';

    public function up(): void
    {
        if (! Schema::hasTable('governance_settings')) {
            return;
        }
        $moved = 0;
        foreach (DB::table('governance_settings')->where('reason', GovernanceSettingsSeeder::REASON)->whereNull('set_by')->whereNull('approved_by')
            ->whereDate('effective_from', self::OLD_EFFECTIVE_FROM)->where('status', 'APPROVED')->get(['id', 'key']) as $row) {
            if (DB::table('governance_settings')->where('key', $row->key)->whereDate('effective_from', GovernanceSettingsSeeder::EFFECTIVE_FROM)->exists()) {
                continue;
            }
            DB::table('governance_settings')->where('id', $row->id)->update(['effective_from' => GovernanceSettingsSeeder::EFFECTIVE_FROM]);
            $moved++;
        }
        if ($moved > 0 && Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'user_id' => null, 'action' => 'Governance Defaults Backdated', 'entity_type' => 'governance_settings', 'entity_id' => null, 'rows_affected' => $moved,
                'old_values' => json_encode(['effective_from' => self::OLD_EFFECTIVE_FROM]),
                'new_values' => json_encode(['effective_from' => GovernanceSettingsSeeder::EFFECTIVE_FROM, 'rows' => $moved]),
                'meta' => json_encode(['reason' => 'System audit 9 Oct 2026, finding H10: seeded defaults are in force from inception so every contract resolves its settings at origination.']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('governance_settings')) {
            return;
        }
        DB::table('governance_settings')->where('reason', GovernanceSettingsSeeder::REASON)->whereNull('set_by')->whereNull('approved_by')
            ->whereDate('effective_from', GovernanceSettingsSeeder::EFFECTIVE_FROM)->update(['effective_from' => self::OLD_EFFECTIVE_FROM]);
    }
};
