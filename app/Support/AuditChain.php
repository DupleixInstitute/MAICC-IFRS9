<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log as a chain (Staging Module Specification v1, section 8.5).
 *
 * Each entry written through App\Models\AuditLog carries the fingerprint of
 * the entry before it (prev_hash) and its own (entry_hash): SHA-256 over the
 * previous fingerprint and the entry's content in a canonical form (the JSON
 * values decoded, their keys sorted, encoded again), so a database that
 * reformats JSON does not break the chain but any change of a value does.
 * Entries written before the chain began (no entry_hash) are not chained.
 *
 * verify() recomputes the chain in order and names the first entry that does
 * not follow: one changed, one removed from the middle (the next entry's
 * prev_hash no longer matches), or one inserted without the chain.
 */
final class AuditChain
{
    /** The fields of an entry the fingerprint covers. */
    public const FIELDS = ['user_id', 'action', 'entity_type', 'entity_id', 'scope', 'reporting_period', 'rows_affected', 'old_values', 'new_values', 'meta', 'created_at'];

    /** Whether this database's audit log is chained; remembered per connection (a test's fresh database is asked again). */
    public static function enabled(): bool
    {
        static $known = null;
        $known ??= new \WeakMap();
        $pdo = DB::connection()->getPdo();

        // only a yes is remembered: the bootstrap migrates a fresh database on the same connection
        if (isset($known[$pdo])) {
            return true;
        }
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'entry_hash')) {
            return $known[$pdo] = true;
        }

        return false;
    }

    /** @param array<string,mixed> $attributes the entry as stored (JSON fields as text) */
    public static function hash(?string $prev, array $attributes): string
    {
        $content = [];
        foreach (self::FIELDS as $f) {
            $v = $attributes[$f] ?? null;
            if (in_array($f, ['old_values', 'new_values', 'meta'], true)) {
                $v = self::canonical(is_string($v) ? json_decode($v, true) : $v);
            } elseif ($f === 'created_at' && $v !== null) {
                $v = substr((string) $v, 0, 19);
            } elseif ($v !== null) {
                $v = (string) $v;
            }
            $content[$f] = $v;
        }

        return hash('sha256', ($prev ?? '') . '|' . json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** The last chained entry's fingerprint, read under a lock so two writers cannot fork the chain. */
    public static function last(): ?string
    {
        $row = DB::table('audit_logs')->whereNotNull('entry_hash')->orderByDesc('id')->lockForUpdate()->first(['entry_hash']);

        return $row?->entry_hash;
    }

    /**
     * Recompute the chain.
     *
     * @return array{entries:int,intact:bool,first_broken:?int,reason:?string,guard:bool}
     */
    public static function verify(): array
    {
        if (! self::enabled()) {
            return ['entries' => 0, 'intact' => true, 'first_broken' => null, 'reason' => 'the audit log is not chained on this database', 'guard' => false];
        }
        $prev = null;
        $n = 0;
        $broken = null;
        $reason = null;
        $chained = false;
        foreach (DB::table('audit_logs')->orderBy('id')->cursor() as $row) {
            if ($row->entry_hash === null) {
                if ($chained) {
                    [$broken, $reason] = [(int) $row->id, 'an entry written after the chain began carries no fingerprint'];
                    break;
                }
                continue;
            }
            $chained = true;
            $n++;
            if ($row->prev_hash !== $prev) {
                [$broken, $reason] = [(int) $row->id, 'it does not follow the entry before it (an entry was removed or inserted)'];
                break;
            }
            if (self::hash($prev, (array) $row) !== $row->entry_hash) {
                [$broken, $reason] = [(int) $row->id, 'its content no longer matches its fingerprint (the entry was changed)'];
                break;
            }
            $prev = $row->entry_hash;
        }

        return ['entries' => $n, 'intact' => $broken === null, 'first_broken' => $broken, 'reason' => $reason, 'guard' => self::guardInstalled()];
    }

    /** Whether the database refuses an update or delete of an entry (the MySQL and MariaDB triggers). */
    public static function guardInstalled(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return DB::table('sqlite_master')->where('type', 'trigger')->whereIn('name', ['audit_logs_append_only_update', 'audit_logs_append_only_delete'])->count() === 2;
        }
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }
        try {
            return DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())
                ->whereIn('TRIGGER_NAME', ['audit_logs_append_only_update', 'audit_logs_append_only_delete'])->count() === 2;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function canonical(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map(fn ($x) => self::canonical($x), $v);
    }
}
