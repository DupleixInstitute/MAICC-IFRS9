<?php

namespace App\Services\Ebanker\DirectRead;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * E-Banker's Oracle database over the `ebanker` connection (config/database.php, EBANKER_DB_* in the
 * environment file), used by the route 4 runner and the connection test only.
 *
 * The session is set up as the manual packs' RUN 0 (ISO dates, point decimal) and with E-Banker's
 * schema as the current schema, so the registered statements run unqualified, as Barry runs them.
 * Every statement runs inside its own SET TRANSACTION READ ONLY: a second guard beside the account's
 * own grants (data access request, section 6.1).
 *
 * Needs the Oracle driver (yajra/laravel-oci8 and PHP's oci8 extension with the Instant Client),
 * which is not installed yet; until it is, connecting fails with that said plainly.
 */
class OracleConnectionSource implements EbankerSource
{
    public const CONNECTION = 'ebanker';

    private ?ConnectionInterface $db = null;

    public function select(string $sql, array $bindings = []): iterable
    {
        $db = $this->connection();
        $db->beginTransaction();
        try {
            $db->statement('SET TRANSACTION READ ONLY');
            foreach ($db->cursor($sql, $bindings) as $row) {
                $out = [];
                foreach ((array) $row as $column => $value) {
                    $out[strtoupper((string) $column)] = self::normaliseValue($value);
                }
                yield $out;
            }
            $db->commit();
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
        }
    }

    public function describe(): string
    {
        $c = config('database.connections.' . self::CONNECTION, []);

        return sprintf('Oracle %s:%s/%s, schema %s', $c['host'] ?? '?', $c['port'] ?? '?', $c['service_name'] ?? '?', $c['current_schema'] ?? '?');
    }

    /**
     * Oracle turns a NUMBER below one into text without its leading zero (.5, -.25); SQL Developer
     * writes 0.5. Restored so that a row read here hashes as the same row exported by hand.
     */
    public static function normaliseValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = (string) $value;

        return preg_match('/^(-?)\.(\d+)$/', $text, $m) ? $m[1] . '0.' . $m[2] : $text;
    }

    private function connection(): ConnectionInterface
    {
        if ($this->db !== null) {
            return $this->db;
        }
        $schema = (string) config('database.connections.' . self::CONNECTION . '.current_schema', '');
        if (! preg_match('/^[A-Z][A-Z0-9_$#]{0,29}$/', $schema)) {
            throw new DirectReadException("EBANKER_DB_SCHEMA '{$schema}' is not an Oracle schema name.");
        }
        try {
            $db = DB::connection(self::CONNECTION);
            $db->statement("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
            $db->statement("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
            $db->statement("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'");
            $db->statement('ALTER SESSION SET CURRENT_SCHEMA = ' . $schema);
        } catch (InvalidArgumentException $e) {
            throw new DirectReadException('Route 4 cannot connect: the Oracle driver is not installed on this server (yajra/laravel-oci8 and the oci8 extension). ' . $e->getMessage());
        } catch (Throwable $e) {
            throw new DirectReadException('Route 4 cannot connect to E-Banker: ' . self::oracleError($e));
        }

        return $this->db = $db;
    }

    /** The Oracle error number only, or a message of ours: a driver's message can carry the connection string. */
    public static function oracleError(Throwable $e): string
    {
        if (preg_match('/(ORA|TNS)-\d{5}/', $e->getMessage(), $m)) {
            return $m[0];
        }

        return $e instanceof DirectReadException ? $e->getMessage() : class_basename($e);
    }
}
