<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\DirectRead\EbankerSource;
use App\Services\Ebanker\DirectRead\RegisteredStatements;
use RuntimeException;

/** E-Banker as route 4 sees it, keyed by the registered statement each call carries. */
class FakeEbanker implements EbankerSource
{
    /** @var list<array{query_id:string,bindings:array,sql:string}> */
    public array $calls = [];
    private array $byStatement;

    public function __construct(private readonly array $rows, private readonly ?string $failOn = null)
    {
        $this->byStatement = array_flip(RegisteredStatements::files());
    }

    public function select(string $sql, array $bindings = []): iterable
    {
        $id = $this->byStatement[$sql] ?? 'UNKNOWN';
        $this->calls[] = ['query_id' => $id, 'bindings' => $bindings, 'sql' => $sql];
        if ($id === $this->failOn) {
            throw new RuntimeException('ORA-00942: table or view does not exist (connection secret-host:1521)');
        }
        yield from $this->rows[$id] ?? [];
    }

    public function describe(): string
    {
        return 'the test fake';
    }

    public function bindsFor(string $queryId): ?array
    {
        foreach ($this->calls as $c) {
            if ($c['query_id'] === $queryId) {
                return $c['bindings'];
            }
        }

        return null;
    }
}
