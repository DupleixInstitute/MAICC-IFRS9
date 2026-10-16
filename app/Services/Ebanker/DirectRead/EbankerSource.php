<?php

namespace App\Services\Ebanker\DirectRead;

/** Where route 4 reads E-Banker from: the Oracle connection in use, a fake in the tests. */
interface EbankerSource
{
    /**
     * Run one statement and yield its rows, column names upper case, values as text (null for SQL NULL).
     *
     * @param  array<string, string|int>  $bindings
     * @return iterable<array<string, string|null>>
     */
    public function select(string $sql, array $bindings = []): iterable;

    /** A label for the manifest and the audit trail: which database, never the credentials. */
    public function describe(): string;
}
