<?php

namespace App\Wallet;

use RuntimeException;

class InsufficientFunds extends RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $amount)
    {
        parent::__construct("Wallet balance {$balance} cannot cover {$amount}.");
    }
}
