<?php

namespace App\Wallet;

use RuntimeException;

/**
 * The same idempotency key came back with a different wallet, type or amount: a bug in the caller, never a retry.
 */
class IdempotencyConflict extends RuntimeException
{
    public function __construct(string $idempotencyKey)
    {
        parent::__construct("Idempotency key [{$idempotencyKey}] was already used for a different wallet entry.");
    }
}
