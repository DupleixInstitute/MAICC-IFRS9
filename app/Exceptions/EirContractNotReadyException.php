<?php

namespace App\Exceptions;

use DomainException;

class EirContractNotReadyException extends DomainException
{
    public function __construct(public readonly string $contractId, public readonly array $issues)
    {
        parent::__construct(
            "Contract {$contractId} is not ready for EIR calculation: " .
            implode('; ', array_column($issues, 'message'))
        );
    }
}
