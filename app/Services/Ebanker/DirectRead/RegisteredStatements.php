<?php

namespace App\Services\Ebanker\DirectRead;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The statements route 4 may execute (data access request of 14 October 2026, sections 4.2 and 6.1):
 * one committed file per query id under resources/ebanker-queries/route4, each a single SELECT with
 * bind variables (:watermark, :period_start) in place of the SQLcl substitutions of route 2. The
 * seeder copies each into the register with its SHA-256; the runner executes a statement only while
 * the register's text still hashes to the register's fingerprint.
 */
class RegisteredStatements
{
    public const FOLDER = 'resources/ebanker-queries/route4';

    public const BINDS = ['watermark', 'period_start'];

    /** @return array<string, string> query id => statement, from the committed files */
    public static function files(): array
    {
        $out = [];
        foreach (glob(base_path(self::FOLDER) . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            $id = pathinfo($path, PATHINFO_FILENAME);
            $sql = self::normalise((string) file_get_contents($path));
            self::assertSelect($id, $sql);
            $out[$id] = $sql;
        }
        ksort($out);

        return $out;
    }

    public static function normalise(string $sql): string
    {
        return trim(rtrim(trim(str_replace(["\r\n", "\r"], "\n", $sql)), ';'));
    }

    public static function fingerprint(string $sql): string
    {
        return hash('sha256', $sql);
    }

    /** One statement, and a query: a ';' anywhere would let a second statement ride along. */
    public static function assertSelect(string $queryId, string $sql): void
    {
        if (! preg_match('/^(SELECT|WITH)\b/i', $sql) || str_contains($sql, ';')) {
            throw new RuntimeException("{$queryId}: a route 4 statement must be one SELECT, with no ';'.");
        }
    }

    /** @return list<string> the bind names the statement uses, so only those are passed */
    public static function bindsIn(string $sql): array
    {
        return array_values(array_filter(self::BINDS, fn ($b) => preg_match('/:' . $b . '\b/', $sql) === 1));
    }

    /**
     * The register's runnable statements, each checked against its fingerprint. One mismatch
     * refuses the whole read: a statement changed in the database is not the approved one.
     *
     * @return list<object> register rows with sql set
     */
    public static function runnable(): array
    {
        $rows = DB::table('ebanker_queries')->whereNotNull('sql')->orderBy('query_id')->get()->all();
        foreach ($rows as $q) {
            if ($q->sha256 === null || ! hash_equals($q->sha256, self::fingerprint($q->sql))) {
                throw new RuntimeException("{$q->query_id}: the register's statement no longer matches its fingerprint; route 4 runs nothing until the register is re-seeded from the committed files.");
            }
            self::assertSelect($q->query_id, $q->sql);
        }

        return $rows;
    }
}
