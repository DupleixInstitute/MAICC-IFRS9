<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Governance Centre change log (Staging Module Specification v1, sections
 * 8.1 to 8.5; phase 4, section 7).
 *
 *   governance_settings, governance_setting_history
 *       reason        kept in full (it was cut to 500 characters)
 *       reviewed_by, reviewed_at, review_reason
 *                     who rejected or withdrew a proposal, when and why; the
 *                     proposal stays on record as REJECTED or WITHDRAWN
 *   audit_logs
 *       prev_hash, entry_hash
 *                     each entry carries the fingerprint of the entry before
 *                     it, so the log is a chain a check can recompute
 *                     (AuditChain); entries before this migration are not
 *                     chained
 *       on MySQL and MariaDB, triggers refuse any UPDATE or DELETE of an entry:
 *       the log is append-only. Creating a trigger needs the TRIGGER privilege;
 *       without it the guard is reported as not installed and the chain alone
 *       detects a change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['governance_settings', 'governance_setting_history'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->text('reason')->nullable()->change();
                if ($table === 'governance_settings' && ! Schema::hasColumn($table, 'review_reason')) {
                    $t->unsignedBigInteger('reviewed_by')->nullable();
                    $t->timestamp('reviewed_at')->nullable();
                    $t->text('review_reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'entry_hash')) {
            Schema::table('audit_logs', function (Blueprint $t) {
                $t->char('prev_hash', 64)->nullable();
                $t->char('entry_hash', 64)->nullable()->index();
            });
        }

        if (Schema::hasTable('audit_logs') && in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_update');
                DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_delete');
                DB::unprepared("CREATE TRIGGER audit_logs_append_only_update BEFORE UPDATE ON audit_logs FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is append-only: an entry cannot be changed (Staging Module Specification v1, section 8.5)'");
                DB::unprepared("CREATE TRIGGER audit_logs_append_only_delete BEFORE DELETE ON audit_logs FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The audit log is append-only: an entry cannot be deleted (Staging Module Specification v1, section 8.5)'");
            } catch (\Throwable $e) {
                // no TRIGGER privilege: the chain still detects a change; the guard's absence is reported by AuditChain::guardInstalled()
                logger()->warning('audit_logs append-only guard not installed: ' . $e->getMessage());
            }
        }
        if (Schema::hasTable('audit_logs') && DB::getDriverName() === 'sqlite') {
            // the same guard on SQLite (the test database), so the suite exercises it
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_delete');
            DB::unprepared("CREATE TRIGGER audit_logs_append_only_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'The audit log is append-only: an entry cannot be changed'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_append_only_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'The audit log is append-only: an entry cannot be deleted'); END");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only_delete');
        }
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'entry_hash')) {
            Schema::table('audit_logs', function (Blueprint $t) {
                $t->dropIndex(['entry_hash']);
                $t->dropColumn(['prev_hash', 'entry_hash']);
            });
        }
        if (Schema::hasTable('governance_settings') && Schema::hasColumn('governance_settings', 'review_reason')) {
            Schema::table('governance_settings', fn (Blueprint $t) => $t->dropColumn(['reviewed_by', 'reviewed_at', 'review_reason']));
        }
    }
};
