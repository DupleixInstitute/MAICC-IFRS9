<?php

namespace App\Services\Eir;

use RuntimeException;

/** A confirmed restructure that cannot be applied stops its month, with the reason (fail closed). */
class ModificationBlockedException extends RuntimeException
{
    public function __construct(public readonly int $modificationId, string $message)
    {
        parent::__construct($message);
    }
}
