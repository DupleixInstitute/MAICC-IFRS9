<?php

namespace App\Services\Ebanker\DirectRead;

use RuntimeException;

/** A route 4 failure whose message is safe to show and log: it carries no connection detail. */
class DirectReadException extends RuntimeException
{
}
